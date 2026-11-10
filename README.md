# Xchange Portal

Plataforma de colaboración para solicitudes de auditoría e intercambio de documentos, inspirada funcionalmente en EY Canvas Client Portal. Proyecto independiente, sin afiliación con EY.

## Objetivo
**Primero entregar el flujo completo de document requests/sharing**: auditor crea solicitud → cliente recibe → adjunta archivos → auditor revisa → acepta o devuelve → auditoría queda registrada.

## Stack y restricciones
- PHP 8.2+ sin framework; MySQL/MariaDB compatible con XAMPP; PDO y consultas preparadas.
- Apache mod_rewrite opcional; rutas PHP compatibles con instalación `http://localhost/Xchange-Portal/public/`.
- HTML semántico, CSS nativo (variables, grid, flex, responsive), JS vanilla; librerías JS solo incluidas en `public/assets/vendor/`, jamás CDN.
- Sin servicios cloud, llamadas de terceros, trackers o recursos externos en runtime.
- Almacén privado de documentos fuera de `public/`; descarga controlada por PHP y permisos.
- Zona horaria y fechas configurables; UTF-8; UI inicialmente en español.

## Entregables
- [Roadmap vivo y criterios de aceptación](docs/ROADMAP.md)
- [Arquitectura y modelo de datos](docs/ARCHITECTURE.md)
- [Guía de instalación XAMPP](docs/XAMPP.md)
- [Schema SQL inicial](database/schema.sql)

## Enfoque
1. Seguridad, acceso y modelo de datos.
2. Solicitudes + documentos + comentarios + revisión (MVP prioritario).
3. Dashboard + usuarios/equipos + filtros y reportes.
4. Notificaciones locales, versiones, exportaciones, pruebas y endurecimiento.

### Seguimiento
Las **GitHub Issues** actúan como unidades de trabajo y fuente del progreso real. Marcar una issue como cerrada solo después de cumplir sus criterios de aceptación. Este README y el roadmap describen planificación, **no** funcionalidad ya implementada.

## Referencia visual
Capturas aportadas por el solicitante: dashboard, request list, detalle, team members y menú. Se adopta una experiencia comparable, no el logo, el branding ni assets propietarios de EY.

## Estado
🟡 Planificación inicial. No hay aplicación ejecutable aún.
