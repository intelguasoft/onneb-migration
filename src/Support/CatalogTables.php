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
        'PROVEEDORES' => 'CODPROV',
    ];

    /**
     * Resumen de compras por producto y proveedor. No es una tabla de OnneB:
     * se arma con DOCUMENTOS, DOCPRODUCTOS y TIPODOCUMENTOS.
     */
    public const PURCHASES = 'COMPRAS';

    /**
     * Tablas que una extracción anterior o una instalación de OnneB pueden no
     * traer: sin ellas el catálogo se migra sin proveedores.
     */
    public const OPTIONAL = ['PROVEEDORES', self::PURCHASES];

    /** Columnas que necesita el mapeo. */
    public const REQUIRED_COLUMNS = [
        'PRODUCTOS' => [
            'CODPROD', 'CODPROD2', 'DESPROD', 'DESPROD2', 'DESPROD3', 'UXC', 'COSTO',
            'CODMARCA', 'INVMINIMO', 'INVMAXIMO', 'EXENTO', 'TIPOPROD', 'HABILITADO',
        ],
        'PRECIOS' => ['ID', 'CODPROD', 'CODMEDIDA', 'EQUIVALE', 'COSTO', 'PRECIO', 'MAYOREOA', 'MAYOREOB', 'MAYOREOC'],
        'MARCAS' => ['CODMARCA', 'DESMARCA'],
        'INVSALDO' => ['CODPROD', 'SALDO'],
        'PROVEEDORES' => ['CODPROV', 'EMPRESA', 'RAZONSOCIAL', 'DIRECCION', 'TELEMPRESA', 'CONTACTO', 'TELCONTACTO', 'NIT', 'SALDO'],
        self::PURCHASES => ['CODPROD', 'CODPROV', 'COMPRAS', 'ULTIMA_COMPRA'],
    ];

    /** Columnas del resumen de compras, con el tipo que declara el manifiesto. */
    public const PURCHASE_COLUMNS = [
        'CODPROD' => 'varchar',
        'CODPROV' => 'int',
        'COMPRAS' => 'int',
        'ULTIMA_COMPRA' => 'date',
    ];
}
