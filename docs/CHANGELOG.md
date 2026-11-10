# Changelog

## 2026-11-10 — Demo visual interactiva sin XAMPP (main)
- Incorporado `demo/index.html`: prototipo HTML independiente, CSS y JS incluidos localmente, con siete solicitudes ilustrativas, métricas, filtros, detalle, comentarios, actividad, equipos y creación de requests en memoria del navegador.
- Simulación de adjuntos: se conserva únicamente el nombre del archivo, **no sus bytes**; no usar para documentos sensibles.
- Capturas reales realizadas en Chromium: dashboard, drawer de solicitud y nueva solicitud; compartidas como archivos en la conversación.
- Pruebas en navegador local con Playwright: listado de 7 registros, búsqueda reducida a 1, apertura de detalle y creación aumentando el total a 8 (**pasaron**).
- Esta demo no utiliza PHP/SQL; la integración y las pruebas completas con XAMPP siguen pendientes.

## 2026-11-10 — Demo navegable de intercambio documental (main)
- Publicado `demo/index.html` (HTML+CSS+JS incluidos localmente) para evaluar la interfaz sin instalar XAMPP.
- Vista de solicitudes, resumen, filtros, detalle lateral, crear solicitud, comentar, carga de archivos **solo simulada por nombre**, aceptar/devolver y vista de miembros/actividad.
- Los datos de muestra y modificaciones de la demo permanecen únicamente en `localStorage` del navegador. Ningún archivo es enviado a un servidor ni conservado como documento descargable.
- Capturas obtenidas mediante Chromium local. **Pruebas UI aprobadas**: listado de 7 registros, filtro de búsqueda, apertura de detalle y creación de una nueva solicitud (8 registros).
- **No probado**: almacenamiento real, permisos por usuario, conexión a SQL o funcionamiento de PHP en XAMPP.

## 2026-11-10 — Roadmap interactivo (main)
- Incorporado `docs/roadmap.html` con una vista interactiva de fases, tarjetas, drawer, indicadores y progreso.
- Estilos CSS nativos en `docs/roadmap.css`; lógica local en `docs/roadmap.js` sin dependencias externas.
- Soporta búsqueda, filtro por fase/estado/prioridad, edición de notas y estados, localStorage, exportación/importación JSON y reinicio.
- Estados iniciales son informativos: implementación parcial en progreso; completadas 0 hasta probar en XAMPP.
- **Alcance:** el estado interactivo es local al navegador; no sincroniza automáticamente con GitHub Issues, SQL ni otros usuarios.
- **Pruebas:** código publicado en `main`; validación visual/manual en navegador y XAMPP todavía pendiente.

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
