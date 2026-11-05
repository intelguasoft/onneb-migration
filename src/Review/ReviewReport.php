<?php

namespace RealsoftGt\OnneB\Review;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RealsoftGt\OnneB\Data\Catalog;
use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;
use RealsoftGt\OnneB\Mapping\CatalogMapper;

/**
 * Arma el Excel con lo que una persona debe revisar tras cada corrida, con el
 * dato de OnneB al lado del resultado para corregir sin volver a consultarlo.
 */
class ReviewReport
{
    /** Incidencias cuyo arreglo requiere ver las presentaciones. */
    private const PRESENTATION_ISSUES = [
        'base_ambigua', 'base_implicita', 'factor_no_confiable', 'factor_por_uxc',
        'precio_invertido', 'precio_incoherente',
    ];

    public function __construct(
        private readonly string $disk = 'local',
        private readonly string $directory = 'onneb',
        private readonly string $targetName = 'Destino',
    ) {}

    /**
     * Guarda el libro completo y, junto a él, el de atención.
     *
     * @return string Ruta absoluta del libro completo.
     */
    public function store(Catalog $catalog, MappedCatalog $mapped, ImportResult $import, bool $dryRun): string
    {
        $path = $this->directory.'/revision-catalogo-'.now()->format('Ymd-His').($dryRun ? '-simulacion' : '').'.xlsx';

        $products = collect($mapped->products)->keyBy(fn (array $product): string => $this->key($product['sku']));
        $prices = collect($catalog->prices)->groupBy(fn (array $row): string => $this->key($row['CODPROD']));

        Excel::store(
            new ReviewExport([$this->attentionSheet($import->issues, $products, $catalog->stock)]),
            $this->attentionFile($path),
            $this->disk,
            ExcelFormat::XLSX,
        );

        $sheets = [
            $this->summarySheet($catalog, $import, $dryRun),
            $this->issuesSheet($import->issues, $products, $prices, $catalog->stock),
            $this->presentationsSheet($import->issues, $products, $prices),
            $this->namesSheet($catalog->products, $products),
            $this->wholesaleSheet($catalog->prices, $products),
        ];

        if ($import->pending !== []) {
            $sheets[] = $this->pendingSheet($import->pending);
        }

        Excel::store(new ReviewExport($sheets), $path, $this->disk, ExcelFormat::XLSX);

        return Storage::disk($this->disk)->path($path);
    }

    /**
     * Libro de atención que acompaña a un libro completo.
     */
    public function attentionPath(string $reportPath): string
    {
        return $this->attentionFile($reportPath);
    }

    private function attentionFile(string $reportPath): string
    {
        return preg_replace('/revision-catalogo-(?=[^\/\\\\]*$)/', 'atencion-catalogo-', $reportPath) ?? $reportPath;
    }

    /**
     * Solo lo que requiere a una persona: una fila por producto, con todo lo
     * que hay que revisar de él, por prioridad y luego por existencia.
     *
     * @param  list<array<string, string>>  $issues
     * @param  Collection<string, array<string, mixed>>  $products
     * @param  array<string, float>  $stock
     */
    private function attentionSheet(array $issues, Collection $products, array $stock): ReviewSheet
    {
        $rows = collect($issues)
            ->filter(fn (array $issue): bool => $issue['severidad'] === 'revision')
            ->groupBy(fn (array $issue): string => $this->key($issue['codprod']))
            ->map(function (Collection $productIssues, string $key) use ($products, $stock): array {
                $catalogs = $productIssues->map(fn (array $issue): array => IssueCatalog::ISSUES[$issue['incidencia']]);
                $priority = $catalogs->sortBy(fn (array $issue): int => IssueCatalog::PRIORITIES[$issue['priority']])->first()['priority'];
                $first = $productIssues->first();
                $product = $products->get($key);

                return [
                    'order' => [IssueCatalog::PRIORITIES[$priority], -($stock[$key] ?? 0), $key],
                    'row' => [
                        $priority,
                        $first['codprod'],
                        $product['name'] ?? $first['descripcion'],
                        $product['brand'] ?? '',
                        $stock[$key] ?? 0,
                        $catalogs->pluck('label')->unique()->join(', '),
                        $productIssues->pluck('detalle')->unique()->join("\n"),
                        $catalogs->pluck('action')->unique()->map(fn (string $action): string => "• {$action}")->join("\n"),
                        implode(', ', array_column($product['presentations'] ?? [], 'name')),
                        $first['descripcion'],
                        '',
                        '',
                        '',
                    ],
                ];
            })
            ->sortBy('order')
            ->pluck('row')
            ->values()
            ->all();

        return new ReviewSheet('Atención', [
            ['Prioridad', 'Código', 'Producto', 'Marca', 'Existencia OnneB', 'Qué revisar', 'Detalle', 'Qué hacer', "Presentaciones en {$this->targetName}", 'Nombre en OnneB', 'Corregido', 'Responsable', 'Notas'],
            ...$rows,
        ], wrapFrom: 6);
    }

