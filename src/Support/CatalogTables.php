<?php

namespace RealsoftGt\OnneB\Support;

/**
 * Tablas de OnneB que forman el catálogo.
 */
final class CatalogTables
{
    /** Tabla y columna que fija el orden de sus filas. */
    public const ORDER = [
        'PRODUCTOS' => 'CODPROD',
        'PRECIOS' => 'ID',
        'MARCAS' => 'CODMARCA',
        'INVSALDO' => 'CODPROD',
    ];

    /** Columnas que necesita el mapeo. */
    public const REQUIRED_COLUMNS = [
        'PRODUCTOS' => [
            'CODPROD', 'CODPROD2', 'DESPROD', 'DESPROD2', 'DESPROD3', 'UXC', 'COSTO',
            'CODMARCA', 'INVMINIMO', 'INVMAXIMO', 'EXENTO', 'TIPOPROD', 'HABILITADO',
        ],
        'PRECIOS' => ['ID', 'CODPROD', 'CODMEDIDA', 'EQUIVALE', 'COSTO', 'PRECIO', 'MAYOREOA', 'MAYOREOB', 'MAYOREOC'],
        'MARCAS' => ['CODMARCA', 'DESMARCA'],
        'INVSALDO' => ['CODPROD', 'SALDO'],
    ];
}
