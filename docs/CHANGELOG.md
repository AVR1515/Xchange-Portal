# Changelog

## 2026-11-13 — Paleta cálida y navegación del detalle
- El portal usa una paleta ciruela, terracota y crema sobre la estructura de proyectos, solicitudes y detalle lateral.
- El detalle mantiene el foco dentro del diálogo y lo devuelve a la solicitud al cerrar. El listado ignora valores de filtro que no sean texto.
- Verificados en XAMPP los accesos a las vistas, la entrega, la descarga, los comentarios, la aceptación y el CSV. En Chrome se comprobaron las pestañas, Escape y retorno del foco a 360, 768 y 1440 px.

## 2026-11-13 — Actividad en el listado
- Cada fila muestra sus conteos de documentos y comentarios con acceso directo a la sección correspondiente. El panel resalta la solicitud abierta y conserva los filtros al pasar de una página a otra.

## 2026-11-13 — Estructura del espacio de trabajo basada en Canvas
- Barra superior, navegación compacta, directorio de proyectos con resumen y gráfico de vencimientos de 30 días.
- El detalle se abre sobre el listado, con secciones para documentos, comentarios e historial y navegación entre solicitudes del resultado actual.

## 2026-11-12 — Pantalla de errores del portal
- Los fallos inesperados devuelven una pantalla del portal con un código de incidente. El tipo de excepción y su ubicación se registran en el servidor.

## 2026-11-12 — Confirmaciones y contexto de solicitudes
- El detalle conserva los filtros y la página del listado. Las operaciones muestran una confirmación después de guardar.
- El selector de adjuntos muestra la cantidad seleccionada y valida el tamaño por archivo antes de enviar.

## 2026-11-12 — Inicio de proyectos y tema oscuro
- El portal abre en Mis proyectos con tarjetas, avance documental e indicadores calculados sobre los proyectos del usuario.
- El tema usa CSS nativo y una paleta azul oscura. Se verificó el inicio en Chrome a 360, 768 y 1440 px.

## 2026-11-12 — Entrega de varios archivos
- Una solicitud admite hasta diez archivos por envío, sujeto a los límites de XAMPP. Si uno falla, se rechaza la entrega completa.
- Se verificaron la carga de dos adjuntos, el rechazo de un envío mixto sin guardar archivos parciales y el flujo de devolución y reenvío.

## 2026-11-12 — Vista rápida de solicitudes
- El listado abre un panel lateral con estado, responsable, fecha límite y acceso al detalle. Verificados Escape y retorno del foco en Chrome a 360 y 1280 px.

## 2026-11-12 — Rediseño del espacio de trabajo
- El listado de solicitudes pasa a ser la vista principal, con indicadores que abren sus filtros, selector de proyecto, navegación por secciones y acceso directo a crear una solicitud.
- Añadidos el filtro Asignadas a mí y el orden por fecha límite. Ambos se aplican al CSV.
- Verificados el listado y el detalle en Chrome a 360, 768 y 1280 px, el enlace para saltar al contenido y el flujo documental en XAMPP.

## 2026-11-12 — Verificación de comentarios e historial
- En XAMPP se comprobó que las etiquetas HTML de un comentario se muestran como texto y que el historial registra las cargas y los cambios de estado.

## 2026-11-12 — Cierre de entregas aceptadas
- Una solicitud aceptada conserva sus documentos y deja de admitir nuevas cargas. Se verificó en XAMPP que el cliente puede descargar la versión revisada.

## 2026-11-12 — Navegación del portal y formatos Office
- Añadido acceso directo a Mi cuenta y un enlace para saltar al contenido con el teclado. Nueva solicitud aparece para los roles autorizados.
- Se verificó en XAMPP la carga y descarga de muestras DOCX y XLSX.

## 2026-11-12 — Cancelación de solicitudes
- El equipo de la compañía puede cancelar solicitudes activas con un motivo. El historial y los documentos existentes siguen disponibles y la solicitud queda fuera de los vencimientos activos.
- Añadida una migración para instalaciones existentes. En XAMPP se verificaron la cancelación, la consulta de adjuntos anteriores y el bloqueo de nuevas cargas.

## 2026-11-12 — Filtros en pantallas pequeñas
- Añadido un enlace para limpiar filtros y retirado el filtro antiguo del navegador. Chrome verificó el tablero autenticado a 360, 768 y 1280 píxeles sin desplazamiento horizontal de página.

## 2026-11-12 — Distribución de estados
- El tablero muestra la distribución de solicitudes por estado y permite abrir cada filtro desde la métrica.
- Se verificó la carga y descarga de PNG y el rechazo de una descarga cuyo SHA-256 difiere del registrado.

## 2026-11-12 — Cambio de contraseña
- Cada usuario puede cambiar su contraseña desde el portal. Se comprueba la contraseña actual y se invalidan las sesiones anteriores.

## 2026-11-12 — Retiro de miembros
- Un administrador puede retirar a un cliente o integrante del equipo de un proyecto. El portal revoca su acceso, libera sus asignaciones y registra el cambio en el historial.

