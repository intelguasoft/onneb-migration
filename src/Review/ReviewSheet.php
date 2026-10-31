<?php

namespace RealsoftGt\OnneB\Review;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja del libro de revisión. Todo texto se escribe como texto: un código de
 * barras no debe volverse 7.41E+12 ni una descripción ejecutarse como fórmula.
 */
class ReviewSheet extends DefaultValueBinder implements Export, FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    private const WRAP_WIDTH = 60;

    /**
     * @param  list<list<mixed>>  $rows
     * @param  int  $headerRow  Fila de encabezados, donde inicia el filtro.
     * @param  ?int  $wrapFrom  Columna desde la que el texto largo se ajusta.
     */
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
        private readonly int $headerRow = 1,
        private readonly ?int $wrapFrom = null,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            $this->headerRow => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            ],
        ];

        if ($this->headerRow > 1) {
            $styles[1] = ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A5F']]];
            $styles[2] = ['font' => ['italic' => true, 'color' => ['rgb' => '505050']]];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $sheet->setAutoFilter("A{$this->headerRow}:{$lastColumn}{$lastRow}");
                $sheet->freezePane('A'.($this->headerRow + 1));

                if ($this->headerRow > 1) {
                    $sheet->mergeCells("A1:{$lastColumn}1");
                    $sheet->mergeCells("A2:{$lastColumn}2");
                    $sheet->mergeCells("A3:{$lastColumn}3");
                }

                if ($this->wrapFrom !== null) {
                    $this->wrapLongColumns($sheet, $lastRow);
                }
            },
        ];
    }

    private function wrapLongColumns(Worksheet $sheet, int $lastRow): void
    {
        $columns = range($this->wrapFrom, count($this->rows[$this->headerRow - 1]));

        foreach ($columns as $column) {
            $letter = chr(64 + $column);

            $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(self::WRAP_WIDTH);
            $sheet->getStyle("{$letter}{$this->headerRow}:{$letter}{$lastRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
        }
    }
}
