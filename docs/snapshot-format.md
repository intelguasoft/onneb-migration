# Formato de la extracción

Una extracción es una carpeta con un archivo por tabla y un manifiesto:

```text
database/data/onneb/
├── manifest.json
├── PRODUCTOS.json
├── PRECIOS.json
├── MARCAS.json
└── INVSALDO.json
```

## Tablas

Cada archivo es un arreglo JSON con una fila por línea, lo que permite comparar extracciones con `diff` o Git.

```json
[
{"ID":1,"EMPNIT":"MIEMPRESA","CODPROD":"1005","DESPROD":"AMOXICILINA","COSTO":"1.0000"},
{"ID":2,"EMPNIT":"MIEMPRESA","CODPROD":"1006","DESPROD":"ACETAMINOFEN","COSTO":"0.5000"}
]
```

- Conserva nombres de tablas y columnas de SQL Server.
- Incluye todas las columnas excepto las binarias, como `PRODUCTOS.FOTO`.
- Incluye solo la empresa indicada, también los productos deshabilitados. El filtro `HABILITADO` se aplica al leer.
- Los decimales se guardan como texto cuando el controlador los entrega así, para no perder precisión.

## Manifiesto

```json
{
    "formato": 1,
    "origen": "OnneB",
    "empnit": "MIEMPRESA",
    "exportado_en": "2026-01-15T08:00:00-06:00",
    "tablas": {
        "PRODUCTOS": {
            "archivo": "PRODUCTOS.json",
            "filas": 2,
            "sha256": "…",
            "columnas": {"CODPROD": "varchar(50)", "COSTO": "numeric(18,4)"}
        }
    }
}
```

| Campo | Uso |
|---|---|
| `formato` | Versión del formato. Una extracción más nueva que el paquete se rechaza. Si falta, se asume 1. |
| `empnit` | Empresa extraída. |
| `exportado_en` | Fecha de los datos; aparece en el Excel. |
| `filas`, `sha256` | Detectan archivos truncados o alterados. |
| `columnas` | Tipo original de cada columna. |

`archivo` es informativo: el lector construye la ruta con el nombre de la tabla, de modo que un manifiesto alterado no puede apuntar fuera de la carpeta.

## Saltos de línea

El SHA-256 se calcula con saltos `LF`. El lector normaliza `CRLF` antes de comparar, porque Git en Windows puede convertirlos al clonar. Dentro de los valores JSON los saltos van escapados, por lo que esta normalización no altera los datos.

## Alcance de la verificación

El SHA-256 detecta daños accidentales y ediciones. No es una firma: quien pueda escribir en la carpeta también puede regenerar el manifiesto. Proteja la carpeta con los permisos habituales.
