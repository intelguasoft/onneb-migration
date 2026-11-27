<?php

namespace RealsoftGt\OnneB\Mapping;

use Illuminate\Support\Str;
use RealsoftGt\OnneB\Data\Catalog;

/**
 * Traduce PROVEEDORES y liga cada producto con sus proveedores: el principal
 * sale de una columna de PRODUCTOS (en FARMARURAL, CODCLADOS) y los alternos,
 * de las compras registradas en OnneB.
 *
 * OnneB usa sus campos con libertad: TELCONTACTO suele traer la condición de
 * pago («30 DIAS CREDITO») y CONTACTO, «SN». Lo que no tiene un campo propio
 * en el destino viaja en `notes`, para no perderlo.
 *
 * @phpstan-type MappedSupplier array{
 *     code: string, legal_name: string, trade_name: ?string, tax_id: string, source_tax_id: ?string,
 *     phone: ?string, mobile: ?string, contact: ?string, address: ?string, credit_days: ?int, balance: float,
 *     notes: ?string, primary_products: int, linked_products: int, review: list<string>
 * }
 * @phpstan-type SupplierLink array{code: string, is_primary: bool, purchases: int, last_purchase: ?string}
 */
class SupplierMapper
{
    /** Valores de relleno que OnneB guarda en lugar de dejar el campo vacío. */
    private const PLACEHOLDERS = ['', 'SN', 'S/N', 'N/A', 'NA', '-', '.', '0', 'NINGUNO'];

    /** NIT de Consumidor Final o sin dato. */
    private const NO_TAX_ID = ['', 'CF', 'C/F', 'C.F.', 'CONSUMIDORFINAL', 'SN', 'S/N', '0'];

    /** Lo que se revisa de cada proveedor; las claves están en IssueCatalog::SUPPLIER_ISSUES. */
    public const REVIEW = ['nit_repetido', 'nit_invalido', 'proveedor_sin_nit', 'proveedor_con_saldo', 'credito_por_confirmar'];

    /**
     * @param  ?string  $primaryColumn  Columna de PRODUCTOS con el CODPROV del proveedor principal; null si no se usa.
     */
    public function __construct(private readonly ?string $primaryColumn = null) {}

