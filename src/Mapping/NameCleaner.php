<?php

namespace RealsoftGt\OnneB\Mapping;

/**
 * Deja en el nombre solo lo que identifica al producto.
 *
 * OnneB mezcla en la descripción el laboratorio y el empaque, como en
 * «ACETAMINOFEN 500MG CAJA X 100 TABLETAS [MK]». La marca ya viaja en su campo
 * y el contenido pertenece a la presentación: en el nombre confunde al vender
 * una fracción del empaque. Solo se quita lo que se reconoce con certeza; la
 * concentración y el tamaño se conservan, escritos siempre de la misma forma.
 */
class NameCleaner
{
    /** Empaques que OnneB escribe junto al contenido: «CAJA X 100». */
    private const PACKS = 'CAJAS?|CJA|CJ|BLISTERS?|BLIS|FRASCOS?|FCO|BOTES?|TUBOS?|SOBRES?|BOLSAS?|PAQUETES?|PAQ|BOTELLAS?|DISPENSADOR|DISPLAY|DISP|TARROS?|LATAS?|ESTUCHES?';

    /** Lo que cuenta el contenido => [singular, plural] para la presentación. */
    private const NOUNS = [
        'TABLETAS?|TABS?|TABELTAS' => ['tableta', 'tabletas'],
        'C[AÁ]PSULAS?|CAPS?|CAPUSLAS' => ['cápsula', 'cápsulas'],
        'GRAGEAS?' => ['gragea', 'grageas'],
        'COMPRIMIDOS?|COMRPIMIDOS|COMPRIMDIOS|COMRPIMDOS' => ['comprimido', 'comprimidos'],
        'CAPLETS?|CAPLETAS?' => ['caplet', 'caplets'],
        'GEL\s?CAPS?' => ['gelcap', 'gelcaps'],
        'FLACONETES?' => ['flaconete', 'flaconetes'],
        'UNIDADES|UNIDAD|UNDS?|UNI|UDS' => ['unidad', 'unidades'],
        'PIEZAS?|PZAS?' => ['pieza', 'piezas'],
        'SOBRES?|SACHETS?' => ['sobre', 'sobres'],
        'AMPOLLAS?|AMPOLLETAS?' => ['ampolla', 'ampollas'],
        'VIALES|VIAL|VIABLES' => ['vial', 'viales'],
        'UNIDOSIS' => ['unidosis', 'unidosis'],
        '[OÓ]VULOS?' => ['óvulo', 'óvulos'],
        'SUPOSITORIOS?' => ['supositorio', 'supositorios'],
        'TIRAS?' => ['tira', 'tiras'],
        'PARCHES?' => ['parche', 'parches'],
        'LANCETAS?' => ['lanceta', 'lancetas'],
        'JERINGAS?' => ['jeringa', 'jeringas'],
        'PARES|PAR' => ['par', 'pares'],
        'PA[ÑN]ALES|PA[ÑN]AL' => ['pañal', 'pañales'],
        'TOALLAS?' => ['toalla', 'toallas'],
        'PERLAS?' => ['perla', 'perlas'],
        'PASTILLAS?' => ['pastilla', 'pastillas'],
        'CHICLES?' => ['chicle', 'chicles'],
        'GOMITAS?' => ['gomita', 'gomitas'],
        'GOMAS?' => ['goma', 'gomas'],
        'STICKS?' => ['stick', 'sticks'],
        'CONDONES|COND[OÓ]N' => ['condón', 'condones'],
        'APLICADORES|APLICADOR' => ['aplicador', 'aplicadores'],
        'CARTERITAS?' => ['carterita', 'carteritas'],
        'LATAS?' => ['lata', 'latas'],
        'CAJAS?' => ['caja', 'cajas'],
        'PACKS?' => ['pack', 'packs'],
        'BARRAS?' => ['barra', 'barras'],
        'SOFTGELS?' => ['softgel', 'softgels'],
        'GASAS?' => ['gasa', 'gasas'],
        'TRATAMIENTOS?' => ['tratamiento', 'tratamientos'],
        'AGUJAS?' => ['aguja', 'agujas'],
        'CURITAS?' => ['curita', 'curitas'],
        'AP[OÓ]SITOS?' => ['apósito', 'apósitos'],
        'BLISTERS?' => ['blíster', 'blísteres'],
        'FRASCOS?|FCOS?' => ['frasco', 'frascos'],
        'PRUEBAS?' => ['prueba', 'pruebas'],
        'DOSIS' => ['dosis', 'dosis'],
    ];

