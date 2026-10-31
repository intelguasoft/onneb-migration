# Seguridad y datos

## Datos sensibles

Las extracciones y los Excel contienen costos, precios, márgenes y saldos.

- No los incluya en repositorios públicos.
- Si los versiona, use un repositorio privado. Si no, agregue `database/data/onneb` a `.gitignore` y cópielos por un canal seguro.
- No los guarde en discos públicos.
- El paquete no incluye datos de ningún cliente y su `.gitignore` excluye extracciones y libros de Excel.

## Acceso a SQL Server

Cree un usuario exclusivo con permiso `SELECT` sobre las cuatro tablas:

```sql
CREATE LOGIN onneb_lectura WITH PASSWORD = '<contraseña segura>';
CREATE USER onneb_lectura FOR LOGIN onneb_lectura;
GRANT SELECT ON dbo.PRODUCTOS TO onneb_lectura;
GRANT SELECT ON dbo.PRECIOS TO onneb_lectura;
GRANT SELECT ON dbo.MARCAS TO onneb_lectura;
GRANT SELECT ON dbo.INVSALDO TO onneb_lectura;
```

El paquete solo ejecuta `SELECT`, pero esta restricción lo garantiza desde la base. Si el usuario tampoco puede leer metadatos de las tablas, otorgue `VIEW DEFINITION` sobre ellas.

- Guarde las credenciales en `.env`, nunca en el repositorio.
- Después de migrar, retire `ONNEB_DB_*` si ya no las necesita.
- Con el origen `snapshot`, el destino no necesita credenciales ni `pdo_sqlsrv`.

## Controles del paquete

| Riesgo | Control |
|---|---|
| Leer otra empresa | `EMPNIT` es obligatorio en toda lectura de SQL Server |
| Escribir por accidente | Simulación por defecto; `--execute` explícito; confirmación en producción |
| Usar una extracción dañada | Verificación de filas, columnas y SHA-256 antes de leer |
| Reemplazar una extracción buena | Toda la lectura termina antes de escribir |
| Salir de la carpeta con un manifiesto alterado | La ruta se construye con el nombre de la tabla |
| Fórmulas en Excel | Todo texto se escribe como texto |
| Medidas desconocidas | La migración se detiene en vez de inventarlas |

## Reportar una vulnerabilidad

Siga [SECURITY.md](../SECURITY.md).
