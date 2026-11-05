# Excel de revisión

Cada corrida genera dos libros en `storage/app/private/onneb/`:

```text
atencion-catalogo-20260115-080000-simulacion.xlsx
revision-catalogo-20260115-080000-simulacion.xlsx
```

## Libro de atención

Es por donde empieza el equipo. Trae una sola hoja, **Atención**, con lo que requiere a una persona:

- Una fila por producto, aunque tenga varias incidencias, con todas juntas.
- Solo incidencias de revisión; las informativas, como un costo derivado, quedan en el libro completo.
- Ordenado por prioridad y, dentro de cada prioridad, por existencia en OnneB: primero lo que más se vende y está en bodega.
- Columnas **Qué revisar**, **Detalle** y **Qué hacer**, y tres libres para el equipo: **Corregido**, **Responsable** y **Notas**.

`MigrationResult::$attentionPath` devuelve su ruta.

## Libro completo

| Hoja | Contenido |
|---|---|
| **Resumen** | Empresa, origen, fecha de los datos, totales e incidencias con su significado y acción |
| **Por corregir** | Una fila por incidencia, por prioridad y existencia, con costo, precio, margen y datos de OnneB |
| **Presentaciones** | OnneB y destino lado a lado en los productos con dudas de factor o precio |
| **Nombres** | Cada nombre que cambió: el de OnneB, el limpio, la marca y las presentaciones |
| **Mayoreo** | Precios A, B y C que no se migran |
| **Pendientes** | Tareas que reporta el importador, si existen |

La columna **Corregido** de **Por corregir** queda libre para control del equipo; el paquete no la lee.

## Incidencias

| Prioridad | Incidencias |
|---|---|
| Alta | Sin precio, Sin presentaciones, Sin costo, Costo mayor que el precio, Base ambigua, Factor no confiable, Precio invertido |
| Media | Base implícita, Factor tomado de UXC, Precio incoherente, Código de barras compartido, Código de barras en uso, Servicio con varias tarifas, Código duplicado, Contenido por confirmar, Laboratorio distinto de la marca |
| Baja | Costo derivado, Código de barras interno, Stock máximo inválido |

## Configuración

```php
'review' => [
    'disk' => env('ONNEB_REVIEW_DISK', 'local'),
    'directory' => 'onneb',
    'target_name' => env('ONNEB_TARGET_NAME', 'Destino'),
],
```

`target_name` aparece en encabezados como *Presentaciones en Destino*.

## Protección del libro

Todos los textos se escriben como texto, no como fórmulas. Así un código de barras no se convierte en `7.41E+12` y un dato de OnneB que empiece con `=` no se ejecuta en Excel.

El libro contiene costos y márgenes: el disco por defecto, `local`, no lo expone por web. No configure un disco público.
