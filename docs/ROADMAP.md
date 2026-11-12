# Roadmap vivo · Xchange Portal

**Actualización:** 2026-11-12

**Repositorio:** https://github.com/AVR1515/Xchange-Portal  
**Branch única de trabajo:** `main`. Prohibido crear ramas alternativas. Todos los commits van directamente a main.  
**Seguimiento:** https://github.com/AVR1515/Xchange-Portal/issues

## Principios
1. La compañía gestiona varios proyectos con sus clientes. La prioridad es solicitar, recibir y revisar documentos dentro de cada proyecto.
2. Cada milestone tiene **criterios verificables**, pruebas y documentación.
3. Estado `[ ]` significa pendiente; `[x]` significa funcionalidad implementada y verificada. No confundir código de ejemplo con entrega terminada.
4. Sin frameworks CSS ni CDNs ni dependencias de internet durante la ejecución.
5. Compatible con Apache/PHP/MariaDB de XAMPP.
6. Evitar copiar marcas, logos o recursos propietarios de EY; usar UX como referencia.

## Etapas y dependencias

| Fase | Prioridad | Entregable | Issues | Estado |
|---|---|---|---|---|
| 0 | P0 | Instalación local, SQL, sesiones, roles | #1 #2 | 🟡 Esquema importado y acceso al tablero con tres roles verificado en XAMPP |
| 1A | P0 | Crear/listar/filtrar solicitudes, detalle | #3 | 🟡 Creación, edición y filtros verificados. Paginación con volumen por validar |
| 1B | P0 | Subir, descargar y compartir documentos protegidos | #4 | 🟡 Carga y descarga de TXT y aislamiento verificados. Más formatos y tamaños por validar |
| 1C | P0 | Workflow de revisión, comentarios, historia | #5 #6 | 🟡 Devolución, reenvío, aceptación e historial verificados. Concurrencia por validar |
| 2 | P0 | Administrar proyectos, clientes y miembros | #8 | 🟡 Creación, edición, alta de miembros y cambio de rol verificados |
| 2 | P1 | UI dashboard, métricas, CSV | #7 #9 | 🟡 CSV filtrado y permisos verificados. Métricas y experiencia móvil por validar |
| 3 | P2 | Versiones, avisos y QA integral | #10 #11 | 🟡 Versiones por nombre y avisos de actividad verificados. Respaldo y restauración por validar |

## Actualización visual y controles de demo
- Diseño oscuro de bajo contraste cromático aplicado a demo y frontend PHP mediante hojas CSS locales.
- Incorporados en la demo filtros por vencimiento, ordenamiento, exportación CSV y restauración.
- Fuentes: pila local con preferencia por Poppins instalada, no se descargan ni copian archivos de fuentes.
- Las pruebas del backend PHP y la integración de estos controles con SQL siguen pendientes.

## Demo sin XAMPP
- Vista preliminar interactiva publicada en [`demo/index.html`](../demo/index.html), independiente de PHP y SQL.
- La demo permite previsualizar y simular interacciones sin prometer almacenamiento real de documentos.
- Pruebas UI realizadas en Chromium local sobre listado, filtro, drawer y creación.
- Esta demo no equivale a cerrar issues de backend; la validación de XAMPP, SQL y políticas de seguridad sigue abierta.

## Corte de implementación 2026-11-10
- Ya presentes en `main`: estructura SQL, acceso, membresía por proyecto, solicitudes nuevas, subida/descarga privada, comentarios, eventos, aceptación y devolución, CSS responsive y filtro de texto local.
- **No completado:** CRUD pleno (editar/eliminar), administración desde UI, filtros avanzados/paginación, versionado y notificaciones.
- **No verificado:** ejecución PHP en Windows/XAMPP y pruebas de seguridad end-to-end. Issues abiertas hasta verificar aceptación.

## Definition of Done
- Código en `main`, sin ramas ni PR.
- Comprobado en entorno XAMPP con base de datos fresca.
- Privilegios verificados para el equipo de la compañía, el cliente y los administradores.
- SQL con PDO preparado, CSRF en escrituras y escaping HTML en vistas.
- Sin llamadas externas; recursos CSS/JS servidos localmente.
- Funcionalidad y cambios registrados en `docs/CHANGELOG.md`.
- Issue cerrada solo si satisface sus criterios de aceptación y hay evidencia de prueba.

## Casos de uso de aceptación del MVP
1. Un administrador incorpora al equipo de la compañía y al cliente a un proyecto.
2. El equipo de la compañía solicita un documento con título, descripción, prioridad y fecha de vencimiento.
3. Cliente inicia sesión y ve únicamente solicitudes de proyectos a los que pertenece.
4. Cliente sube evidencia: nombre visible, MIME/tamaño validados, bytes en ubicación privada.
5. El equipo de la compañía descarga los documentos tras validar permisos y la acepta o devuelve con comentario.
6. Cliente corrige o reenvía; historia y timestamps preservados.
7. Usuario de otro proyecto no puede leer ni descargar evidencia ajena.
8. Request overdue aparece automáticamente si venció y no fue aceptado.

## Roadmap de UI
- Proyectos: creación, acceso por membresía y navegación entre proyectos de distintos clientes.
- Dashboard: contador total, received/outstanding, accepted, overdue, próximas fechas, prioridades.
- Requests: barra de búsqueda, filtros, estado y fechas, tabla con paginación.
- Drawer detalle: descripción, asignados, vencimiento, archivos, comentarios, historial y acciones.
- Equipo: directorio, pertenencia por grupos/proyectos, permisos.

## Orden recomendado de ejecución
`#2 → #1 → #3 → #4 → #5 → #6 → #8 → #7 → #9 → #10 → #11`.

## Riesgos y decisiones pendientes
- Límite por archivo por defecto 20 MB; ajustar `upload_max_filesize` y `post_max_size` en XAMPP.
- Almacenamiento privado nunca directamente accesible vía URL.
- Sin antivirus por defecto: se requieren controles complementarios en producción real.
- Cookies `secure` solo con HTTPS; en `localhost` HTTP se ajusta por entorno.
- Para despliegue real, HTTPS, backups, retención y cumplimiento son requisitos operativos.

## Cómo mantenerlo vivo
En cada entrega: actualizar tabla de estados, describir pruebas efectuadas en `docs/CHANGELOG.md` y cerrar la issue correspondiente. Para visualizar progreso real, consultar issues abiertas/cerradas, no fechas estimadas.
