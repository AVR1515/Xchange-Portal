# Xchange Portal

Xchange Portal es una plataforma de colaboración entre una compañía y sus clientes. La compañía puede gestionar varios proyectos y centralizar en cada uno las solicitudes de documentos, las entregas del cliente y su revisión.

EY Canvas Client Portal es una referencia para el flujo de solicitudes e intercambio de documentos. Xchange Portal es un proyecto independiente, sin afiliación con EY.

## Objetivo
El equipo de la compañía crea una solicitud en un proyecto, el cliente adjunta los documentos y el equipo los acepta o devuelve para corrección. El portal conserva los comentarios y el historial de cada solicitud.

## Stack y restricciones
- PHP 8.2+ sin framework; MySQL/MariaDB compatible con XAMPP; PDO y consultas preparadas.
- Apache mod_rewrite opcional; rutas PHP compatibles con instalación `http://localhost/Xchange-Portal/public/`.
- HTML semántico, CSS nativo (variables, grid, flex, responsive), JS vanilla; librerías JS solo incluidas en `public/assets/vendor/`, jamás CDN.
- Sin servicios cloud, llamadas de terceros, trackers o recursos externos en runtime.
- Almacén privado de documentos fuera de `public/`; descarga controlada por PHP y permisos.
- Zona horaria y fechas configurables; UTF-8; UI inicialmente en español.

## Demo visual sin XAMPP

**[Abrir demo HTML interactiva](demo/index.html)** — se puede descargar y abrir con doble clic, sin PHP, SQL ni conexión a Internet. Implementa una vista funcional en el navegador con dashboard de solicitudes, búsqueda y filtros, detalle lateral, comentarios, vista de equipo, actividad, creación de solicitudes y simulación de adjuntos/revisión.

**Importante:** la demo utiliza datos ilustrativos y guarda cambios de solicitudes mediante `localStorage`. Los documentos simulados **no se suben**: solo se almacena el nombre del archivo; para almacenamiento seguro real, autenticación y descarga autorizada se necesita el backend PHP/SQL incluido en `public/`. La demo **no equivale a una implementación de seguridad** y no debe recibir datos confidenciales.

**Capturas de avance:** se generaron tres capturas de navegador (dashboard, solicitud y formulario de nueva solicitud) para la revisión visual en la conversación.

## Diseño visual actualizado (referencia PlannerDome)

- Modo oscuro más profundo, superficies en gris azulado casi negro y **un solo acento azul**; sin gradientes llamativos.
- Tipografía geométrica: se prioriza **Poppins si está instalada localmente** y se usan alternativas locales (Century Gothic, Avenir Next, Lato, Trebuchet MS y system-ui). **No se descargan ni distribuyen fuentes de terceros**; la captura no contiene los archivos originales de la tipografía y no se puede garantizar una copia exacta.
- `demo/theme.css` aplica el diseño a la demo HTML. `public/assets/theme.css` aplica la misma dirección visual al PHP.
- `demo/enhancements.js` añade filtro por vencimiento, orden por fecha/prioridad/título, exportación CSV y reinicio de registros de demostración.
- Las tareas de la demo **no se sincronizan** con SQL, GitHub ni otros navegadores.

## Demostración visual sin XAMPP

**[Abrir la demostración de Document Requests](demo/index.html)**. Este archivo HTML independiente contiene CSS y JS locales y se puede abrir desde el disco sin configurar PHP, Apache o SQL. Incluye navegación por solicitudes/equipo/actividad, búsqueda, filtros, alta de solicitudes, panel de detalle, archivos simulados, comentarios y revisión. Los datos son ilustrativos y las acciones de la demo se guardan en `localStorage` de tu navegador. **No almacena el contenido de archivos ni sustituye al backend seguro en PHP.**

Se verificó en Chromium la apertura del tablero, la búsqueda, el panel lateral y la creación de solicitudes de la demo.

## Roadmap interactivo (HTML, CSS y JS)

**[Abrir el roadmap interactivo](docs/roadmap.html)** — panel visual con fases, progreso, filtros, buscador, detalle y anotaciones de las 11 issues. Se ejecuta directamente desde disco en un navegador o en XAMPP, sin CDN ni recursos externos.

En XAMPP: **`http://localhost/Xchange-Portal/docs/roadmap.html`**. Los estados y notas modificados en el dashboard se guardan en `localStorage` **solo en ese navegador**; no se sincronizan con la base SQL ni con GitHub. Usar «Exportar JSON» para respaldo y «Importar» para recuperar seguimiento. Las GitHub Issues siguen siendo el registro colaborativo oficial. La edición local no crea commits ni cierra issues.

Archivos: [`docs/roadmap.html`](docs/roadmap.html), [`docs/roadmap.css`](docs/roadmap.css), [`docs/roadmap.js`](docs/roadmap.js).

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
Las **GitHub Issues** actúan como unidades de trabajo y fuente del progreso real. Marcar una issue como cerrada solo después de cumplir sus criterios de aceptación. El roadmap separa explícitamente código implementado de pruebas aún no ejecutadas.

## Referencia visual
Capturas aportadas por el solicitante: dashboard, request list, detalle, team members y menú. Se adopta una experiencia comparable, no el logo, el branding ni assets propietarios de EY.

## Estado
**MVP en validación en XAMPP.** Incluye acceso, administración de proyectos y miembros, solicitudes, archivos privados, comentarios, revisión, historial, avisos y exportación CSV.

### Probar localmente
1. Importar `database/schema.sql` en MariaDB/MySQL de XAMPP.
2. Copiar `config/config.example.php` a `config/config.php` (ignorado por Git), y ajustar el acceso SQL.
3. Ejecutar `C:\\xampp\\php\\php.exe scripts\\seed.php` desde la raíz. El comando genera una contraseña temporal aleatoria y muestra las 3 cuentas de demo.
4. Abrir `http://localhost/Xchange-Portal/`. Apache redirige a `public/`, que también se puede abrir directamente.
5. Seguir `docs/XAMPP.md`, `docs/SECURITY.md` y `docs/ROADMAP.md`.

**Pendiente de validación:** más formatos y tamaños de archivos, revisiones concurrentes, experiencia móvil y restauración de respaldos. Los documentos se agrupan como versiones por nombre dentro de una misma solicitud.
