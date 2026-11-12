# Instalación local con XAMPP (Windows)

## Requisitos
- XAMPP con Apache, PHP 8.2+ y MariaDB/MySQL.
- Extensiones PHP `pdo_mysql`, `fileinfo` y `mbstring` (si está disponible).
- No se necesita Composer, npm, npm install, CDN ni acceso a internet durante la ejecución.

## Pasos
1. Clonar el repositorio en `C:\\xampp\\htdocs\\Xchange-Portal` (o descargar ZIP y extraer).
2. Iniciar **Apache** y **MySQL** en XAMPP Control Panel.
3. Abrir phpMyAdmin y ejecutar `database/schema.sql`, o importar por consola usando `mysql -u root < database/schema.sql`.
4. Copiar `config/config.example.php` a `config/config.php`; ajustar credenciales locales (por defecto XAMPP: `root` sin contraseña, solo para desarrollo).
5. Ejecutar `php scripts/seed.php` desde raíz del proyecto para generar cuentas demo; **solo en entorno local**.
6. Abrir `http://localhost/Xchange-Portal/`. La redirección a `public/` usa el archivo `.htaccess` de la raíz y requiere `mod_rewrite` y `AllowOverride All` en Apache. También se puede abrir `http://localhost/Xchange-Portal/public/` directamente.
7. Para subir archivos de hasta 20MB, configurar `upload_max_filesize=20M`, `post_max_size=24M` y reiniciar Apache.

## Si PHP no está en PATH
Utilizar `C:\\xampp\\php\\php.exe scripts\\seed.php`.

## Comprobaciones
- Un usuario sin sesión debe ser redirigido al login.
- Un cliente no debe ver solicitudes de otro proyecto.
- Los archivos no deben poder abrirse mediante `/storage/private/...` en Apache.
- El navegador debe funcionar sin conexión a internet.
- En caso de error de conexión SQL, verificar que el servicio MySQL esté iniciado y DB_USER/DB_PASS correctos.

## Advertencia
Las credenciales de ejemplo, cuentas demo y HTTP localhost son solo para desarrollo. Para producción: HTTPS, `config.php` excluido de Git, secret management, backups, hardening, antivirus/escaneo de documentos y pruebas de seguridad.
