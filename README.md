# TechStore · Panel de administración

Aplicación CRUD en PHP y MySQL para administrar la base `techstore` desde XAMPP.

## Ejecutar en local

1. Copiá esta carpeta dentro de `C:\xampp\htdocs\Tienda de informatica`.
2. Iniciá Apache y MySQL desde el panel de XAMPP.
3. Confirmá que la base `techstore` está importada en MySQL.
4. Abrí `http://localhost/Tienda%20de%20informatica/`.

La configuración local por defecto usa `127.0.0.1`, base `techstore`, usuario `root` y contraseña vacía. Se puede cambiar en `config.php` o definir `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` en el entorno.

## Funciones

- Panel con conteo de registros y últimas ventas.
- Alta, consulta, búsqueda, edición y eliminación de productos, categorías, clientes, proveedores, ventas y detalles de venta.
- Selectores para relaciones entre tablas; el subtotal del detalle se calcula a partir de cantidad y precio unitario.
- Vista de solo lectura `detalleventas`, visible también en el dashboard, con descarga CSV UTF-8 compatible con Excel y filtros aplicables a la exportación.
- Consultas parametrizadas y token CSRF en formularios.

Los registros relacionados respetan las restricciones de la base. Por ejemplo, una categoría o un cliente usados en otros registros no se pueden eliminar hasta quitar esas referencias.

## Publicación

GitHub puede alojar el código. Netlify sirve sitios estáticos y no ejecuta esta aplicación PHP ni aloja la base MySQL. Para una publicación funcional habrá que alojar PHP y MySQL en servicios compatibles, o convertir el backend a funciones/API compatibles con Netlify y mover los datos a una base accesible desde esas funciones. No subas credenciales a GitHub.