## 2026-11-12 — Historial de administración y revisión concurrente
- Los administradores pueden consultar los cambios del proyecto y de sus miembros en el portal.
- Se verificó en XAMPP que, con dos sesiones de revisión sobre la misma solicitud, solo una registra el cambio de estado. También se verificó el rechazo de un POST sin token CSRF.

## 2026-11-12 — Filtros de vencimiento y carga de archivos
- El listado y el CSV pueden filtrarse por vencidas, próximas a vencer y sin fecha. Los controles se reorganizan según el ancho disponible.
- Las entregas que exceden el límite de la petición reciben un mensaje de tamaño. Se verificaron descarga íntegra de PDF y rechazo de MIME incorrecto y de archivos de 21 MB.

## 2026-11-12 — Motivos de devolución y recuperación
- La devolución exige un motivo. El portal guarda el comentario, el estado y el historial en la misma transacción.
- Documentado el respaldo de MariaDB y archivos privados. Se restauró el volcado en una base aislada y se verificó el SHA-256 de dos archivos.

## 2026-11-12 — Vencimientos y métricas
- El tablero cuenta las solicitudes aceptadas y muestra las vencidas o con fecha límite en los próximos siete días.

## 2026-11-12 — Roles, avisos y versiones
- Los administradores pueden cambiar entre cliente y equipo de la compañía el rol de un miembro de su proyecto.
- El tablero muestra avisos sobre la actividad de otros miembros. Los adjuntos con el mismo nombre en una solicitud se muestran como versiones independientes y las descargas comprueban su SHA-256.

## 2026-11-12 — Edición, filtros y exportación de solicitudes
- El equipo de la compañía puede editar solicitudes. El tablero permite filtrar por texto, estado y prioridad, navegar por páginas y descargar un CSV del proyecto.
- El cambio de estado y su historial se guardan juntos. La revisión comprueba el estado vigente antes de actualizarlo.

## 2026-11-12 — Administración de proyectos y miembros
- Los administradores pueden crear y editar proyectos, asociar cuentas existentes y crear cuentas de cliente o de equipo desde el portal. Cada usuario navega por los proyectos en los que tiene membresía.
- Verificado en XAMPP el flujo de creación de proyecto y solicitud, carga y descarga íntegra, devolución, reenvío, aceptación, comentarios, cierre de sesión y aislamiento entre proyectos.

## 2026-11-12 — Roadmap de implementación actualizado
- Registradas las verificaciones de XAMPP en el tablero interactivo y priorizada la administración de proyectos, clientes y miembros para el MVP.

## 2026-11-12 — Carga del tablero al iniciar sesión
- Corregida la consulta de solicitudes para mostrar el responsable cuando está asignado y conservar las solicitudes sin asignar.

## 2026-11-12 — Colaboración por proyecto entre compañía y cliente
- Actualizado el alcance del producto, la interfaz y el roadmap para centrar el portal en la gestión de solicitudes y documentos por proyecto. La demo muestra un proyecto de auditoría como ejemplo.

## 2026-11-12 — Acceso desde la raíz en XAMPP
- Apache redirige la URL base a `public/`, desactiva el listado de carpetas y deniega el acceso web a directorios internos.

## 2026-11-10 — Demo visual interactiva sin XAMPP (main)
- Incorporado `demo/index.html`: prototipo HTML independiente, CSS y JS incluidos localmente, con siete solicitudes ilustrativas, métricas, filtros, detalle, comentarios, actividad, equipos y creación de requests en memoria del navegador.
- Simulación de adjuntos: se conserva únicamente el nombre del archivo, **no sus bytes**; no usar para documentos sensibles.
- Capturas reales realizadas en Chromium: dashboard, drawer de solicitud y nueva solicitud; compartidas como archivos en la conversación.
- Pruebas en navegador local con Playwright: listado de 7 registros, búsqueda reducida a 1, apertura de detalle y creación aumentando el total a 8 (**pasaron**).
- Esta demo no utiliza PHP/SQL; la integración y las pruebas completas con XAMPP siguen pendientes.

## 2026-11-10 — Ajuste dark mode y controles de solicitudes (main)
- Rediseño inspiracional según nueva captura PlannerDome: colores desaturados, fondos casi negros, azul como acento y tipografía geométrica con fallbacks locales.
- Añadidos `demo/theme.css` y `public/assets/theme.css`; PHP carga su hoja adicional.
- Añadido `demo/enhancements.js` con filtros de vencimientos, ordenamiento, CSV local y botón de restablecimiento; `demo/index.html` carga ambos archivos nuevos.
- Las capturas de revisión se produjeron en Chromium con el tema actualizado. La previsualización visual de capturas no sustituye pruebas E2E de las nuevas acciones JS.
- No se copió ni redistribuyó ningún archivo de fuente del ejemplo; se usan fuentes instaladas localmente.
- Riesgos pendientes: QA de flujo completo PHP/XAMPP, permisos y persistencia SQL.

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