    /** Medidas de tamaño: se conservan, solo pierden la «X» del empaque. */
    private const MEASURES = 'MLS?|CC|LTS?|LITROS?|L|GRS?|GRAMOS?|G|KGS?|MG|MCG|ONZAS?|ONZ|OZ|LBS?|LIBRAS?|MTS?|CMS?|M|YDS?|YARDAS?';

    /** Cada forma en que OnneB escribe una medida => su notación uniforme. */
    private const UNITS = [
        'MCG|UG' => 'mcg',
        'MG' => 'mg',
        'KGS?' => 'kg',
        'GRAMOS?|GRMS?|GMS|GRS?|G' => 'g',
        'MLS?|CC' => 'mL',
        'LITROS?|LTS?|L' => 'L',
        'U\.I|UI' => 'UI',
        'MEQ' => 'mEq',
        'ONZAS?|OZN|ONZ|OZ' => 'oz',
        'LIBRAS?|LBS?' => 'lb',
        'CMS?' => 'cm',
        'MM' => 'mm',
        'METROS?|MTS?' => 'm',
        'YARDAS?|YDS?' => 'yd',
    ];

    /** En agujas y catéteres la «G» es el calibre, no gramos: «21G», «#23 GR». */
    private const GAUGED = '/\b(?:AGUJAS?|JERINGAS?|CATETER|ANGIOCATH|SCALP|LANCETAS?|HIPODERMICAS?|CANULAS?|SUTURAS?)\b/u';

    /** Formas farmacéuticas que justifican conservar el sustantivo: «TABLETAS MASTICABLES». */
    private const FORMS = 'MASTICABLES?|RECUBIERTAS?|RECUBIERTOS?|BEBIBLES?|BEBILES?|EFERVESCENTES?|ORODISPERSABLES?|DISPERSABLES?|SUBLINGUALES?|VAGINALES?|GASTRORRESISTENTES?|SOLUBLES?|DE\s+GELATINA\s+BLANDA|DE\s+LIBERACI[OÓ]N\s+[A-ZÁÉÍÓÚ]+';

    /** «X» de un conteo: el sustantivo que le sigue evita confundirla. */
    private const COUNT_TIMES = '(?<![\w\/])[X*]';

    /** «X» sin sustantivo: no la de una medida como «6 X 10 YDS» o «3" X 5». */
    private const TIMES = '(?<![\w\/])(?<![\d"\']\s)[X*]';