    /**
     * Vínculos y proveedores principales inexistentes van por CODPROD en mayúsculas.
     *
     * @return array{suppliers: list<MappedSupplier>, links: array<string, list<SupplierLink>>, missing: array<string, string>}
     */
    public function map(Catalog $catalog): array
    {
        $rows = [];

        foreach ($catalog->suppliers as $row) {
            $rows[$this->code($row['CODPROV'])] = $row;
        }

        [$links, $missing] = $this->links($catalog, $rows);

        $primary = [];
        $linked = [];

        foreach ($links as $productLinks) {
            foreach ($productLinks as $link) {
                $linked[$link['code']] = ($linked[$link['code']] ?? 0) + 1;

                if ($link['is_primary']) {
                    $primary[$link['code']] = ($primary[$link['code']] ?? 0) + 1;
                }
            }
        }

        $suppliers = [];

        foreach ($rows as $code => $row) {
            $suppliers[] = $this->supplier((string) $code, $row, $primary[$code] ?? 0, $linked[$code] ?? 0);
        }

        return [
            'suppliers' => $this->flagRepeatedTaxIds($suppliers),
            'links' => $links,
            'missing' => $missing,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $suppliers
     * @return array{0: array<string, list<array{code: string, is_primary: bool, purchases: int, last_purchase: ?string}>>, 1: array<string, string>}
     */
    private function links(Catalog $catalog, array $suppliers): array
    {
        $history = [];

        foreach ($catalog->purchases as $purchase) {
            $code = $this->code($purchase['CODPROV']);

            if (isset($suppliers[$code])) {
                $history[$this->key($purchase['CODPROD'])][$code] = [(int) $purchase['COMPRAS'], (string) $purchase['ULTIMA_COMPRA']];
            }
        }

        $links = [];
        $missing = [];

        foreach ($catalog->products as $product) {
            $key = $this->key($product['CODPROD']);
            $bought = $history[$key] ?? [];
            $primary = $this->primary($product);
            $productLinks = [];

            if ($primary !== null && ! isset($suppliers[$primary])) {
                $missing[$key] = $primary;
                $primary = null;
            }

            if ($primary !== null) {
                $productLinks[] = $this->link($primary, true, $bought[$primary] ?? null);
                unset($bought[$primary]);
            }

            // Primero a quien más se le compró y, a igualdad, la compra más reciente.
            uksort($bought, fn (string $a, string $b): int => [$bought[$b][0], $bought[$b][1], $a] <=> [$bought[$a][0], $bought[$a][1], $b]);

            foreach ($bought as $code => $purchase) {
                $productLinks[] = $this->link((string) $code, false, $purchase);
            }

            if ($productLinks !== []) {
                $links[$key] = $productLinks;
            }
        }

        return [$links, $missing];
    }

    /**
     * @param  ?array{0: int, 1: string}  $purchase
     * @return array{code: string, is_primary: bool, purchases: int, last_purchase: ?string}
     */
    private function link(string $code, bool $primary, ?array $purchase): array
    {
        return [
            'code' => $code,
            'is_primary' => $primary,
            'purchases' => $purchase[0] ?? 0,
            'last_purchase' => $purchase[1] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function primary(array $product): ?string
    {
        if ($this->primaryColumn === null) {
            return null;
        }

        $code = $this->code($product[$this->primaryColumn] ?? '');

        return $code === '' || $code === '0' ? null : $code;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return MappedSupplier
     */
    private function supplier(string $code, array $row, int $primaryProducts, int $linkedProducts): array
    {
        $tradeName = $this->text($row['EMPRESA']);
        $legalName = $this->text($row['RAZONSOCIAL']);
        $taxId = $this->taxId($row['NIT']);
        $companyPhone = $this->text($row['TELEMPRESA']);
        $contact = $this->text($row['CONTACTO']);
        $contactPhone = $this->text($row['TELCONTACTO']);
        $terms = $this->creditDays($contactPhone) ?? $this->creditDays($contact) ?? $this->creditDays($companyPhone);
        $balance = round((float) $row['SALDO'], 2);

        $phone = $this->phone($companyPhone);
        $mobile = $this->phone($contactPhone);
        $contactName = $contact !== null && $this->phone($contact) === null && $this->creditDays($contact) === null ? $contact : null;
        $address = $this->text($row['DIRECCION']);

        $notes = array_filter([
            $address === null ? null : "Dirección: {$address}",
            $contactName === null ? null : "Contacto: {$contactName}",
            $companyPhone !== null && $phone === null ? "Teléfono en OnneB: {$companyPhone}" : null,
            $contactPhone !== null && $mobile === null ? "Condición en OnneB: {$contactPhone}" : null,
        ]);

        $review = [];

        if ($taxId === 'CF') {
            $review[] = 'proveedor_sin_nit';
        } elseif (! self::isValidTaxId($taxId)) {
            $review[] = 'nit_invalido';
        }

        if ($balance !== 0.0) {
            $review[] = 'proveedor_con_saldo';
        }

        if ($terms === null) {
            $review[] = 'credito_por_confirmar';
        }

        return [
            'code' => $code,
            'legal_name' => $legalName ?? $tradeName ?? "Proveedor {$code}",
            'trade_name' => $tradeName ?? $legalName,
            'tax_id' => $taxId,
            'source_tax_id' => $this->text($row['NIT']),
            'phone' => $phone,
            'mobile' => $mobile,
            'contact' => $contactName,
            'address' => $address,
            'credit_days' => $terms,
            // Saldo de OnneB: negativo es lo que se le debe al proveedor.
            'balance' => $balance,
            'notes' => $notes === [] ? null : implode("\n", $notes),
            'primary_products' => $primaryProducts,
            'linked_products' => $linkedProducts,
            'review' => $review,
        ];
    }

    /**
     * Un NIT en varios proveedores puede ser el mismo tercero dado de alta dos veces.
     *
     * @param  list<MappedSupplier>  $suppliers
     * @return list<MappedSupplier>
     */
    private function flagRepeatedTaxIds(array $suppliers): array
    {
        $count = array_count_values(array_filter(array_column($suppliers, 'tax_id'), fn (string $taxId): bool => $taxId !== 'CF'));

        foreach ($suppliers as &$supplier) {
            if (($count[$supplier['tax_id']] ?? 0) > 1) {
                array_unshift($supplier['review'], 'nit_repetido');
            }
        }

        return $suppliers;
    }

    /**
     * NIT sin guiones, espacios ni puntos, en mayúsculas; `CF` si no hay.
     */
    public static function normalizeTaxId(mixed $value): string
    {
        $taxId = mb_strtoupper(str_replace(['-', ' ', '.'], '', trim((string) $value)));

        return in_array($taxId, self::NO_TAX_ID, true) ? 'CF' : $taxId;
    }

    /**
     * Dígito verificador del NIT de Guatemala (módulo 11, `K` para 10).
     */
    public static function isValidTaxId(string $taxId): bool
    {
        if ($taxId === 'CF') {
            return true;
        }

        if (! preg_match('/^(\d+)([0-9K])$/', $taxId, $matches)) {
            return false;
        }

        $sum = 0;

        foreach (array_reverse(str_split($matches[1])) as $position => $digit) {
            $sum += (int) $digit * ($position + 2);
        }

        $check = (11 - $sum % 11) % 11;

        return $matches[2] === ($check === 10 ? 'K' : (string) $check);
    }

    private function taxId(mixed $value): string
    {
        return self::normalizeTaxId($value);
    }

    /**
     * Días de crédito escritos a mano: «30 DIAS CREDITO» da 30 y «CONTADO», 0.
     */
    private function creditDays(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $text = mb_strtoupper(Str::ascii($value));

        if (preg_match('/(\d{1,3})\s*DIAS?\b/', $text, $matches)) {
            return (int) $matches[1];
        }

        return str_contains($text, 'CONTADO') ? 0 : null;
    }

    /**
     * Solo si parece un número de teléfono: al menos 8 dígitos y nada de letras.
     */
    private function phone(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^[\d\s\-\/+().]+$/', $value) || strlen(preg_replace('/\D/', '', $value) ?? '') < 8) {
            return null;
        }

        return mb_substr($value, 0, 30);
    }

    private function text(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return in_array(mb_strtoupper($text), self::PLACEHOLDERS, true) ? null : $text;
    }

    private function code(mixed $value): string
    {
        return trim((string) $value);
    }

    private function key(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
