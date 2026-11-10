'use strict';
/* Extensiones offline de la demo v2. Los registros no salen de este navegador. */
(()=>{
  const $=id=>document.getElementById(id);
  const controls=document.querySelector('#requests .toolbar');
  if(!controls)return;
  const overdue=document.createElement('select');overdue.className='field utility';overdue.id='dueFilter';
  overdue.setAttribute('aria-label','Filtrar vencimientos');
  [['all','Todas las fechas'],['overdue','Vencidas'],['week','Próximos 7 días'],['no-due','Sin fecha']].forEach(([value,label])=>{const o=document.createElement('option');o.value=value;o.textContent=label;overdue.append(o)});
  const sort=document.createElement('select');sort.className='field utility';sort.id='sortRequests';sort.setAttribute('aria-label','Ordenar solicitudes');
  [['recent','Más recientes'],['due','Vencimiento próximo'],['priority','Prioridad'],['title','Título A-Z']].forEach(([value,label])=>{const o=document.createElement('option');o.value=value;o.textContent=label;sort.append(o)});
  const exportBtn=document.createElement('button');exportBtn.className='btn secondary utility';exportBtn.type='button';exportBtn.textContent='↓ Exportar CSV';
  const reset=document.createElement('button');reset.type='button';reset.className='btn secondary utility';reset.textContent='↺ Reiniciar demo';
  controls.append(overdue,sort,exportBtn,reset);
  const start=Date.now();
  const today=new Date();today.setHours(0,0,0,0);
  function overdueFlag(r){
    if(!r.due||r.status==='accepted')return false;
    const d=new Date(r.due+'T00:00:00');return d<today;
  }
  function matchesDate(r){
    const mode=overdue.value;
    if(mode==='all')return true;
    if(mode==='no-due')return !r.due;
    if(mode==='overdue')return overdueFlag(r);
    if(mode==='week'){
      if(!r.due||r.status==='accepted')return false;
      const d=new Date(r.due+'T00:00:00');
      return d>=today&&d.getTime()<=today.getTime()+7*86400000;
    }
    return true;
  }
  const originalRender=render;
  /* El render base conserva los contadores; este segundo paso mejora la tabla visible. */
  function refresh(){
    originalRender();
    const query=$('search').value.toLocaleLowerCase().trim(),status=$('status').value,priority=$('priority').value;
    const priorities={critical:0,high:1,normal:2};
    const rows=requests.filter(r=>(status==='all'||r.status===status)&&(priority==='all'||r.priority===priority)&&(String(r.id)+' '+r.title+' '+r.assigned).toLocaleLowerCase().includes(query)&&matchesDate(r)).sort((a,b)=>{
      switch(sort.value){
        case 'due':return (a.due||'9999').localeCompare(b.due||'9999')||b.id-a.id;
        case 'priority':return priorities[a.priority]-priorities[b.priority]||b.id-a.id;
        case 'title':return a.title.localeCompare(b.title,'es');
        default:return b.id-a.id;
      }
    });
    const body=$('rows');body.replaceChildren();
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      const values=[r.id,r.title,r.assigned,statusNames[r.status],priorityNames[r.priority],r.due||'—'];
      values.forEach((value,i)=>{
        const td=document.createElement('td');
        if(i===3){const pill=document.createElement('span');pill.className='pill '+r.status;pill.textContent=value;td.append(pill)}
        else if(i===4){const span=document.createElement('span');span.className='priority '+r.priority;span.textContent=value;td.append(span)}
        else td.textContent=String(value);
        if(i===5&&overdueFlag(r)){const tag=document.createElement('span');tag.className='badge-due';tag.textContent='● Vencida';td.append(tag)}
        tr.append(td);
      });
      tr.addEventListener('click',()=>openDetail(r.id));
      tr.tabIndex=0;tr.setAttribute('aria-label','Abrir solicitud '+r.id);
      tr.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();openDetail(r.id)}});
      body.append(tr);
    });
    $('visibleCount').textContent=rows.length+' solicitudes';
    $('empty').style.display=rows.length?'none':'block';
  }
  ['search','status','priority'].forEach(id=>$(id).addEventListener(id==='search'?'input':'change',()=>queueMicrotask(refresh)));
  overdue.addEventListener('change',refresh);
  sort.addEventListener('change',refresh);
  const observer=new MutationObserver(()=>{}); // Sin polling ni conexiones.
  document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',()=>queueMicrotask(refresh)));
  ['acceptBtn','returnBtn','sendComment','fileInput'].forEach(id=>$(id).addEventListener('click',()=>setTimeout(refresh,0)));
  $('newForm').addEventListener('submit',()=>queueMicrotask(refresh));
  exportBtn.addEventListener('click',()=>{
    const headers=['ID','Título','Asignado','Estado','Prioridad','Vence'];
    const esc=value=>'"'+String(value??'').replace(/"/g,'""')+'"';
    const lines=[headers,...requests.map(r=>[r.id,r.title,r.assigned,statusNames[r.status],priorityNames[r.priority],r.due])].map(line=>line.map(esc).join(','));
    const blob=new Blob(['\uFEFF'+lines.join('\r\n')],{type:'text/csv;charset=utf-8'});
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='xchange-solicitudes-demo.csv';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  reset.addEventListener('click',()=>{
    if(!confirm('¿Restablecer todos los registros y comentarios de demostración?'))return;
    requests=structuredClone(SEED);globalEvents=['Datos de demostración restablecidos'];
    try{localStorage.removeItem(storageKey)}catch{}
    overdue.value='all';sort.value='recent';$('search').value='';$('status').value='all';$('priority').value='all';
    closeDetail();refresh();renderEvents();
  });
  const home=document.querySelector('.brand');
  if(home)home.title='Xchange Portal — demo local sin conexión';
  refresh();
})();