    private function summarySheet(Catalog $catalog, ImportResult $import, bool $dryRun): ReviewSheet
    {
        $extractedAt = Carbon::parse($catalog->extractedAt)->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $counts = collect($import->issues)->groupBy('incidencia')->map(fn (Collection $rows): int => $rows->pluck('codprod')->unique()->count());

        $rows = [
            ['Revisión del catálogo OnneB', '', '', '', '', ''],
            [($dryRun ? 'SIMULACIÓN: el catálogo no se escribió. ' : 'Catálogo cargado. ').'Generado el '.now()->format('d/m/Y H:i'), '', '', '', '', ''],
            ["Datos de OnneB ({$catalog->empnit}, origen {$catalog->origin}) del {$extractedAt}", '', '', '', '', ''],
            ['', '', '', '', '', ''],
        ];

        foreach ($import->summary as $label => $value) {
            $rows[] = [$label, $value, '', '', '', ''];
        }

        $rows[] = ['', '', '', '', '', ''];
        $header = count($rows) + 1;
        $rows[] = ['Prioridad', 'Incidencia', 'Productos', 'Qué significa', 'Qué se hizo', 'Qué debe hacer'];

        $issues = collect(IssueCatalog::ISSUES)
            ->filter(fn (array $issue, string $code): bool => isset($counts[$code]))
            ->sortBy(fn (array $issue, string $code): array => [IssueCatalog::PRIORITIES[$issue['priority']], -$counts[$code]]);

        foreach ($issues as $code => $issue) {
            $rows[] = [$issue['priority'], $issue['label'], $counts[$code], $issue['meaning'], $issue['done'], $issue['action']];
        }

        return new ReviewSheet('Resumen', $rows, $header, wrapFrom: 4);
    }

    /**
     * @param  list<array<string, string>>  $issues
     * @param  Collection<string, array<string, mixed>>  $products
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $prices
     * @param  array<string, float>  $stock
     */
    private function issuesSheet(array $issues, Collection $products, Collection $prices, array $stock): ReviewSheet
    {
        $rows = collect($issues)
            ->map(function (array $issue) use ($products, $prices, $stock): array {
                $key = $this->key($issue['codprod']);
                $product = $products->get($key);
                $catalog = IssueCatalog::ISSUES[$issue['incidencia']];

                return [
                    'order' => [IssueCatalog::PRIORITIES[$catalog['priority']], -($stock[$key] ?? 0), $issue['codprod']],
                    'row' => [
                        $catalog['priority'],
                        $issue['codprod'],
                        $product['name'] ?? $issue['descripcion'],
                        $product['brand'] ?? '',
                        $catalog['label'],
                        $issue['detalle'],
                        $catalog['action'],
                        $stock[$key] ?? 0,
                        $product['standard_cost'] ?? null,
                        $product['price'] ?? null,
                        $this->margin($product),
                        implode(', ', array_column($product['presentations'] ?? [], 'name')),
                        $this->sourceRows($prices->get($key)),
                        '',
                    ],
                ];
            })
            ->sortBy('order')
            ->pluck('row')
            ->all();

        return new ReviewSheet('Por corregir', [
            ['Prioridad', 'Código', 'Producto', 'Marca', 'Incidencia', 'Detalle', 'Qué hacer', 'Existencia OnneB', 'Costo base', 'Precio base', 'Margen %', "Presentaciones en {$this->targetName}", 'Datos en OnneB', 'Corregido'],
            ...$rows,
        ]);
    }

