(() => {
 'use strict';
 const root=document.querySelector('[data-analytics]'); if(!root)return;
 const screen=root.dataset.analytics, office=root.dataset.office==='1';
 const content=document.getElementById('an-content'), form=document.getElementById('an-filters');
 const money=n=>'S/ '+n.toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
 const fmt=n=>n.toLocaleString('es-PE',{maximumFractionDigits:1});
 const escape=s=>String(s??'—').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const definitions={
 clientes:['Clientes','Fecha de registro',['Código','Cliente','Documento','Distrito','Registro','Estado']],
 ventas:['Ventas','Fecha de venta',['Código','Cliente','Contrato','Fecha de venta','Monto','Estado']],
 contratos:['Contratos','Fecha de registro',['Contrato','Cliente','Plan','Registro','Precio mensual','Estado']],
 pagos:['Pagos registrados','Fecha de registro',['Código','Cliente','Contrato','Registro','Vencimiento','Monto','Estado']],
 pendientes:['Pagos pendientes','Fecha de vencimiento',['Código','Cliente','Contrato','Registro','Vencimiento','Monto','Estado']],
 vencidos:['Pagos vencidos','Fecha de vencimiento',['Código','Cliente','Contrato','Registro','Vencimiento','Monto','Estado']],
 equipos:['Equipos','Fecha de registro',['Código','Equipo','Serie','Registro','Condición','Estado']],
 materiales:['Materiales','Fecha de registro',['Código','Material','Registro','Unidad','Stock actual','Estado']],
 kardex:['Kardex','Fecha de movimiento',['Código','Artículo','Movimiento','Fecha','Cantidad','Unidad','Responsable']],
 instalaciones:['Instalaciones','Fecha de solicitud',['Código','Cliente','Técnico','Solicitud','Inicio real','Fin real','Estado']]
 };
 const base=[]; for(let m=4;m<=9;m++)for(let i=1;i<=3;i++){
  const date=`2026-${String(m).padStart(2,'0')}-${String(i*7).padStart(2,'0')}`;
  base.push({date,m,i,id:(m-4)*3+i,amount:70+i*20+(m-4)*12+(m%2)*8,clients:8+m+i,contracts:5+m+i,payments:18+m+i,installs:4+m+i,clientTime:2+i*.6,contractTime:4+i*.9,orderTime:7+i,reportTime:1+i*.3});
 }
 let state={from:'2026-04-01',to:'2026-09-30',process:'all',type:'clientes',page:1};
 const params=new URLSearchParams(location.search);
 const typeSelect=document.getElementById('an-type');
 if(typeSelect){Object.entries(definitions).filter(([key])=>!office||['clientes','ventas','contratos','pagos','pendientes','vencidos'].includes(key)).forEach(([key,d])=>typeSelect.add(new Option(d[0],key)));if([...typeSelect.options].some(o=>o.value===params.get('tipo')))typeSelect.value=params.get('tipo');}
 const processSelect=document.getElementById('an-process');if(processSelect&&[...processSelect.options].some(o=>o.value===params.get('proceso')))processSelect.value=params.get('proceso');
 for(const [key,id] of [['desde','an-from'],['hasta','an-to']]){if(/^\d{4}-\d{2}-\d{2}$/.test(params.get(key)||''))document.getElementById(id).value=params.get(key);}
 const sum=(rows,k)=>rows.reduce((s,r)=>s+r[k],0);
 const avg=(rows,k)=>rows.length?sum(rows,k)/rows.length:0;
 const showDialog=(title,text)=>{document.getElementById('an-dialog-title').textContent=title;document.getElementById('an-dialog-text').textContent=text;document.getElementById('an-dialog').showModal();};
 const stat=(name,value,note='Muestra del periodo')=>`<article class="an-card"><div class="an-card-top"><span class="an-label">${escape(name)}</span><span class="an-icon" aria-hidden="true">↗</span></div><div class="an-number">${escape(value)}</div><small>${escape(note)}</small></article>`;
 const pill=s=>`<span class="an-pill ${['PENDIENTE','PROGRAMADO','ASIGNADO','MANTENIMIENTO'].includes(s)?'warn':['VENCIDO','ANULADO'].includes(s)?'bad':''}">${escape(s)}</span>`;
 const table=(cols,rows)=>`<div class="an-table-wrap" tabindex="0" role="region" aria-label="Detalle de resultados"><table class="an-table"><thead><tr>${cols.map(c=>`<th scope="col">${escape(c)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${r.map((v,i)=>`<td>${i===r.length-1&&/^[A-ZÁ ]+$/.test(String(v))?pill(v):escape(v)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
 const empty=()=>'<div class="an-empty"><span class="an-icon" style="margin:auto" aria-hidden="true">∅</span><h2>Sin datos para este periodo</h2><p>Prueba con fechas entre abril y septiembre de 2026 para explorar la demostración.</p></div>';
 function reportRows(selected){
  return selected.filter(r=>state.type==='pendientes'?r.i===2:state.type==='vencidos'?r.i===3:true).map(r=>{
   const id=String(r.id).padStart(4,'0'), client=`Cliente de ejemplo ${r.id}`,contract=`CON-${id}`,paystate=['REALIZADO','PENDIENTE','VENCIDO'][r.i-1];
   return ({clientes:[`CLI-${id}`,client,`0000${id}`,'Trujillo',r.date,'ACTIVO'],ventas:[`VEN-${id}`,client,contract,r.date,money(r.amount),'CONFIRMADA'],contratos:[contract,client,['Fibra 100','Fibra 200','Fibra 300'][r.i-1],r.date,money(r.amount),'ACTIVO'],pagos:[`PAG-${id}`,client,contract,r.date,r.date,money(r.amount),paystate],pendientes:[`PAG-${id}`,client,contract,r.date,r.date,money(r.amount),paystate],vencidos:[`PAG-${id}`,client,contract,r.date,r.date,money(r.amount),paystate],equipos:[`EQ-${id}`,['Router','ONU','Repetidor'][r.i-1],`DEMO-${id}`,r.date,'BUENO',['DISPONIBLE','ASIGNADO','MANTENIMIENTO'][r.i-1]],materiales:[`MAT-${id}`,['Cable de fibra','Conectores','Grapas'][r.i-1],r.date,r.i===1?'m':'unid.',String(100+r.id*3),'ACTIVO'],kardex:[`MOV-${id}`,['Cable de fibra','Conectores','Grapas'][r.i-1],['ENTRADA','CONSUMO','AJUSTE'][r.i-1],r.date,String(10+r.i),r.i===1?'m':'unid.','Operador de ejemplo'],instalaciones:[`INS-${id}`,client,`Técnico de ejemplo ${r.i}`,r.date,r.i===1?r.date+' 09:00':'—',r.i===1?r.date+' 11:30':'—',['COMPLETADO','PROGRAMADO','EN_PROCESO'][r.i-1]]})[state.type];
  });
 }
 function reports(selected){
  const rows=reportRows(selected),d=definitions[state.type],pages=Math.max(1,Math.ceil(rows.length/8));state.page=Math.min(state.page,pages);
  content.innerHTML=`<div class="an-stats">${stat('Registros encontrados',rows.length,'Total de la muestra filtrada')}${stat('Tipo de reporte',d[0],'Detalle operativo')}${stat('Formatos previstos','PDF · Excel','Exportación pendiente de conexión')}${stat('Periodo aplicado',selected.length?`${state.from.slice(5)} / ${state.to.slice(5)}`:'—',d[1])}</div><section class="an-panel"><header class="an-panel-header"><div><h2>Detalle de ${d[0].toLowerCase()}</h2><p>Filtro: ${d[1].toLowerCase()} · Total: ${rows.length} registros de ejemplo</p></div><div class="an-actions"><button class="an-secondary" data-export="PDF">Descargar PDF</button><button class="an-primary" data-export="Excel">Descargar Excel</button></div></header>${rows.length?table(d[2],rows.slice((state.page-1)*8,state.page*8)):empty()}<div class="an-pagination"><span>${rows.length?`Mostrando ${(state.page-1)*8+1}–${Math.min(state.page*8,rows.length)} de ${rows.length}`:'0 registros'}</span><div><button data-page="-1" ${state.page===1?'disabled':''}>Anterior</button><span>${state.page} / ${pages}</span><button data-page="1" ${state.page===pages?'disabled':''}>Siguiente</button></div></div><p class="an-footer-note">Fecha de generación de la vista: ${new Date().toLocaleString('es-PE')} · Datos ficticios; no se genera un archivo en esta etapa.</p></section>`;
 }
 function metrics(rows){const count=rows.length;return [
  ['01','Tiempo de registro de clientes',fmt(avg(rows,'clientTime'))+' min','comercial','Duración total de registros finalizados / cantidad de registros finalizados. Falta acordar el inicio y fin de la medición.'],
  ['02','Tiempo de registro de contratos',fmt(avg(rows,'contractTime'))+' min','comercial','Duración total de registro de contratos / contratos con medición válida.'],
  ['03','Errores en pagos','2,8 %','pagos','Pagos revisados con al menos un error / pagos revisados × 100. Ejemplo ilustrativo; criterio de error pendiente de validar.'],
  ['04','Tiempo de gestión de pedidos',fmt(avg(rows,'orderTime'))+' min','instalaciones','Duración de gestión acumulada / pedidos medidos. La gestión administrativa y la ejecución técnica deben distinguirse.'],
  ['05','Tiempo de generación de reportes',fmt(avg(rows,'reportTime'))+' s','reportes','Tiempo desde solicitar hasta disponer del reporte / reportes generados correctamente.'],
  ['06','Registro correcto de equipos','96,4 %','inventario','Registros que cumplen las reglas de calidad / registros evaluados × 100. Reglas por validar.'],
  ['07','Registro correcto de materiales','98,1 %','inventario','Registros de materiales correctos / registros evaluados × 100. Reglas por validar.'],
  ['08','Equipos disponibles',fmt(count*3),'inventario','Conteo de equipos en estado DISPONIBLE a una fecha de corte. En esta vista el valor es ilustrativo.'],
  ['09','Existencias de materiales','Ver detalle','inventario','Saldo inicial + entradas − salidas, por material y unidad. No se suman metros con unidades. Ejemplo: cable 420 m; conectores 180 unid.; grapas 260 unid.'],
  ['10','Pagos pendientes',fmt(count*2),'pagos','Conteo de pagos con estado PENDIENTE. Debe definirse la fecha de corte.'],
  ['11','Pagos vencidos',fmt(count),'pagos','Conteo de obligaciones sin pagar con vencimiento anterior a la fecha de corte, excluyendo anuladas.'],
  ['12','Instalaciones completadas',fmt(sum(rows,'installs')),'instalaciones','Conteo de instalaciones completadas según la fecha de finalización; el cierre lo confirma el módulo de instalaciones.']];}
 function indicators(rows){
  if(!rows.length){content.innerHTML=empty();return;}const ms=metrics(rows).filter(m=>state.process==='all'||m[3]===state.process);
  content.innerHTML=`<div class="an-stats">${stat('Indicadores en pantalla',ms.length,'Según el proceso seleccionado')}${stat('Eficiencia','Tiempos','Registro, pedidos y reportes')}${stat('Calidad','Porcentajes','Pagos, equipos y materiales')}${stat('Disponibilidad','Cantidades','Inventario y atención')}</div><h2 class="an-section-title">Desempeño de los procesos</h2><p class="an-section-note">Valores ilustrativos. Consulta la definición antes de conectar cada medición con los datos reales.</p><div class="an-grid">${ms.map(m=>`<article class="an-card"><div class="an-card-top"><span class="an-status">RF-IND-${m[0]}</span><span class="an-label">${escape(m[3])}</span></div><h3>${m[1]}</h3><div class="an-number">${m[2]}</div><small>Ejemplo · No es una medición real</small><br><button class="an-detail" data-metric="${m[0]}">${m[0]==='09'?'Ver existencias por material':'Ver definición del indicador'}</button></article>`).join('')}</div>`;
 }
 const bars=(values)=>{const max=Math.max(1,...values.map(v=>v[1]));return `<div class="an-bars">${values.map(([label,value])=>`<div class="an-bar-row"><span>${escape(label)}</span><div class="an-track" aria-hidden="true"><div class="an-fill" style="width:${value/max*100}%"></div></div><strong>${fmt(value)}</strong></div>`).join('')}</div>`;};
 const panel=(title,note,body)=>`<section class="an-panel"><h2>${title}</h2><p class="an-description">${note}</p>${body}</section>`;
 function bi(rows){
  if(!rows.length){content.innerHTML=empty();return;}
  const visible=g=>state.process==='all'||state.process===g;let cards='',charts='';const n=rows.length;
  if(visible('comercial')){
   cards+=stat('Clientes registrados',fmt(sum(rows,'clients')))+stat('Ventas registradas',fmt(n),'Cantidad de operaciones de ejemplo')+stat('Monto de ventas',money(sum(rows,'amount')),'Ventas confirmadas de ejemplo')+stat('Contratos registrados',fmt(sum(rows,'contracts')));
   const months=[...new Set(rows.map(r=>r.m))].map(m=>[new Date(2026,m-1,1).toLocaleDateString('es-PE',{month:'short'}),sum(rows.filter(r=>r.m===m),'amount')]);const max=Math.max(...months.map(m=>m[1]));
   charts+=panel('Ventas por mes','Evolución del monto vendido · Soles · Muestra filtrada',`<div class="an-trend">${months.map(([month,value])=>`<div class="an-trend-col"><strong>${fmt(value)}</strong><div class="an-trend-bar" style="height:${value/max*145}px" aria-label="${month}: ${money(value)}"></div><span>${month}</span></div>`).join('')}</div>`);
   const total=sum(rows,'contracts'),a=Math.floor(total*.4),b=Math.floor(total*.35);charts+=panel('Participación del personal','Contratos asociados por empleado · Cantidad, no calificación de productividad',bars([['Empleado A',a],['Empleado B',b],['Empleado C',total-a-b]]));
  }
  if(visible('pagos')){cards+=stat('Pagos pendientes',fmt(n*2))+stat('Pagos vencidos',fmt(n));charts+=panel('Pagos por estado','Identifica obligaciones que requieren seguimiento · Distribución ficticia',bars([['Realizados',n*5],['Pendientes',n*2],['Vencidos',n],['Anulados',Math.floor(n/4)]]));}
  if(visible('instalaciones')){cards+=stat('Instalaciones completadas',fmt(sum(rows,'installs')));charts+=panel('Estado de instalaciones','Distribución de la atención · Datos de ejemplo',bars([['Completadas',sum(rows,'installs')],['En proceso',n],['Programadas',n*2],['Canceladas',Math.floor(n/3)]]));}
  if(visible('inventario')){cards+=stat('Equipos disponibles',fmt(n*3),'Disponibilidad ilustrativa');charts+=panel('Equipos por estado','Disponibilidad para futuras asignaciones · Datos de ejemplo',bars([['Disponibles',n*3],['Asignados',n*4],['Mantenimiento',n],['Inactivos',Math.floor(n/2)]]));charts+=panel('Existencias por material','Cada material conserva su unidad · Saldo ilustrativo, no histórico',table(['Material','Disponible','Unidad'],[['Cable de fibra','420','m'],['Conectores','180','unid.'],['Grapas','260','unid.']]));charts+=panel('Movimientos de Kardex','Cantidad de movimientos por tipo, sin sumar unidades incompatibles',bars([['Entradas',n*2],['Consumos',n*3],['Devoluciones',n],['Ajustes',Math.floor(n/2)]]));charts+=panel('Calidad de registros','Porcentaje ilustrativo · Criterios de validación pendientes',bars([['Equipos',96.4],['Materiales',98.1]])+'<p class="an-footer-note">Escala comparativa en porcentaje. Estos valores son ejemplos fijos.</p>');}
  if(state.process==='reportes'){cards+=stat('Tiempo de generación',fmt(avg(rows,'reportTime'))+' s');charts+=panel('Generación de reportes','Representación del tiempo por formato · Ejemplo en segundos',bars([['Consulta',1.2],['PDF',2.4],['Excel',1.8]]));}
  content.innerHTML=`<div class="an-stats">${cards}</div><div class="an-chart-grid">${charts}</div><p class="an-footer-note">Demostración visual. Los conteos varían con la muestra del periodo; existencias y porcentajes de calidad son ejemplos fijos. La fecha de corte y las fórmulas definitivas se acordarán antes de conectar datos reales.</p>`;
 }
 function render(){const selected=base.filter(r=>r.date>=state.from&&r.date<=state.to);document.getElementById('an-period').textContent=`Periodo aplicado: ${state.from} — ${state.to}`;document.getElementById('an-updated').textContent='Vista actualizada: '+new Date().toLocaleTimeString('es-PE');({reports,indicators,bi})[screen](selected);}
 function apply(){const from=document.getElementById('an-from').value,to=document.getElementById('an-to').value,error=document.getElementById('an-error');if(!from||!to||from>to){error.hidden=false;error.textContent='Selecciona un periodo válido: la fecha inicial no puede ser posterior a la final.';return;}error.hidden=true;state={from,to,type:typeSelect?.value||'clientes',process:processSelect?.value||'all',page:1};const q=new URLSearchParams({desde:from,hasta:to,...(screen==='reports'?{tipo:state.type}:{proceso:state.process})});history.replaceState(null,'','?'+q);render();}
 form.addEventListener('submit',e=>{e.preventDefault();apply();});document.getElementById('an-reset').addEventListener('click',()=>{form.reset();apply();});
 content.addEventListener('click',e=>{const button=e.target.closest('button');if(!button)return;if(button.dataset.page){state.page+=Number(button.dataset.page);render();}if(button.dataset.export)showDialog('Exportación a '+button.dataset.export,'Esta pantalla es una demostración. La descarga del archivo y su fecha de generación se conectarán en la siguiente etapa. No se ha generado ni guardado ningún archivo.');if(button.dataset.metric){const m=metrics(base.filter(r=>r.date>=state.from&&r.date<=state.to)).find(m=>m[0]===button.dataset.metric);showDialog(m[1],m[4]);}});
 apply();
})();