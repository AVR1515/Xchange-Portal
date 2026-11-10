# Roadmap vivo · Xchange Portal

**Actualización:** 2026-11-10  
**Repositorio:** https://github.com/AVR1515/Xchange-Portal  
**Branch única de trabajo:** `main`. Prohibido crear ramas alternativas. Todos los commits van directamente a main.  
**Seguimiento:** https://github.com/AVR1515/Xchange-Portal/issues

## Principios
1. El intercambio de documentos es prioridad absoluta.
2. Cada milestone tiene **criterios verificables**, pruebas y documentación.
3. Estado `[ ]` significa pendiente; `[x]` significa funcionalidad implementada y verificada. No confundir código de ejemplo con entrega terminada.
4. Sin frameworks CSS ni CDNs ni dependencias de internet durante la ejecución.
5. Compatible con Apache/PHP/MariaDB de XAMPP.
6. Evitar copiar marcas, logos o recursos propietarios de EY; usar UX como referencia.

## Etapas y dependencias

| Fase | Prioridad | Entregable | Issues | Estado |
|---|---|---|---|---|
| 0 | P0 | Instalación local, SQL, sesiones, roles | #1 #2 | 🟡 En proceso |
| 1A | P0 | Crear/listar/filtrar solicitudes, detalle | #3 | ⬜ Pendiente |
| 1B | P0 | Subir, descargar y compartir documentos protegidos | #4 | ⬜ Pendiente |
| 1C | P0 | Workflow de revisión, comentarios, historia | #5 #6 | ⬜ Pendiente |
| 2 | P1 | UI dashboard, equipos, métricas, CSV | #7 #8 #9 | ⬜ Pendiente |
| 3 | P2 | Versiones, avisos y QA integral | #10 #11 | ⬜ Pendiente |

## Definition of Done
- Código en `main`, sin ramas ni PR.
- Comprobado en entorno XAMPP con base de datos fresca.
- Privilegios verificados: auditor, cliente, admin.
- SQL con PDO preparado, CSRF en escrituras y escaping HTML en vistas.
- Sin llamadas externas; recursos CSS/JS servidos localmente.
- Funcionalidad y cambios registrados en `docs/CHANGELOG.md`.
- Issue cerrada solo si satisface sus criterios de aceptación y hay evidencia de prueba.

## Casos de uso de aceptación del MVP
1. Administrador crea auditor y cliente y los incorpora a un proyecto.
2. Auditor solicita documento con título, descripción, prioridad y fecha de vencimiento.
3. Cliente inicia sesión y ve únicamente solicitudes de proyectos a los que pertenece.
4. Cliente sube evidencia: nombre visible, MIME/tamaño validados, bytes en ubicación privada.
5. Auditor descarga evidencia tras validar permisos y la acepta o devuelve con comentario.
6. Cliente corrige o reenvía; historia y timestamps preservados.
7. Usuario de otro proyecto no puede leer ni descargar evidencia ajena.
8. Request overdue aparece automáticamente si venció y no fue aceptado.

## Roadmap de UI
- Dashboard: contador total, received/outstanding, accepted, overdue, próximas fechas, prioridades.
- Requests: barra de búsqueda, filtros, estado y fechas, tabla con paginación.
- Drawer detalle: descripción, asignados, vencimiento, archivos, comentarios, historial y acciones.
- Equipo: directorio, pertenencia por grupos/proyectos, permisos.

## Orden recomendado de ejecución
`#2 → #1 → #3 → #4 → #5 → #6 → #7 → #8 → #9 → #10 → #11`.

## Riesgos y decisiones pendientes
- Límite por archivo por defecto 20 MB; ajustar `upload_max_filesize` y `post_max_size` en XAMPP.
- Almacenamiento privado nunca directamente accesible vía URL.
- Sin antivirus por defecto: se requieren controles complementarios en producción real.
- Cookies `secure` solo con HTTPS; en `localhost` HTTP se ajusta por entorno.
- Para despliegue real, HTTPS, backups, retención y cumplimiento son requisitos operativos.

## Cómo mantenerlo vivo
En cada entrega: actualizar tabla de estados, describir pruebas efectuadas en `docs/CHANGELOG.md` y cerrar la issue correspondiente. Para visualizar progreso real, consultar issues abiertas/cerradas, no fechas estimadas.
