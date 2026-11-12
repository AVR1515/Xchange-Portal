'use strict';

/**
 * Xchange Portal – Roadmap Vivo
 * Sin framework, CDN ni conexión externa para funcionar.
 * Los estados iniciales reflejan el avance publicado del proyecto.
 * LocalStorage contiene datos de seguimiento del navegador solamente.
 * GitHub issues mantienen el seguimiento oficial del proyecto.
 */
const TASKS = [
 {id:2,phase:0,priority:'P0',status:'progress',icon:'▦',title:'Modelo SQL y estructura de datos',summary:'Usuarios, proyectos, membresías, solicitudes, documentos y eventos de auditoría.',criteria:['Importar database/schema.sql con XAMPP','Comprobar claves foráneas e índices','Documentar migraciones y datos de prueba','Verificar aislamiento entre proyectos']},
 {id:1,phase:0,priority:'P0',status:'progress',icon:'⛨',title:'Autenticación, sesiones y permisos',summary:'Inicio de sesión, roles de equipo de la compañía, cliente y administrador, CSRF y autorización por proyecto.',criteria:['Login/logout y sesiones en XAMPP','Probar roles por proyecto','Negar acceso a solicitudes ajenas','Validar CSRF y seguridad de sesiones']},
 {id:3,phase:1,priority:'P0',status:'progress',icon:'☷',title:'Gestión de solicitudes documentales',summary:'Crear y listar solicitudes, fechas, prioridad, asignación, detalle y búsqueda.',criteria:['Crear una solicitud como miembro del equipo de la compañía','Cliente ve solicitudes autorizadas','Editar solicitudes y filtros avanzados','Paginación y búsqueda verificadas']},
 {id:4,phase:1,priority:'P0',status:'progress',icon:'⇧',title:'Carga y descarga segura de documentos',summary:'Archivos locales con validación de tamaño y tipo, almacenamiento privado y permisos.',criteria:['Carga y descarga íntegra en XAMPP','Verificar MIME, extensiones y límites','Bloquear rutas directas a storage','Denegar descarga entre proyectos']},
 {id:5,phase:1,priority:'P0',status:'progress',icon:'✓',title:'Flujo de revisión y aceptación',summary:'Enviar, aceptar, devolver y reenviar evidencia con control de roles.',criteria:['Ejecutar open → submitted → accepted','Ejecutar submitted → returned → submitted','Prohibir aceptación por clientes','Verificar transiciones concurrentes']},
 {id:6,phase:1,priority:'P0',status:'progress',icon:'◷',title:'Comentarios e historial de auditoría',summary:'Mensajes vinculados a solicitudes, eventos y trazabilidad de acciones.',criteria:['Publicar y mostrar comentarios','Historial de cambios por usuario','Escapar texto potencialmente malicioso','Auditar acciones sensibles']},
 {id:7,phase:2,priority:'P1',status:'progress',icon:'◫',title:'Interfaz moderna y responsive',summary:'Dashboard, tabla de solicitudes, navegación y detalle inspirados funcionalmente en las referencias.',criteria:['Interfaz responsive en CSS nativo','Construir drawer de detalle','Teclado, foco y estados vacíos','Validar experiencia en móvil y escritorio']},
 {id:8,phase:2,priority:'P0',status:'pending',icon:'♙',title:'Gestión de proyectos, clientes y miembros',summary:'Administrar varios proyectos desde el portal y controlar quién puede participar en cada uno.',criteria:['Crear y editar proyectos desde la interfaz','Crear o invitar usuarios del cliente y de la compañía','Asignar miembros y roles por proyecto','Navegar entre proyectos con acceso autorizado','Verificar aislamiento con dos proyectos y clientes distintos']},
 {id:9,phase:2,priority:'P1',status:'pending',icon:'▥',title:'Dashboard, métricas y exportaciones',summary:'Indicadores calculados desde SQL, vencimientos, gráficos locales y CSV.',criteria:['Comprobar métricas contra SQL','Gráficos sin conexión a Internet','Filtros de dashboard','Exportaciones CSV autorizadas']},
 {id:10,phase:3,priority:'P2',status:'pending',icon:'▤',title:'Versionado y notificaciones internas',summary:'Versiones de documentos, hashes, alertas locales de cambios y fechas.',criteria:['Preservar versiones anteriores','Descargar versiones con autorización','Crear avisos internos','Verificar integridad por SHA-256']},
 {id:11,phase:3,priority:'P2',status:'pending',icon:'⚙',title:'Pruebas, seguridad y entrega XAMPP',summary:'QA funcional, documentación y validación de seguridad antes de producción.',criteria:['Ejecución reproducible en Windows XAMPP','Pruebas de autorización y subida','Checklist de seguridad documentado','Backups y restauración probados']}
];
const VERIFIED = {
 1: 'Las tres cuentas demo iniciaron sesión y abrieron el tablero en XAMPP. Queda probar el cierre de sesión, el aislamiento por proyecto y CSRF.',
 2: 'El esquema se importó en MariaDB y se creó un proyecto demo con tres miembros. Queda verificar claves, índices y aislamiento con varios proyectos.',
 3: 'El tablero de solicitudes abrió con los tres roles tras corregir la consulta del responsable. Queda probar creación, edición, asignación y filtros.',
 4: 'Apache denegó el acceso directo al directorio de almacenamiento. Queda probar el ciclo de carga y descarga con permisos, tipos y tamaños reales.',
 11: 'La URL base redirige al formulario de acceso y sus recursos cargan en XAMPP. Queda ejecutar la validación integral y comprobar la restauración de datos.'
};
const PHASES = [
 {id:0,name:'Fundamentos',subtitle:'Arquitectura y seguridad',label:'FASE 00'},
 {id:1,name:'Core documental',subtitle:'Solicitudes y archivos',label:'FASE 01'},
 {id:2,name:'Experiencia y equipo',subtitle:'Interfaz y reportes',label:'FASE 02'},
 {id:3,name:'Calidad y entrega',subtitle:'Validación y mejoras',label:'FASE 03'}
];
const STATES = {pending:'Pendiente',progress:'En progreso',done:'Completada',blocked:'Bloqueada'};
const KEY='xchange-roadmap-state-v1';
const $=id=>document.getElementById(id);
let userState={};
try {
 const stored=JSON.parse(localStorage.getItem(KEY)||'{}');
 if(stored && typeof stored==='object' && !Array.isArray(stored))userState=stored;
} catch { userState={}; }
let activePhase='all', selectedId=null, priorFocus=null;
const state=id=>Object.assign({},TASKS.find(t=>t.id===id),userState[id]||{});
const save=()=>{try{localStorage.setItem(KEY,JSON.stringify(userState));return true}catch{return false}};
const cleanText=x=>typeof x==='string'?x.slice(0,6000):'';
const card=(t)=>{
 const a=document.createElement('article');a.className='task-card';a.tabIndex=0;a.setAttribute('role','button');a.setAttribute('aria-label','Abrir tarea '+t.id+': '+t.title);
 const icon=document.createElement('div');icon.className='task-icon';icon.textContent=t.icon;
 const main=document.createElement('div');main.className='task-main';
 const h=document.createElement('h3');h.textContent=t.title;
 const p=document.createElement('p');p.textContent=t.summary;
 const tags=document.createElement('div');tags.className='task-tags';
 const pri=document.createElement('span');pri.className='priority-chip '+t.priority.toLowerCase();pri.textContent=t.priority;
 const ph=document.createElement('span');ph.className='phase-chip';ph.textContent=PHASES[t.phase].label;
 tags.append(pri,ph);main.append(h,p,tags);
 const right=document.createElement('div');right.className='task-right';
 const status=document.createElement('span');status.className='status-chip '+t.status;status.textContent=STATES[t.status];
 const number=document.createElement('small');number.textContent='Issue #'+t.id;
 const arrow=document.createElement('span');arrow.className='arrow';arrow.textContent='›';arrow.setAttribute('aria-hidden','true');
 right.append(status,arrow,number);a.append(icon,main,right);
 a.addEventListener('click',()=>openTask(t.id));a.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();openTask(t.id)}});
 return a;
};
function renderPhases(){
 const root=$('phase-strip');root.replaceChildren();
 for(const ph of PHASES){
  const rows=TASKS.filter(t=>t.phase===ph.id).map(t=>state(t.id));
  const done=rows.filter(t=>t.status==='done').length, percent=Math.round(100*done/rows.length);
  const btn=document.createElement('button');btn.className='phase'+(activePhase===ph.id?' selected':'');btn.type='button';btn.setAttribute('aria-pressed',String(activePhase===ph.id));btn.setAttribute('aria-label',ph.name+'; '+done+' de '+rows.length+' completadas. Filtrar fase.');
  const top=document.createElement('div');top.className='phase-top';const label=document.createElement('span');label.textContent=ph.label;const pct=document.createElement('span');pct.textContent=percent+'%';top.append(label,pct);
  const name=document.createElement('h3');name.textContent=ph.name;
  const desc=document.createElement('p');desc.textContent=ph.subtitle;
  const track=document.createElement('div');track.className='phase-track';const fill=document.createElement('span');fill.className='phase-fill';fill.style.width=percent+'%';track.append(fill);
  const stat=document.createElement('div');stat.className='phase-stat';stat.textContent=done+' / '+rows.length+' completadas';
  btn.append(top,name,desc,track,stat);
  btn.addEventListener('click',()=>{activePhase=activePhase===ph.id?'all':ph.id;render()});
  root.append(btn);
 }
}
function render(){
 const tasks=TASKS.map(t=>state(t.id));
 const done=tasks.filter(t=>t.status==='done').length, active=tasks.filter(t=>t.status==='progress').length;
 const pct=Math.round(100*done/tasks.length);
 $('stat-total').textContent=String(tasks.length);$('stat-progress').textContent=String(active);$('stat-done').textContent=String(done);
 $('stat-p0').textContent=String(tasks.filter(t=>t.priority==='P0').length);
 $('progress-value').textContent=pct+'%';$('progress-ring').style.background='conic-gradient(var(--teal) '+pct+'%, #34516a '+pct+'%)';
 $('progress-ring').setAttribute('aria-label','Progreso de tareas completadas: '+pct+' por ciento');
 $('progress-caption').textContent=done+' de '+tasks.length+' tareas completadas';
 renderPhases();
 const query=$('search').value.toLocaleLowerCase().trim(),status=$('status-filter').value,priority=$('priority-filter').value;
 const shown=tasks.filter(t=>(activePhase==='all'||t.phase===activePhase)&&(status==='all'||t.status===status)&&(priority==='all'||t.priority===priority)&&(t.title+' '+t.summary+' '+t.criteria.join(' ')).toLocaleLowerCase().includes(query));
 $('result-count').textContent=shown.length+' '+(shown.length===1?'tarea':'tareas');
 const list=$('task-list');list.replaceChildren(...shown.map(card));$('empty-state').hidden=shown.length>0;
}
function openTask(id){
 selectedId=id;const t=state(id);priorFocus=document.activeElement;
 $('drawer-phase').textContent=PHASES[t.phase].label+' / ISSUE #'+t.id;
 $('drawer-title').textContent=t.title;$('drawer-summary').textContent=t.summary;
 $('drawer-verification').hidden=!VERIFIED[id];
 $('drawer-evidence').textContent=VERIFIED[id]||'';
 const pr=$('drawer-priority');pr.className='priority-chip '+t.priority.toLowerCase();pr.textContent=t.priority;
 const st=$('drawer-status-label');st.className='status-chip '+t.status;st.textContent=STATES[t.status];
 $('drawer-status').value=t.status;$('drawer-notes').value=cleanText(t.notes||'');
 const url='https://github.com/AVR1515/Xchange-Portal/issues/'+t.id;
 $('drawer-issue').href=url;
 const list=$('drawer-checklist');list.replaceChildren();
 t.criteria.forEach(item=>{const li=document.createElement('li');li.textContent=item;list.append(li)});
 $('save-feedback').textContent='';
 $('drawer-backdrop').hidden=false;$('drawer').hidden=false;document.body.style.overflow='hidden';
 $('drawer-close').focus();
}
function closeTask(){
 $('drawer').hidden=true;$('drawer-backdrop').hidden=true;document.body.style.overflow='';selectedId=null;
 if(priorFocus&&typeof priorFocus.focus==='function')priorFocus.focus();
}
function saveTask(){
 if(selectedId===null)return;
 const status=$('drawer-status').value;
 if(!Object.hasOwn(STATES,status))return;
 const notes=$('drawer-notes').value.slice(0,6000);
 userState[selectedId]={status,notes};
 const stored=save();$('save-feedback').textContent=stored?'Guardado en este navegador':'No se pudo guardar localmente';
 $('drawer-status-label').className='status-chip '+status;$('drawer-status-label').textContent=STATES[status];
 render();
}
function downloadJSON(){
 const data={format:'xchange-roadmap',version:1,exportedAt:new Date().toISOString(),state:userState};
 const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
 const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='xchange-roadmap.json';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1500);
}
function importJSON(file){
 if(!file||file.size>300000){alert('Selecciona un JSON menor de 300 KB.');return}
 const reader=new FileReader();
 reader.onload=()=>{
  try{
   const data=JSON.parse(String(reader.result));
   if(data?.format!=='xchange-roadmap'||data?.version!==1||!data.state||typeof data.state!=='object'||Array.isArray(data.state))throw Error('Formato inválido');
   const clean={};
   for(const t of TASKS){
    const item=data.state[t.id];
    if(item&&typeof item==='object'&&!Array.isArray(item)){
     const status=Object.hasOwn(STATES,item.status)?item.status:t.status;
     clean[t.id]={status,notes:cleanText(item.notes||'')};
    }
   }
   userState=clean;save();render();alert('Tablero importado correctamente.');
  }catch{alert('No se pudo importar: formato de roadmap no válido.')}
 };
 reader.readAsText(file);
}
$('search').addEventListener('input',render);
$('status-filter').addEventListener('change',render);
$('priority-filter').addEventListener('change',render);
$('reset-filters').addEventListener('click',()=>{activePhase='all';$('search').value='';$('status-filter').value='all';$('priority-filter').value='all';render()});
$('drawer-close').addEventListener('click',closeTask);
$('drawer-backdrop').addEventListener('click',closeTask);
$('drawer-save').addEventListener('click',saveTask);
$('drawer-status').addEventListener('change',saveTask);
$('drawer-notes').addEventListener('input',saveTask);
$('export-btn').addEventListener('click',downloadJSON);
$('import-btn').addEventListener('click',()=>$('import-input').click());
$('import-input').addEventListener('change',e=>{importJSON(e.target.files?.[0]);e.target.value=''});
$('restore-btn').addEventListener('click',()=>{if(confirm('¿Restablecer al estado inicial? Se perderán las notas locales.')){userState={};save();render()}});
document.addEventListener('keydown',e=>{
 if(e.key==='Escape'&&!$('drawer').hidden)closeTask();
 if(e.key==='Tab'&&!$('drawer').hidden){
  const focusable=Array.from($('drawer').querySelectorAll('button,a[href],select,textarea')).filter(el=>!el.disabled&&!el.hidden);
  const first=focusable[0],last=focusable[focusable.length-1];
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
 }
});
render();