    public function clean(string $name): CleanedName
    {
        [$text, $label] = $this->withoutLabel($name);
        [$text, $content] = $this->withoutContent($text);

        $text = preg_replace('/(?:\b(?:'.self::PACKS.')\.?\s*)?'.self::TIMES.'\s*(\d+(?:[.,]\d+)?\s*(?:'.self::MEASURES.'))\b\.?/iu', '$1', $text) ?? $text;

        $text = $this->tidy($text);

        // «... 20G SOBRE» pierde el empaque; «SOBRE BLANCO» y «SIN CAJA», no.
        $withoutPack = $this->tidy(preg_replace('/(?<!\bSIN|\bCON|\bDE|\bPARA|\bEN)\s\b(?:'.self::PACKS.')\.?$/iu', '', $text) ?? $text);

        if (count(explode(' ', $withoutPack)) >= 2) {
            $text = $withoutPack;
        }

        $text = $this->uniform($text);

        // Sin letras no queda un nombre: mejor el original que uno vacío.
        if (! preg_match('/\p{L}{2,}/u', $text)) {
            return new CleanedName($name);
        }

        return new CleanedName($text, $label, $content);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function withoutLabel(string $name): array
    {
        // El laboratorio va al final, a veces seguido de la «x» de OnneB.
        if (preg_match('/^(.*?\S)\s*[\[(]([^\[\]()]+)[\])]\s*x?$/iu', $name, $match)) {
            return [$match[1], trim($match[2])];
        }

        return [preg_replace('/([)\]])\s*x$/iu', '$1', $name) ?? $name, null];
    }

    /**
     * @return array{0: string, 1: ?array{count: int, display: string, singular: ?string, plural: ?string, text: string}}
     */
    private function withoutContent(string $text): array
    {
        $nouns = implode('|', array_keys(self::NOUNS));
        $pattern = '/(?:\b(?:'.self::PACKS.')\.?\s*)?'.self::COUNT_TIMES.'\s*(\d+)(?:\s*\+\s*(\d+))?\s*('.$nouns.')X?\b\.?'
            .'(?:(?:\s+(?:DE|CON)\s+|\s+[X*]\s*)(\d+)\s*('.$nouns.')X?\b\.?|\s+(?:DE|CON)\s+(\d+(?:[.,]\d+)?\s*(?:'.self::MEASURES.'))\b\.?)?'
            .'(\s+(?:'.self::FORMS.')\b)?/iu';

        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            $count = (int) $match[1][0];
            $promo = (int) $this->group($match, 2);
            $noun = $match[3][0];
            $display = $promo > 0 ? "{$count}+{$promo}" : (string) $count;
            $count += $promo;

            // «X 25 BLISTER DE 8 TABLETAS» son 200 tabletas.
            if ($this->group($match, 4) !== null) {
                $count *= (int) $this->group($match, 4);
                $display = (string) $count;
                $noun = (string) $this->group($match, 5);
            }

            $form = $this->group($match, 7);
            $keep = array_filter([$form === null ? null : $match[3][0].$form, $this->group($match, 6)]);

            [$singular, $plural] = $this->noun($noun);

            return [
                substr_replace($text, ' '.implode(' ', $keep).' ', $match[0][1], strlen($match[0][0])),
                ['count' => $count, 'display' => $display, 'singular' => $singular, 'plural' => $plural, 'text' => trim($match[0][0])],
            ];
        }

        // Un conteo sin sustantivo al final: «GUANTES TALLA S x100», «X 10 + 2».
        if (preg_match('/(?:\b(?:'.self::PACKS.')\.?\s*)?'.self::TIMES.'\s*(\d+)(?:\s*\+\s*(\d+))?\.?$/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
            $promo = (int) $this->group($match, 2);

            return [
                substr($text, 0, $match[0][1]),
                [
                    'count' => (int) $match[1][0] + $promo,
                    'display' => $promo > 0 ? "{$match[1][0]}+{$promo}" : $match[1][0],
                    'singular' => null,
                    'plural' => null,
                    'text' => trim($match[0][0]),
                ],
            ];
        }

        return [$text, null];
    }

    /**
     * Una sola forma de escribir: el texto en mayúsculas y cada medida separada
     * del número, en su notación y sin punto: «500 mg», «250 mg/5 mL», «30 g».
     */
    private function uniform(string $text): string
    {
        $text = mb_strtoupper($text);
        $gauged = preg_match(self::GAUGED, $text) === 1;
        $units = implode('|', array_keys(self::UNITS));

        // Una medida pegada a la palabra anterior: «SUSPENSION120 ML».
        $text = preg_replace('/(?<=\p{L}{3})(\d{2,}(?:[.,]\d+)?\s*(?:'.$units.')\.?)(?![\p{L}\d])/u', ' $1', $text) ?? $text;

        $text = preg_replace_callback(
            '/(?<![\p{L}\d#.,])(\d+(?:[.,]\d+)?)\s*('.$units.')\.?(?![\p{L}\d])/u',
            fn (array $match): string => $gauged && preg_match('/^(?:GRS?|G)$/', $match[2])
                ? $match[0]
                : $match[1].' '.$this->unit($match[2]),
            $text,
        ) ?? $text;

        // La medida de una razón sin número: «8 mg/ML» es «8 mg/mL».
        $text = preg_replace_callback(
            '/\/\s*('.$units.')\.?(?![\p{L}\d])/u',
            fn (array $match): string => '/'.$this->unit($match[1]),
            $text,
        ) ?? $text;

        $text = preg_replace('/(\d)\s+%/u', '$1%', $text) ?? $text;

        // La «x» suelta al final, y el punto tras una palabra completa; el de
        // una abreviatura como «SOL. OFT.» o «I.M.» se conserva.
        $text = preg_replace('/\sX$/u', '', $this->tidy($text)) ?? $text;

        return preg_replace('/(\s\p{L}{4,})\.$/u', '$1', $this->tidy($text)) ?? $text;
    }

    private function unit(string $written): string
    {
        foreach (self::UNITS as $pattern => $unit) {
            if (preg_match('/^(?:'.$pattern.')$/u', $written)) {
                return $unit;
            }
        }

        return $written;
    }

    /**
     * Texto de un grupo opcional, o null si no participó en la coincidencia.
     *
     * @param  array<int, array{0: string, 1: int}>  $match
     */
    private function group(array $match, int $index): ?string
    {
        return ($match[$index][1] ?? -1) >= 0 ? $match[$index][0] : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function noun(string $word): array
    {
        foreach (self::NOUNS as $pattern => $forms) {
            if (preg_match('/^(?:'.$pattern.')$/iu', $word)) {
                return $forms;
            }
        }

        return [mb_strtolower($word), mb_strtolower($word)];
    }

    private function tidy(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return preg_replace('/[\s,;:\-\/+]+$/u', '', $text) ?? $text;
    }
}
