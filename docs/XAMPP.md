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

## Respaldo y restauración de prueba

1. Detén Apache en el panel de XAMPP y deja MariaDB activo. Así se conservan juntos los registros y los archivos de la misma entrega.
2. En PowerShell, desde la raíz del repositorio, crea una carpeta de respaldo nueva fuera de `htdocs`. Los comandos usan la cuenta local `root`. Agrega `-p` si esa cuenta tiene contraseña.

   ```powershell
   $portalBackup = Join-Path $env:USERPROFILE ("xchange-backup-" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
   New-Item -ItemType Directory -Path $portalBackup -ErrorAction Stop
   & C:/xampp/mysql/bin/mariadb-dump.exe -h 127.0.0.1 -u root --single-transaction --no-tablespaces "--result-file=$portalBackup/database.sql" xchange_portal
   if ($LASTEXITCODE -ne 0) { throw 'Falló el respaldo SQL' }
   Copy-Item -LiteralPath storage/private -Destination "$portalBackup/private" -Recurse -ErrorAction Stop
   ```

3. Vuelve a iniciar Apache. El respaldo contiene cuentas y documentos. Guárdalo en una ubicación de acceso restringido.
4. Para probar la restauración, crea una base nueva con nombre único. El volcado se generó sin `--databases`, por lo que se puede cargar en esa base.

   ```powershell
   $portalRestore = "xchange_restore_" + (Get-Date -Format 'yyyyMMddHHmmss')
   & C:/xampp/mysql/bin/mysql.exe -h 127.0.0.1 -u root -e "CREATE DATABASE $portalRestore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   if ($LASTEXITCODE -ne 0) { throw 'Falló la creación de la base de prueba' }
   $portalDump = (Join-Path $portalBackup 'database.sql').Replace('\','/')
   & C:/xampp/mysql/bin/mysql.exe -h 127.0.0.1 -u root $portalRestore -e "source $portalDump"
   if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración SQL' }
   ```

5. En una copia de prueba del portal, configura `db_name` con el nombre de la base restaurada y `storage_path` con una copia de la carpeta `private` del respaldo. Inicia sesión, abre un proyecto y descarga un archivo. Compara su SHA-256 con el del original.
