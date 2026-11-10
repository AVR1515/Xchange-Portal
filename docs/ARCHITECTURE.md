# Arquitectura y decisiones

## Stack
PHP 8.2+, Apache en XAMPP, MySQL/MariaDB, PDO MySQL. HTML/CSS/JavaScript sin framework. Sin requerimiento de Composer, Node ni internet.

## Layout
```text
Xchange-Portal/
├─ app/
│  ├─ bootstrap.php         # carga config, sesión, db, helpers
│  └─ functions.php         # auth, requests, upload, policies, CSRF
├─ config/
│  └─ config.example.php
├─ database/
│  └─ schema.sql
├─ docs/
│  ├─ ROADMAP.md
│  ├─ ARCHITECTURE.md
│  ├─ XAMPP.md
│  ├─ SECURITY.md
│  └─ CHANGELOG.md
├─ public/
│  ├─ index.php             # controlador web
│  └─ assets/
│     ├─ app.css
│     └─ app.js
├─ scripts/
│  └─ seed.php
└─ storage/
   └─ private/              # fuera de public/, protegida por Apache
```

## Entidades principales
- `users`: identidades y passwords bcrypt/argon2 gestionados por PHP.
- `projects`: engagements (auditoría).
- `project_members`: acceso usuario↔proyecto, con rol `admin|auditor|client`.
- `requests`: documentos pedidos, fechas, prioridad, estado, autor y responsable.
- `documents`: metadatos y ruta interna opaca; no ruta servible por web.
- `request_comments`: comunicaciones de las partes.
- `audit_events`: trazabilidad de operaciones críticas.

## Flujo de datos y autorización
1. El navegador realiza POST a `public/index.php` con token CSRF.
2. PHP verifica sesión y pertenencia a proyecto.
3. Servidor valida input, aplica la política de transición por rol y usa PDO con placeholders.
4. En subida, `move_uploaded_file` almacena bytes bajo nombre aleatorio en `storage/private`; SQL conserva metadatos.
5. En descarga, PHP revalida sesión y membresía antes de responder con stream `Content-Disposition: attachment`.
6. Se registra cada acción relevante con actor, request y timestamp.

## Estados del documento solicitado
`open → submitted → accepted`; desde `submitted → returned → submitted`. Solo auditor/admin acepta o devuelve; cliente/auditor puede cargar evidencia según membresía, pero solo los roles autorizados cambian estado. `overdue` es atributo calculado, no estado permanente.

## Protección
- Autorización **por objeto** en todas las vistas/acciones y descargas.
- Consultas parametrizadas, escapado contextual HTML, cookie httponly y SameSite=Lax, CSRF.
- Archivo con extensión permitida, MIME verificado, tamaño y almacenamiento privado.
- No confiar en MIME del cliente, nombre, `request_id`, `project_id` o datos de sesión sin validar.
- Registro de cambios (quién, qué, cuándo), sin incluir secretos ni contenidos de archivos.

## Organización modular
La primera versión prioriza simplicidad: front controller único y helpers pequeños, preparados para extracción posterior a controladores/servicios. El navegador no requiere build y todos los assets son locales.

## Compatibilidad
La aplicación debe operar bajo `http://localhost/Xchange-Portal/public/` con URL base calculada, incluso sin mod_rewrite. En producción se recomienda establecer `public/` como único DocumentRoot accesible.
