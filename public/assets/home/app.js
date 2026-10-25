import * as D from './domain.js?v=8';
import * as store from './store.js?v=8';
import * as camera from './scanner.js?v=8';
import {exportCopy,importCopy} from './transfer.js?v=8';
import * as cloud from './cloud.js?v=8';
const $=id=>document.getElementById(id),el=(tag,text,className)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(className)e.className=className;return e;};
let state=D.initial(),filter='available',selected=null,busyScan=false,lastScan=null,undoId=null,toastTimer=null,refreshing=false,renderVersion=0,pendingReceipt=null,dateOnly=false;
const photoUrls=new Map();
const button=(text,action,className='button secondary')=>{const b=el('button',text,className);b.type='button';if(action)b.dataset.action=action;return b;};
const error=e=>toast(e?.message||'Nu am putut finaliza. Încearcă din nou.',null,true);
function toast(text,event=null,isError=false){clearTimeout(toastTimer);$('toast-text').textContent=text;$('toast').hidden=false;$('toast').classList.toggle('error',isError);undoId=event?.id||null;$('undo').hidden=!undoId;toastTimer=setTimeout(()=>$('toast').hidden=true,event?15000:8000);}
function show(id){const d=$(id);if(!d.open)d.showModal();}
function close(id){$(id).close();if(id==='scanner')camera.stop();}
function safeLink(raw){try{const u=new URL(raw,location.origin);if(u.username||u.password)return null;return u.protocol==='https:'||(u.protocol==='http:'&&u.origin===location.origin)?u.href:null;}catch{return null;}}
async function render(){
  const version=++renderVersion,data=await store.read();if(version!==renderVersion)return;state=data;const box=$('inventory');box.replaceChildren();box.setAttribute('aria-busy','false');
  $('connection').textContent=navigator.onLine?(cloud.currentStatus()||'Salvat pe acest dispozitiv.'):'Fără conexiune · alertele nu pot fi verificate acum.';
  $('header-add').hidden=state.items.length===0&&filter==='available';
  if(filter==='shopping'){renderShopping(box);return;}
  const items=state.items.filter(i=>filter==='consumed'?i.state==='consumed':i.state!=='consumed');
  items.sort((a,b)=>D.status(a).rank-D.status(b).rank||(a.expiry&&b.expiry?D.dateEnd(a.expiry)-D.dateEnd(b.expiry):0)||String(b.added_at).localeCompare(String(a.added_at)));
  if(!items.length){const empty=el('div',undefined,'empty');empty.append(el('h2',filter==='consumed'?'Niciun aliment terminat':'Cumpărăturile ajung aici'),el('p',filter==='consumed'?'Când termini un produs, îl poți marca dintr-o atingere.':'Scanează ambalajul. Îți păstrăm alimentele la îndemână.'));if(filter!=='consumed')empty.append(button('Adaugă un aliment','scan','button primary'));box.append(empty);return;}
  for(const item of items){
    const s=D.status(item),article=el('article',undefined,'food-card'),main=button('',null,'food-main');main.dataset.item=item.id;main.dataset.action='detail';
    if(item.photo_id){const img=el('img',undefined,'food-photo');img.alt='';img.width=66;img.height=76;const blob=await store.photo(item.photo_id);if(blob){if(!photoUrls.has(item.photo_id))photoUrls.set(item.photo_id,URL.createObjectURL(blob));img.src=photoUrls.get(item.photo_id);}main.append(img);}
    else{const placeholder=el('span','', 'food-photo food-placeholder');placeholder.append($('food-placeholder-icon').content.cloneNode(true));main.append(placeholder);}
    if(version!==renderVersion)return;const info=el('span',undefined,'food-info');info.append(el('span',item.name,'food-name'),el('span',s.text,'food-status '+s.tone));
    const meta=[];if(item.opened_at)meta.push('Deschis '+new Intl.DateTimeFormat('ro-RO',{day:'numeric',month:'short'}).format(new Date(item.opened_at)));if(item.quantity!==1||item.unit!=='pack')meta.push(item.quantity+' '+(item.unit==='pack'?'ambalaje':item.unit));if(item.profile.simulation)meta.push('Demonstrație');if(meta.length)info.append(el('span',meta.join(' · '),'food-meta'));main.append(info);article.append(main);if(!item.expiry&&item.state!=='consumed'){const date=button('Adaugă data de pe ambalaj','date','food-date-action');date.dataset.item=item.id;article.append(date);}
    const warnings=D.allergenWarnings(item.profile,state.preferences.allergens);for(const w of warnings){const unknown=item.profile.food_data?.allergens?.status!=='declared';article.append(el('p',unknown?'Verifică alergenii pe ambalaj.':w,'food-warning'+(unknown?' muted':'')));}
    if(item.state!=='consumed'){const actions=el('div',undefined,'food-actions');const opened=button(item.opened_at?'Deja deschis':'Am deschis','open','');opened.dataset.item=item.id;opened.disabled=Boolean(item.opened_at);const consumed=button('Am terminat','consume','');consumed.dataset.item=item.id;actions.append(opened,consumed);article.append(actions);}box.append(article);
  }
}
function renderShopping(box){
  const form=el('form',undefined,'shopping-form'),input=el('input');input.name='name';input.maxLength=180;input.placeholder='Ce îți trebuie?';input.setAttribute('aria-label','Aliment pentru lista de cumpărături');input.required=true;const add=button('Adaugă',null,'button primary');add.type='submit';form.append(input,add);form.addEventListener('submit',async e=>{e.preventDefault();try{await store.update(s=>s.shopping.push({id:D.id(),name:input.value.trim().slice(0,180),done:false}));await render();}catch(e){error(e);}});box.append(form);
  if(!state.shopping.length)box.append(el('p','Lista este pregătită pentru următoarele cumpărături.','subtle'));
  for(const item of state.shopping){const row=el('div',undefined,'shopping-row'),label=el('label',undefined,'check'),check=el('input');check.type='checkbox';check.checked=item.done;label.append(check,el('span',item.name,item.done?'shopping-done':''));check.addEventListener('change',async()=>{try{await store.update(s=>{const x=s.shopping.find(v=>v.id===item.id);if(x)x.done=check.checked;});await render();}catch(e){error(e);}});const remove=button('Șterge',null,'text-button');remove.setAttribute('aria-label','Șterge '+item.name);remove.addEventListener('click',async()=>{try{await store.update(s=>s.shopping=s.shopping.filter(i=>i.id!==item.id));await render();}catch(e){error(e);}});row.append(label,remove);box.append(row);}
}
async function lookup(code){
  const parsed=D.parseCode(code),cache=state.items.find(i=>i.code===code||(parsed.gtin14&&i.profile.gtin14===parsed.gtin14&&i.profile.lot_code===parsed.lot_code&&(i.profile.gs1_qualifiers?.['22']??null)===(parsed.gs1_qualifiers?.['22']??null)));
  if(navigator.onLine){try{const r=await fetch('/api/v2/public/food?'+new URLSearchParams({code}),{cache:'no-store',credentials:'omit',signal:AbortSignal.timeout(5000)});const data=await r.json();if(r.ok)return D.cleanProfile(data);if(r.status>=400&&r.status<500)throw Object.assign(Error(data.message||'Codul nu este valid.'),{invalid:true});}catch(e){if(e.invalid)throw e;}}
  if(cache)return D.cachedProfile(cache.profile,parsed);
  const profile=D.unknownProfile(parsed);if(parsed.gtin14==='09520000000011'){profile.name='Pâine Bună Tehnic';profile.simulation=true;profile.source.kind='demonstration';profile.notices=['Exemplu demonstrativ. Termenul și valorile alimentare nu sunt validate.'];}return profile;
}
async function saveCode(code,{force=false}={}){
  if(busyScan)return false;busyScan=true;
  try{
    let scanned;try{scanned=new URL(code);}catch{}if(scanned?.origin===location.origin&&scanned.pathname==='/acasa/'&&scanned.hash.startsWith('#bon=')){close('scanner');await openReceipt(new URLSearchParams(scanned.hash.slice(1)).get('bon'));return true;}
    const parsed=D.parseCode(code),now=Date.now();
    if(!force&&lastScan&&now-lastScan.time<8000&&D.scanIdentity(parsed,code)===lastScan.identity)return;
    $('scanner-status').textContent='Se adaugă alimentul…';const profile=await lookup(code);
    const result=await store.update(s=>D.addItem(s,profile,code));lastScan={code,identity:D.scanIdentity(parsed,code),time:Date.now()};
    $('scanner-status').textContent=profile.name+' · adăugat';$('scan-again').hidden=false;toast(profile.name+' · adăugat',result.event);navigator.vibrate?.(60);await render();
    navigator.storage?.persist?.().catch(()=>{});return true;
  }catch(e){$('scanner-status').textContent=e.message||'Codul nu a putut fi citit.';if($('code-dialog').open){$('code-error').textContent=e.message||'Verifică codul de pe ambalaj.';$('code-error').hidden=false;const input=$('code-form').elements.code;input.setAttribute('aria-invalid','true');input.focus();}else if(!$('scanner').open)error(e);return false;}finally{busyScan=false;}
}
async function openReceipt(token){
  if(!/^[a-f0-9]{64}$/.test(token||''))throw Error('Legătura bonului nu este validă.');pendingReceipt=null;show('receipt-dialog');$('receipt-status').textContent='Se deschide bonul…';$('receipt-lines').replaceChildren();$('receipt-add').disabled=true;
  const response=await fetch('/api/v2/home/receipts/preview',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'omit',cache:'no-store',body:JSON.stringify({token}),signal:AbortSignal.timeout(10000)});const raw=await response.json();if(!response.ok)throw Error(raw.message||'Bonul nu este disponibil.');
  const {cleanReceipt}=await import('./receipt.js?v=8');pendingReceipt={...cleanReceipt(raw),schema_version:raw.schema_version};$('receipt-merchant').textContent=pendingReceipt.merchant;
  $('receipt-status').textContent=(pendingReceipt.simulation?'Bon demonstrativ. ':'')+'Adaugă cumpărăturile dintr-o singură atingere. Datele de expirare lipsă rămân necunoscute.';
  for(const line of pendingReceipt.lines){const row=el('li');row.append(el('strong',line.name),el('span',' · '+line.quantity+' '+(line.unit==='pack'?(line.quantity===1?'ambalaj':'ambalaje'):line.unit)));$('receipt-lines').append(row);}$('receipt-add').disabled=false;
}
async function incomingLink(){const fragment=new URLSearchParams(location.hash.slice(1)),pending=fragment.get('add'),receipt=fragment.get('bon');if(!pending&&!receipt)return;history.replaceState(null,'','/acasa/');if(receipt){try{await openReceipt(receipt);}catch(e){$('receipt-status').textContent=e.message||'Bonul nu este disponibil.';error(e);}}else{const f=$('code-form');f.elements.code.value=pending;show('code-dialog');}}
window.addEventListener('hashchange',()=>incomingLink().catch(error));
$('receipt-add').addEventListener('click',async()=>{const receipt=pendingReceipt;if(!receipt)return;const b=$('receipt-add');b.disabled=true;try{const {importReceipt}=await import('./receipt.js?v=8');const count=await importReceipt(receipt,(n,total)=>$('receipt-status').textContent='Pregătim '+n+' din '+total+' alimente…');pendingReceipt=null;close('receipt-dialog');filter='available';document.querySelectorAll('[data-filter]').forEach(b=>{b.classList.toggle('active',b.dataset.filter===filter);b.setAttribute('aria-pressed',b.dataset.filter===filter);});await render();toast(count?count+' alimente adăugate.':'Cumpărăturile din acest bon erau deja adăugate.');}catch(e){error(e);}finally{b.disabled=false;}});
async function scan(){
  show('scanner');$('scanner-status').textContent='Îndreaptă camera spre QR sau codul de bare.';
  try{await camera.start($('camera'),code=>saveCode(code),text=>$('scanner-status').textContent=text);}catch(e){$('scanner-status').textContent=e.name==='NotAllowedError'?'Permite accesul la cameră sau folosește o fotografie.':e.message;}
}
async function act(itemId,action){const result=await store.update(s=>D.changeItem(s,itemId,action));if(result){toast(action==='open'?'Am notat deschiderea.':'Aliment marcat terminat.',result.event);await render();}}
async function detail(itemId){
  selected=itemId;const item=state.items.find(i=>i.id===itemId);if(!item)return;const p=item.profile,box=$('detail-body');$('detail-title').textContent=item.name;box.replaceChildren();
  if(item.photo_id){const blob=await store.photo(item.photo_id);if(blob){if(!photoUrls.has(item.photo_id))photoUrls.set(item.photo_id,URL.createObjectURL(blob));const img=el('img',undefined,'detail-photo');img.src=photoUrls.get(item.photo_id);img.alt='Fotografia alimentului';box.append(img);}}
  const s=D.status(item);box.append(el('p',s.text,'detail-status '+s.tone));
  for(const a of p.alerts||[])box.append(el('p',(a.lot_code?'Lot '+a.lot_code+': ':'')+a.message,'detail-alert'));
  for(const text of p.notices||[])box.append(el('p',text,'subtle'));
  for(const text of D.allergenWarnings(p,state.preferences.allergens))box.append(el('p',text,'detail-alert'));
  const actions=el('div',undefined,'dialog-actions');if(item.state!=='consumed'){const o=button(item.opened_at?'Deja deschis':'Am deschis','open','button secondary');o.dataset.item=item.id;o.disabled=Boolean(item.opened_at);const c=button('Am terminat','consume','button primary');c.dataset.item=item.id;actions.append(o,c);}box.append(actions);
  if(!item.expiry){const date=button('Adaugă data de pe ambalaj','date');date.dataset.item=item.id;box.append(date);}box.append(button('Corectează alimentul','edit'));
  const texts=[['Ingrediente',p.ingredients],['Alergeni',p.allergen_text||'Nu avem o declarație verificată. Citește eticheta.'],['Păstrare',p.storage_text],['După deschidere',p.after_opening_text],['Nutriție',p.nutrition_text]];
  for(const [title,text] of texts)if(text){const sec=el('section',undefined,'detail-section');sec.append(el('h3',title),el('p',text));box.append(sec);}
  const opening=p.food_data?.after_opening;if(item.opened_at&&opening){box.append(el('p',`După deschidere: ${opening.hours} ore, dacă sunt respectate condițiile: ${opening.conditions}`,'subtle'));}
  const n=p.food_data?.nutrition;if(n?.status==='declared'){
    const sec=el('section',undefined,'detail-section');sec.append(el('h3','Carbohidrați pentru porția ta'));
    const note=n.basis==='per_100_ml'?'Etichetă per 100 ml':n.basis==='per_100_g'?'Etichetă per 100 g':`Etichetă per porție de ${n.portion?.amount} ${n.portion?.unit}`;
    sec.append(el('p',note+' · '+(n.preparation==='as_prepared'?'produs preparat':'produs ca atare')+' · '+n.jurisdiction,'subtle'));
    const label=el('label','Cantitatea porției'),amount=el('input');amount.type='number';amount.min='0.1';amount.max='100000';amount.step='any';amount.inputMode='decimal';amount.value=String(n.portion?.amount||100);label.append(amount);
    const unit=n.basis==='per_100_ml'?'ml':n.basis==='per_portion'?n.portion.unit:'g';label.firstChild.textContent='Cantitatea porției ('+unit+')';const output=el('p');output.setAttribute('aria-live','polite');const calc=()=>{const r=D.carbs(p,Number(amount.value),unit);output.textContent=r?r.grams.toLocaleString('ro-RO')+' g carbohidrați':'Cantitatea de carbohidrați nu este cunoscută.';};amount.addEventListener('input',calc);calc();sec.append(label,output);box.append(sec);
  }
  const source=el('section',undefined,'detail-section');if(p.lot_code)source.append(el('p','Lot: '+p.lot_code));
  if(p.source?.version)source.append(el('p','Informații: '+p.source.version,'subtle'));
  source.append(el('p',item.imported?'Copie importată · sursa nu este reverificată.':p.source?.kind==='producer_approved'?'Informații publicate de producător.':'Verifică informațiile de pe ambalaj.','subtle'));
  source.append(el('p',p.alerts_checked_at?'Ultima verificare a alertelor: '+new Intl.DateTimeFormat('ro-RO',{dateStyle:'short',timeStyle:'short'}).format(new Date(p.alerts_checked_at))+'.':'Alertele nu sunt verificate.','subtle'));
  if(p.public_url){const href=safeLink(p.public_url);if(href){const a=el('a','Pagina produsului','text-button');a.href=href;a.rel='noopener';source.append(a);}}
  if('speechSynthesis'in window){const b=button('Citește cu voce',null,'button secondary');b.addEventListener('click',()=>{speechSynthesis.cancel();const text=[item.name,s.text,p.allergen_text,...D.allergenWarnings(p,state.preferences.allergens)].filter(Boolean).join('. ');const utterance=new SpeechSynthesisUtterance(text);utterance.lang='ro-RO';speechSynthesis.speak(utterance);});source.append(b);}box.append(source);show('details');
}
function edit(onlyDate=false){
  const i=state.items.find(i=>i.id===selected);if(!i)return;close('details');dateOnly=onlyDate;$('editor-title').textContent=onlyDate?'Data de pe ambalaj':'Corectează alimentul';for(const group of $('edit-form').querySelectorAll('[data-edit-general]'))group.hidden=onlyDate;$('edit-error').hidden=true;const f=$('edit-form');f.elements.date_kind.removeAttribute('aria-invalid');f.elements.name.value=i.name;f.elements.location.value=i.location;f.elements.quantity.value=i.quantity;f.elements.unit.value=i.unit;f.elements.date_kind.value=i.expiry?.kind||'unknown';f.elements.date_precision.value=i.expiry?.precision||'day';setDateType();f.elements.date_value.value=i.expiry?.value||'';$('date-photo-status').textContent='';show('editor');if(onlyDate)f.elements.date_kind.focus();
}
function setDateType(){const f=$('edit-form'),precision=f.elements.date_precision.value;f.elements.date_value.type=precision==='month'?'month':precision==='year'?'number':'date';f.elements.date_value.min=precision==='year'?'1900':precision==='month'?'1900-01':'1900-01-01';f.elements.date_value.max=precision==='year'?'2200':precision==='month'?'2200-12':'2200-12-31';f.elements.date_value.required=f.elements.date_kind.value!=='unknown';$('date-format').open=precision!=='day';}
async function photoFile(file,maxSize=720){
  if(!file||file.size>25000000)throw Error('Alege o fotografie mai mică de 25 MB.');
  const object=URL.createObjectURL(file),image=new Image();try{image.src=object;await image.decode();if(image.naturalWidth*image.naturalHeight>30000000)throw Error('Fotografie prea mare.');const canvas=document.createElement('canvas'),ratio=Math.min(1,maxSize/Math.max(image.naturalWidth,image.naturalHeight));canvas.width=Math.max(1,Math.round(image.naturalWidth*ratio));canvas.height=Math.max(1,Math.round(image.naturalHeight*ratio));canvas.getContext('2d').drawImage(image,0,0,canvas.width,canvas.height);const blob=await new Promise(r=>canvas.toBlob(r,'image/jpeg',.78));if(!blob)throw Error('Fotografia nu poate fi salvată.');return {image,canvas,blob,release:()=>URL.revokeObjectURL(object)};}catch{URL.revokeObjectURL(object);throw Error('Fotografia nu poate fi deschisă. Alege o imagine JPEG sau PNG.');}
}
async function addPhoto(file){
  const p=await photoFile(file);try{let code=null;try{code=await camera.fromPhoto(p.image);}catch{}if(code){await saveCode(code,{force:true});return;}const key=D.id();const profile=D.unknownProfile();profile.name='Aliment fotografiat';const r=await store.update(s=>D.addItem(s,profile,'',key),new Map([[key,p.blob]]));close('scanner');toast('Aliment adăugat.',r.event);await render();await detail(r.item.id);}finally{p.release();}
}
async function refreshAlerts(){
  if(refreshing||!navigator.onLine)return;refreshing=true;try{
    const current=await store.read(),items=current.items.filter(i=>(i.state!=='consumed'||!i.consumed_at||Date.parse(i.consumed_at)>Date.now()-30*86400000)&&i.code&&(!i.profile.alerts_checked_at||Date.now()-Date.parse(i.profile.alerts_checked_at)>60000)).sort((a,b)=>(Date.parse(a.profile.alerts_checked_at)||0)-(Date.parse(b.profile.alerts_checked_at)||0)).slice(0,40);
    for(let at=0;at<items.length;at+=4)await Promise.all(items.slice(at,at+4).map(async item=>{try{const r=await fetch('/api/v2/public/food?'+new URLSearchParams({code:item.code}),{cache:'no-store',credentials:'omit',signal:AbortSignal.timeout(5000)});if(!r.ok)return;const p=D.cleanProfile(await r.json());if(!p.alerts_checked_at)return;await store.update(s=>{const i=s.items.find(v=>v.id===item.id);if(!i)return;if(i.profile.alerts_checked_at&&Date.parse(i.profile.alerts_checked_at)>Date.parse(p.alerts_checked_at))return;for(const key of ['alerts','alerts_checked_at','alert_scope','batch_state','notices'])i.profile[key]=p[key];});}catch{}}));
    await render();
  }finally{refreshing=false;}
}
document.addEventListener('click',async e=>{const b=e.target.closest('button');if(!b)return;try{
  if(b.dataset.close){close(b.dataset.close);return;}if(b.dataset.filter){filter=b.dataset.filter;for(const tab of document.querySelectorAll('[data-filter]')){const active=tab===b;tab.classList.toggle('active',active);tab.setAttribute('aria-pressed',String(active));}await render();return;}
  const action=b.dataset.action;if(action==='scan'){await scan();return;}if(action==='photo'){$('photo-file').click();return;}if(action==='code'){close('scanner');$('code-error').hidden=true;$('code-form').elements.code.removeAttribute('aria-invalid');show('code-dialog');return;}
  if(action==='detail'){await detail(b.dataset.item);return;}if(action==='open'||action==='consume'){await act(b.dataset.item,action);if($('details').open)await detail(b.dataset.item);return;}if(action==='date'){selected=b.dataset.item;edit(true);return;}if(action==='edit'){edit();return;}
  if(action==='settings'){for(const c of $('preferences').querySelectorAll('input'))c.checked=state.preferences.allergens.includes(c.value);show('settings');return;}
  if(action==='export'){b.disabled=true;try{const blob=await exportCopy(),a=el('a');a.href=URL.createObjectURL(blob);a.download='openaliment-acasa-'+D.today()+'.json';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(a.href),30000);toast('Copia este pregătită pentru salvare.');}finally{b.disabled=false;}return;}
  if(action==='import'){$('import-file').click();return;}if(action==='date-photo'){$('date-file').click();return;}
}catch(e){error(e);}});
$('undo').addEventListener('click',async()=>{if(!undoId)return;try{await store.update(s=>D.undo(s,undoId));toast('Acțiune anulată.');await render();}catch(e){error(e);}});
$('scan-again').addEventListener('click',()=>{if(lastScan)saveCode(lastScan.code,{force:true});});
$('scanner').addEventListener('close',()=>camera.stop());$('scanner').addEventListener('cancel',()=>camera.stop());
document.addEventListener('visibilitychange',()=>{if(document.hidden)camera.stop();else{if($('scanner').open)close('scanner');refreshAlerts().catch(error);}});
window.addEventListener('pagehide',()=>{camera.stop();globalThis.speechSynthesis?.cancel?.();});window.addEventListener('online',()=>{render().catch(error);refreshAlerts().catch(error);});window.addEventListener('offline',()=>render().catch(error));
$('code-form').addEventListener('submit',async e=>{e.preventDefault();const b=e.submitter||e.target.querySelector('button[type=submit]');if(b.disabled)return;b.disabled=true;$('code-error').hidden=true;e.target.elements.code.removeAttribute('aria-invalid');try{if(await saveCode(e.target.elements.code.value,{force:true}))close('code-dialog');}finally{b.disabled=false;}});
$('code-form').elements.code.addEventListener('input',()=>{$('code-error').hidden=true;$('code-form').elements.code.removeAttribute('aria-invalid');});
$('photo-file').addEventListener('change',async e=>{try{if(e.target.files[0])await addPhoto(e.target.files[0]);}catch(e){error(e);}finally{e.target.value='';}});
$('date-file').addEventListener('change',async e=>{if(!e.target.files[0])return;let picture;try{
  picture=await photoFile(e.target.files[0],1800);const {recognize}=await import('./ocr.js?v=8');const result=await recognize(picture.canvas,text=>$('date-photo-status').textContent=text);const f=$('edit-form'),list=result.candidates;
  if(list.length===1&&result.confidence>=50){const date=list[0];f.elements.date_precision.value=date.precision;setDateType();f.elements.date_value.value=date.value;if(date.kind)f.elements.date_kind.value=date.kind;setDateType();$('date-photo-status').textContent='Am citit '+D.dateText({...date,kind:date.kind||'reminder',source:'user'})+'. Verifică data și tipul de pe ambalaj înainte să salvezi.';}
  else if(list.length>1){$('date-photo-status').textContent='Sunt mai multe date. Alege data și tipul înscrise pe ambalaj.';for(const date of list){const b=button(D.dateText({...date,kind:date.kind||'reminder',source:'user'}),null,'button secondary');b.addEventListener('click',()=>{f.elements.date_precision.value=date.precision;setDateType();f.elements.date_value.value=date.value;if(date.kind)f.elements.date_kind.value=date.kind;setDateType();});$('date-photo-status').append(b);}}
  else $('date-photo-status').textContent='Nu am citit clar data. Alege data și tipul exact de pe ambalaj.';
  f.elements.date_value.focus();
}catch(e){$('date-photo-status').textContent='Fotografia nu a putut fi citită. Poți alege data de pe ambalaj.';error(e);}finally{picture?.release();e.target.value='';}});
$('edit-form').elements.date_precision.addEventListener('change',()=>{const f=$('edit-form');f.elements.date_value.value='';setDateType();});$('edit-form').elements.date_kind.addEventListener('change',()=>{const f=$('edit-form');if(f.elements.date_kind.value==='unknown')f.elements.date_value.value='';setDateType();});
$('edit-form').addEventListener('submit',async event=>{
  event.preventDefault();
  const form=event.target,submit=event.submitter||form.querySelector('button[type=submit]');
  if(submit.disabled)return;
  submit.disabled=true;
  $('edit-error').hidden=true;
  form.elements.date_kind.removeAttribute('aria-invalid');
  try{
    if(dateOnly&&form.elements.date_kind.value==='unknown'&&!form.elements.date_value.value){
      form.elements.date_kind.focus();
      throw Error('Alege tipul și data de pe ambalaj. Dacă nu le cunoști, poți închide acest panou.');
    }
    if(form.elements.date_value.value&&form.elements.date_kind.value==='unknown'){
      form.elements.date_kind.setAttribute('aria-invalid','true');
      form.elements.date_kind.focus();
      throw Error('Alege tipul datei înscrise pe ambalaj sau Memento personal.');
    }
    const expiry=form.elements.date_kind.value==='unknown'?null:D.checkedDate({kind:form.elements.date_kind.value,value:form.elements.date_value.value,precision:form.elements.date_precision.value,source:'user'});
    const changes=dateOnly?{expiry}:{name:form.elements.name.value,location:form.elements.location.value,quantity:Number(form.elements.quantity.value),unit:form.elements.unit.value,expiry};
    const result=await store.update(state=>D.changeItem(state,selected,'edit',changes));
    close('editor');
    await render();
    toast(dateOnly?'Data a fost salvată.':'Corectare salvată.',result.event);
  }catch(error){
    $('edit-error').textContent=error.message||'Verifică informațiile și încearcă din nou.';
    $('edit-error').hidden=false;
  }finally{
    submit.disabled=false;
  }
});
$('preferences').addEventListener('submit',async e=>{e.preventDefault();try{const selected=[...e.target.querySelectorAll('input:checked')].map(c=>c.value);await store.update(s=>s.preferences.allergens=selected);close('settings');await render();toast('Alegere păstrată pe dispozitiv.');}catch(e){error(e);}});
$('import-file').addEventListener('change',async e=>{try{if(e.target.files[0]){const count=await importCopy(e.target.files[0]);await render();close('settings');toast(count?count+' alimente adăugate din copie.':'Alimentele erau deja salvate.');refreshAlerts().catch(()=>{});}}catch(e){error(e);}finally{e.target.value='';}});
setInterval(()=>{if(!document.hidden)refreshAlerts().catch(()=>{});},60000);
window.addEventListener('storage',()=>render().catch(error));
window.addEventListener('home-cloud-status',e=>{if(navigator.onLine)$('connection').textContent=e.detail||'Salvat pe acest dispozitiv.';});
const channel='BroadcastChannel'in window?new BroadcastChannel('openaliment-home'):null;
window.addEventListener('home-changed',()=>channel?.postMessage('changed'));if(channel)channel.onmessage=()=>render().catch(error);
try{await render();if('serviceWorker'in navigator)navigator.serviceWorker.register('/acasa/sw.js',{scope:'/acasa/'}).catch(()=>{});await incomingLink();cloud.init(()=>{render().catch(error);refreshAlerts().catch(()=>{});}).catch(error);refreshAlerts().catch(()=>{});}catch(e){$('connection').textContent=e.message;$('inventory').setAttribute('aria-busy','false');error(e);}
