<?php

return [
    /*
    | Origen de datos por defecto: `snapshot` lee archivos JSON generados con
    | `onneb:export`; `database` lee directamente de SQL Server.
    */
    'source' => env('ONNEB_SOURCE', 'snapshot'),

    // Empresa dentro de OnneB, que comparte tablas entre varias empresas.
    // Solo se usa al leer de SQL Server.
    'empnit' => env('ONNEB_EMPNIT'),

    'sources' => [
        'database' => [
            // Nombre de una conexión ya definida en config/database.php. Si
            // queda vacío, se registra una con las credenciales de abajo.
            'connection' => env('ONNEB_DB_CONNECTION'),

            'credentials' => [
                'driver' => 'sqlsrv',
                'host' => env('ONNEB_DB_HOST'),
                'port' => env('ONNEB_DB_PORT', '1433'),
                'database' => env('ONNEB_DB_DATABASE', 'ONNE'),
                'username' => env('ONNEB_DB_USERNAME'),
                'password' => env('ONNEB_DB_PASSWORD'),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
            ],
        ],

        'snapshot' => [
            'path' => env('ONNEB_SNAPSHOT_PATH', database_path('data/onneb')),
        ],
    ],

    'mapping' => [
        // Medidas adicionales: 'CODMEDIDA' => ['CODIGO', 'Nombre'].
        'extra_units' => [],

        // Marcas de OnneB que no identifican a un fabricante.
        'generic_brands' => ['VARIOS'],

        // Quita del nombre el laboratorio final y el contenido del empaque
        // («CAJA X 100 TABLETAS»), que pasa a la presentación. El nombre
        // original queda en la descripción.
        'clean_names' => env('ONNEB_CLEAN_NAMES', true),
    ],

    'review' => [
        'disk' => env('ONNEB_REVIEW_DISK', 'local'),
        'directory' => 'onneb',

        // Nombre del sistema de destino en los encabezados del Excel.
        'target_name' => env('ONNEB_TARGET_NAME', 'Destino'),
    ],

    // El libro de Excel se arma en memoria. Nunca reduce un límite mayor.
    'memory_limit' => '1G',
];
