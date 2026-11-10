# Changelog

## 2026-11-10 — MVP inicial codificado (main)
- Implementado `public/index.php`: formulario de acceso y salida, lista de solicitudes, creación con autorización, vista de detalle, comentarios, carga/descarga de archivos, aceptación/devolución e historial.
- Añadidos `app/bootstrap.php` (sesión, PDO, escaping, CSRF y autorización), `database/schema.sql`, `scripts/seed.php` y `config/config.example.php`.
- Diseñados estilos responsive con CSS nativo y filtro JS local sin bibliotecas externas.
- Archivos guardados bajo `storage/private`; descargas mediadas por sesión y permisos.
- **Verificación:** commits confirmados en GitHub `main`; **PHP y XAMPP aún no ejecutados** en esta sesión. No cerrar issues hasta validación.
- **Pendiente:** pruebas de carga grandes, tipos MIME Office bajo Windows, aislamiento de proyectos, transición de revisión, login/logout, sesión y CSRF.

## 2026-11-10 — Inicialización (main)
- Repositorio inicializado en `main`, sin ramas adicionales.
- Creado roadmap vivo y 11 GitHub Issues con criterios de aceptación.
- Documentada arquitectura PHP/MariaDB para XAMPP, SQL y estrategia de seguridad.
- Prioridad P0 explícita: solicitudes y archivos.
- Estado de pruebas en XAMPP: **pendiente de verificación en máquina Windows con XAMPP**.

> Mantener esta bitácora por cada cambio importante: archivos, motivo, instrucciones de migración y evidencia de pruebas.
