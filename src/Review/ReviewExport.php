<?php

namespace RealsoftGt\OnneB\Review;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro de revisión del catálogo importado de OnneB.
 */
class ReviewExport implements Export, WithMultipleSheets
{
    /**
     * @param  list<ReviewSheet>  $sheets
     */
    public function __construct(private readonly array $sheets) {}

    public function sheets(): array
    {
        return $this->sheets;
    }
}