    /**
     * @param  list<array<string, string>>  $issues
     * @param  Collection<string, array<string, mixed>>  $products
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $prices
     */
    private function presentationsSheet(array $issues, Collection $products, Collection $prices): ReviewSheet
    {
        $rows = [];

        $flagged = collect($issues)
            ->whereIn('incidencia', self::PRESENTATION_ISSUES)
            ->groupBy(fn (array $issue): string => $this->key($issue['codprod']));

        foreach ($flagged as $key => $productIssues) {
            $product = $products->get($key);

            if ($product === null) {
                continue;
            }

            $labels = $productIssues->map(fn (array $issue): string => IssueCatalog::ISSUES[$issue['incidencia']]['label'])->unique()->join(', ');

            foreach ($product['presentations'] as $presentation) {
                $source = collect($prices->get($key) ?? [])
                    ->first(fn (array $row): bool => CatalogMapper::normalizeUnit((string) $row['CODMEDIDA']) === $presentation['unit']);

                $rows[] = [
                    $product['sku'],
                    $product['name'],
                    $labels,
                    $source === null ? '(creada por la migración)' : $source['CODMEDIDA'],
                    $source === null ? null : (float) $source['EQUIVALE'],
                    $source === null ? null : (float) $source['PRECIO'],
                    $source === null ? null : (float) $source['COSTO'],
                    $presentation['name'],
                    $presentation['base_factor'],
                    $presentation['is_base'] ? $product['price'] : $presentation['price'],
                    $presentation['is_base'] ? 'Sí' : 'No',
                    $presentation['is_active'] ? 'Sí' : 'No',
                    $presentation['is_sellable'] ? 'Sí' : 'No',
                ];
            }
        }

        return new ReviewSheet('Presentaciones', [
            ['Código', 'Producto', 'Incidencia', 'Medida OnneB', 'Factor OnneB', 'Precio OnneB', 'Costo OnneB', "Presentación {$this->targetName}", "Factor {$this->targetName}", "Precio {$this->targetName}", 'Base', 'Activa', 'Vendible'],
            ...$rows,
        ]);
    }

    /**
     * Cada nombre que cambió, para revisar la limpieza antes de imprimir facturas.
     *
     * @param  list<array<string, mixed>>  $sourceProducts
     * @param  Collection<string, array<string, mixed>>  $products
     */
    private function namesSheet(array $sourceProducts, Collection $products): ReviewSheet
    {
        $rows = [];

        foreach ($sourceProducts as $source) {
            $product = $products->get($this->key($source['CODPROD']));
            $original = trim(preg_replace('/\s+/u', ' ', (string) $source['DESPROD']) ?? '');

            if ($product === null || $product['name'] === $original) {
                continue;
            }

            $rows[] = [
                $product['sku'],
                $original,
                $product['name'],
                $product['brand'] ?? '',
                implode(', ', array_column($product['presentations'], 'name')),
            ];
        }

        return new ReviewSheet('Nombres', [
            ['Código', 'Nombre en OnneB', "Nombre en {$this->targetName}", 'Marca', "Presentaciones en {$this->targetName}"],
            ...$rows,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $prices
     * @param  Collection<string, array<string, mixed>>  $products
     */
    private function wholesaleSheet(array $prices, Collection $products): ReviewSheet
    {
        $rows = [];

        foreach ($prices as $row) {
            $price = (float) $row['PRECIO'];
            $tiers = [(float) $row['MAYOREOA'], (float) $row['MAYOREOB'], (float) $row['MAYOREOC']];

            if (array_filter($tiers, fn (float $tier): bool => $tier !== $price) !== []) {
                $rows[] = [
                    trim((string) $row['CODPROD']),
                    $products->get($this->key($row['CODPROD']))['name'] ?? '',
                    trim((string) $row['CODMEDIDA']),
                    (float) $row['EQUIVALE'],
                    $price,
                    ...$tiers,
                ];
            }
        }

        return new ReviewSheet('Mayoreo', [
            ['Código', 'Producto', 'Medida', 'Factor', 'Precio', 'Mayoreo A', 'Mayoreo B', 'Mayoreo C'],
            ...$rows,
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $pending
     */
    private function pendingSheet(array $pending): ReviewSheet
    {
        return new ReviewSheet('Pendientes', [
            ['Tema', 'Situación', 'Qué debe hacer', 'Responsable', 'Hecho'],
            ...array_map(fn (array $item): array => [...$item, '', ''], $pending),
        ], wrapFrom: 2);
    }

    /**
     * @param  ?array<string, mixed>  $product
     */
    private function margin(?array $product): ?float
    {
        if (($product['price'] ?? 0) <= 0 || ($product['standard_cost'] ?? 0) <= 0) {
            return null;
        }

        return round(($product['price'] - $product['standard_cost']) / $product['price'] * 100, 2);
    }

    /**
     * @param  ?Collection<int, array<string, mixed>>  $rows
     */
    private function sourceRows(?Collection $rows): string
    {
        return collect($rows ?? [])
            ->map(fn (array $row): string => sprintf(
                '%s: factor %s, precio %s, costo %s',
                trim((string) $row['CODMEDIDA']),
                (float) $row['EQUIVALE'],
                (float) $row['PRECIO'],
                (float) $row['COSTO'],
            ))
            ->join(' | ');
    }

    private function key(mixed $code): string
    {
        return mb_strtoupper(trim((string) $code));
    }
}
