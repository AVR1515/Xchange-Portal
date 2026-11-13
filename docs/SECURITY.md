# Seguridad: alcance y verificación

## Amenazas cubiertas en diseño
- IDOR: cada consulta de request/document requiere pertenencia al proyecto.
- SQLi: usar únicamente PDO con sentencias preparadas.
- CSRF: token por sesión en cada POST que cambie estado.
- XSS: escaping HTML de títulos, comentarios y nombres.
- Session fixation: regenerar identificador al autenticar.
- Subida insegura: extensión lista blanca + MIME por fileinfo + tamaño máximo + nombre aleatorio.
- Exposición directa: bytes en `storage/private` fuera de `public/`, y reglas `.htaccess` denegatorias.
- Descarga segura: archivo nunca es enlazado por ruta de disco; descargar via PHP con check de permisos.
- Gestión de contraseñas: `password_hash`, `password_verify`.

## Validación antes de producción

- Configura HTTPS y sirve exclusivamente `public/` como raíz web. Comprueba que el inicio de sesión solo esté disponible por HTTPS y que la cookie de sesión tenga el atributo Secure.
- Configura una cuenta de MariaDB exclusiva del portal con permisos sobre sus tablas. Usa una cuenta separada para migraciones y excluye las credenciales de Git.
- Verifica el límite de intentos de inicio de sesión en el entorno de despliegue. Deshabilita las cuentas de demostración antes de habilitar el acceso externo.
- Define el escaneo de adjuntos, el cifrado y la retención de respaldos. Prueba la restauración de la base y de los archivos privados juntos.
- Repite las pruebas de acceso, entrega, revisión y descarga en el servidor de destino. Comprueba las cabeceras de seguridad y confirma que los errores se registren sin exponer detalles al navegador.

## Registro de incidentes

Los errores inesperados del portal muestran un código de incidente. Busca ese código en el registro de errores de PHP o Apache para identificar el tipo de excepción y su ubicación. Configura `display_errors=Off` y `log_errors=On` en el servidor de producción, también para errores de arranque de PHP.

## Checklist de pruebas manuales
1. Solicitar request ajena cambiando ID: acceso denegado.
2. Solicitar descarga de otro proyecto: acceso denegado.
3. Intentar POST sin CSRF: rechazado.
4. Subir archivo .php, .html, o con MIME inesperado: rechazado.
5. Inyectar etiquetas script en título/comentario: aparece como texto, no ejecuta.
6. Subir más de 20 MB: rechazado.
7. Probar transición no permitida para cliente: rechazado.
8. Cerrar sesión y reusar enlaces protegidos: login obligatorio.
