import * as D from './domain.js?v=8';
import * as store from './store.js?v=8';
import * as C from './cloud-domain.js?v=8';
import {initPrivacy,privacyState,incomingPrivacy,requestTransfer} from './privacy.js?v=8';
const $=id=>document.getElementById(id),owner=D.id();
let session=null,ready=false,busy=false,timer=null,loginToken=null,inviteToken=null,changed=()=>{},message='',initialized=false;
const downloads=new Map();
function status(text){message=text;if($('cloud-status'))$('cloud-status').textContent=text;window.dispatchEvent(new CustomEvent('home-cloud-status',{detail:text}));}
async function api(path,{body,method=body===undefined?'GET':'POST',raw=false}={}){
  const headers={};if(body!==undefined&&!raw)headers['Content-Type']='application/json';if(session?.csrf&&method==='POST')headers['X-Home-CSRF']=session.csrf;
  let response;try{response=await fetch('/api/v2/home/'+path,{method,headers,body:body===undefined?undefined:raw?body:JSON.stringify(body),credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});}catch{throw Error('Datele sunt salvate aici. Sincronizarea va încerca din nou.');}
  if(raw&&method==='GET'){if(!response.ok)throw Error('Fotografia nu este disponibilă.');return response.blob();}
  let result;try{result=await response.json();}catch{throw Error('Sincronizarea nu este disponibilă acum. Datele sunt salvate aici.');}if(!response.ok&&!(response.status===409&&Array.isArray(result.conflicts)))throw Object.assign(Error(result.message||'Sincronizarea nu este disponibilă acum.'),{status:response.status});return result;
}
async function loadSession(){const response=await api('session');ready=response.available===true;session=response.authenticated?response.session:null;$('cloud-area').hidden=!ready||!session&&!response.magic_link_ready;$('cloud-guest').hidden=Boolean(session);$('cloud-connected').hidden=!session;
  if(session){$('cloud-identity').textContent=session.email;$('cloud-family-name').textContent=session.household_name;$('cloud-devices').replaceChildren();
    for(const device of response.devices||[]){const row=document.createElement('p'),name=document.createElement('span');name.textContent=device.name+(device.id===session.device_id?' · acest dispozitiv':'');row.append(name);if(device.id!==session.device_id){const b=document.createElement('button');b.className='text-button';b.type='button';b.textContent='Deconectează';b.dataset.cloudRevoke=device.id;row.append(' · ',b);}$('cloud-devices').append(row);}
    const meta=await store.syncRead();$('cloud-enable').hidden=meta.enabled&&meta.household_id===session.household_id;$('cloud-invite').hidden=session.role!=='owner';$('cloud-family-edit').hidden=session.role!=='owner';$('cloud-family-input').value=session.household_name;
  }
  privacyState(session);return response;
}
async function familyDetails(){
  if(!session)return;const [families,members]=await Promise.all([api('households'),api('members')]);
  $('cloud-families').replaceChildren();$('cloud-members').replaceChildren();
  for(const family of families.items){const row=document.createElement('p'),name=document.createElement('span');name.textContent=family.name+(family.id===session.household_id?' · aici':'');row.append(name);
    if(family.id!==session.household_id){const b=document.createElement('button');b.type='button';b.className='text-button';b.textContent='Deschide';b.dataset.cloudSwitch=family.id;row.append(' · ',b);}$('cloud-families').append(row);}
  for(const member of members.items){const row=document.createElement('p'),name=document.createElement('span');name.textContent=member.email+(member.account_id===session.account_id?' · tu':'');row.append(name);
    if(session.role==='owner'&&member.role!=='owner'){const b=document.createElement('button');b.type='button';b.className='text-button';b.textContent='Retrage accesul';b.dataset.cloudRemove=member.account_id;row.append(' · ',b);if($('cloud-account-actions')){const transfer=document.createElement('button');transfer.type='button';transfer.className='text-button';transfer.textContent='Transferă administrarea';transfer.addEventListener('click',()=>requestTransfer(transfer,member.account_id));row.append(' · ',transfer);}}$('cloud-members').append(row);}
}
async function owned(fn,{notify=false}={}){return store.syncUpdate((inventory,meta,s)=>{if(!meta.enabled||meta.household_id!==session?.household_id||meta.lease?.owner!==owner)throw Error('Sincronizarea s-a schimbat pe alt dispozitiv sau în altă fereastră.');meta.lease.until=Date.now()+90000;return fn(inventory,meta,s);},{notify});}
async function uploadPhotos(outbox){
  const ids=new Set();for(const op of outbox.operations)if(op.item?.photo_id)ids.add(op.item.photo_id);for(const e of outbox.history)for(const item of[e.before,e.after])if(item?.photo_id)ids.add(item.photo_id);
  for(const id of ids){const meta=await store.syncRead();if(meta.photos.includes(id))continue;await owned(()=>{});const photo=await store.photo(id);if(!photo)throw Error('O fotografie lipsește de pe dispozitiv. Nu am pierdut alimentele salvate.');await api('photos/'+id,{body:photo,raw:true});await owned((_,m)=>{if(!m.photos.includes(id))m.photos.push(id);});}
}
async function pull(){
  const meta=await store.syncRead(),page=await api('changes?'+new URLSearchParams({after:String(meta.cursor),limit:'50'})),inventory=await store.read(),local=await C.fingerprints(inventory.items),remote=await C.pageFingerprints(page);
  await owned((s,m)=>C.applyPage(s,m,page,local,remote),{notify:page.changes.length>0});if(page.changes.length)changed();return page.has_more;
}
async function run(){
  if(busy||!session||!navigator.onLine)return;busy=true;let again=false;
  try{
    const acquired=await store.syncUpdate((_,m)=>{if(!m.enabled||m.household_id!==session.household_id)return false;if(m.lease?.owner!==owner&&m.lease?.until>Date.now())return false;m.lease={owner,until:Date.now()+90000};return true;});if(!acquired)return;
    status('Se păstrează și pe celelalte dispozitive…');
    // Finish any persisted request before pulling; lost responses are retried byte-for-byte by ID.
    for(let step=0;step<10;step++){
      let meta=await store.syncRead();if(!meta.outbox){if(await pull()){again=true;continue;}meta=await store.syncRead();const prepared=await C.prepare(await store.read(),meta);if(!prepared){again=false;break;}await owned((_,m)=>{m.outbox??=prepared;});}
      const outbox=(await store.syncRead()).outbox;await uploadPhotos(outbox);const response=await api('sync',{body:C.wire(outbox)});await owned((_,m)=>C.acknowledge(m,outbox,response));
      if(response.conflicts.length){again=false;break;}again=true;
    }
    const meta=await store.syncRead();$('cloud-conflict').hidden=!meta.conflicts.length;
    status(meta.conflicts.length?'O modificare are nevoie de alegerea ta.':again?'Se păstrează și restul istoricului…':'Păstrat și pe celelalte dispozitive.');
  }catch(e){again=false;if(e.status===401){session=null;$('cloud-area').hidden=false;$('cloud-guest').hidden=false;$('cloud-connected').hidden=true;status('Reconectează acest dispozitiv prin email. Alimentele rămân aici.');}else status(navigator.onLine?e.message:'Se va păstra și pe celelalte dispozitive când revine conexiunea.');}
  finally{await store.syncUpdate((_,m)=>{if(m.lease?.owner===owner)m.lease=null;}).catch(()=>{});busy=false;if(again)schedule(1500);}
}
function schedule(delay=1200){clearTimeout(timer);timer=setTimeout(()=>run(),delay);}
async function connect(keepLocal){if(!session)throw Error('Deschide legătura de conectare din email.');await store.changeHousehold(session.household_id,{keepLocal});$('cloud-enable').hidden=true;changed();schedule(0);}
async function incoming(){
  const confirmation=new URLSearchParams(location.hash.slice(1)).get('confirma');if(confirmation){history.replaceState(null,'','/acasa/');await incomingPrivacy(confirmation);return;}
  const values=new URLSearchParams(location.hash.slice(1)),token=values.get('conectare'),invite=values.get('familie'),oauth=values.get('oauth');if(!token&&!invite&&!oauth)return;history.replaceState(null,'','/acasa/');
  if(oauth){if(!/^[a-f0-9]{32}$/.test(oauth))throw Error('Cererea aplicației nu este validă.');sessionStorage.setItem('oa-oauth-return',oauth);if(session){sessionStorage.removeItem('oa-oauth-return');location.assign('/api/v2/home/oauth/authorize?request='+oauth);}else{$('settings').showModal();$('cloud-email').focus();status('Conectează-te prin email pentru a alege accesul aplicației.');}return;}
  if(invite){if(!/^[a-f0-9]{64}$/.test(invite))throw Error('Invitația nu este validă.');await api('invites/preview',{body:{token:invite}});inviteToken=invite;await store.syncUpdate((_,m)=>m.pending_invite=invite);if(!session){$('settings').showModal();$('cloud-email').focus();status('Intră prin email pentru a accepta invitația.');return;}await showInvite();return;}
  if(!/^[a-f0-9]{64}$/.test(token))throw Error('Legătura nu este validă.');const preview=await api('login/preview',{body:{token}});loginToken=token;$('cloud-login-email').textContent=preview.email;$('cloud-login-keep').checked=true;$('cloud-login-dialog').showModal();
}
async function showInvite(){const meta=await store.syncRead();inviteToken??=meta.pending_invite;if(!inviteToken||!session)return;const preview=await api('invites/preview',{body:{token:inviteToken}});$('cloud-join-name').textContent=preview.name;$('cloud-join-keep').checked=false;$('cloud-join-dialog').showModal();}
async function showConflicts(){const meta=await store.syncRead(),box=$('cloud-conflicts');box.replaceChildren();
  for(const conflict of meta.conflicts){const local=(await store.read()).items.find(i=>i.id===conflict.item_id),section=document.createElement('section'),title=document.createElement('h3');title.textContent=local?.name||conflict.current.item?.name||'Aliment';section.append(title);
    const text=document.createElement('p');text.textContent='Alimentul a fost modificat și pe alt dispozitiv. Ce variantă păstrăm?';section.append(text);
    for(const[choice,label]of[['remote','Varianta familiei'],['local','Varianta de aici']]){const b=document.createElement('button');b.className='button secondary';b.type='button';b.textContent=label;b.addEventListener('click',async()=>{b.disabled=true;try{const raw=conflict.current.item,hash=raw?await C.digest(C.contentText(raw)):null;await store.syncUpdate((s,m)=>{const current=m.conflicts.find(c=>c.item_id===conflict.item_id);if(!current||current.current.server_revision!==conflict.current.server_revision)throw Error('Alimentul s-a schimbat. Redeschide alegerea.');C.resolve(s,m,conflict.item_id,choice,hash);},{notify:true});changed();await showConflicts();schedule(0);}catch(e){status(e.message);}finally{b.disabled=false;}});section.append(b);}box.append(section);
  }$('cloud-conflict').hidden=!meta.conflicts.length;if(!meta.conflicts.length){$('cloud-conflict-dialog').close();return;}if(!$('cloud-conflict-dialog').open)$('cloud-conflict-dialog').showModal();
}
export async function init(onChanged=()=>{}){
  if(initialized)return;initialized=true;changed=onChanged;
  initPrivacy({api,session:()=>session,reload:loadSession,changed,status});
  $('cloud-login-form').addEventListener('submit',async e=>{e.preventDefault();const button=e.submitter;button.disabled=true;try{const r=await api('login/request',{body:{email:$('cloud-email').value}});$('cloud-login-note').textContent=r.message;}catch(e){$('cloud-login-note').textContent=e.message;}finally{button.disabled=false;}});
  $('cloud-confirm-login').addEventListener('click',async()=>{const b=$('cloud-confirm-login');if(!loginToken&&!session)return;b.disabled=true;try{let authorizationUrl=null;if(loginToken){const connected=await api('login/confirm',{body:{token:loginToken,device_name:/Mobile|Android|iPhone/i.test(navigator.userAgent)?'Telefonul meu':'Browserul meu'}});authorizationUrl=connected.authorization_url;loginToken=null;await loadSession();}await connect($('cloud-login-keep').checked);$('cloud-login-dialog').close();if($('settings').open)$('settings').close();if(typeof authorizationUrl==='string'&&/^\/api\/v2\/home\/oauth\/authorize\?request=[a-f0-9]{32}$/.test(authorizationUrl)){sessionStorage.removeItem('oa-oauth-return');location.assign(authorizationUrl);return;}const oauth=sessionStorage.getItem('oa-oauth-return');if(oauth&&/^[a-f0-9]{32}$/.test(oauth)){sessionStorage.removeItem('oa-oauth-return');location.assign('/api/v2/home/oauth/authorize?request='+oauth);return;}await showInvite();}catch(e){$('cloud-login-error').textContent=e.message;}finally{b.disabled=false;}});
  $('cloud-enable').addEventListener('click',async()=>{try{await connect(true);}catch(e){status(e.message);}});
  $('cloud-conflict').addEventListener('click',()=>showConflicts().catch(e=>status(e.message)));
  $('cloud-join').addEventListener('click',async()=>{const b=$('cloud-join');b.disabled=true;try{await api('join',{body:{token:inviteToken}});await loadSession();await connect($('cloud-join-keep').checked);await store.syncUpdate((_,m)=>delete m.pending_invite);inviteToken=null;$('cloud-join-dialog').close();if($('settings').open)$('settings').close();}catch(e){$('cloud-join-error').textContent=e.message;}finally{b.disabled=false;}});
  $('cloud-invite').addEventListener('click',async()=>{try{const result=await api('invites',{body:{}});$('cloud-invite-url').value=result.url;$('cloud-invite-result').hidden=false;try{await navigator.clipboard.writeText(result.url);$('cloud-invite-note').textContent='Legătura a fost copiată. Este valabilă 7 zile și se folosește o singură dată.';}catch{$('cloud-invite-note').textContent='Copiază legătura și trimite-o persoanei pe care vrei să o inviți.';}}catch(e){status(e.message);}});
  $('cloud-logout').addEventListener('click',async()=>{try{await api('logout',{body:{}});await store.syncUpdate((_,m)=>{m.enabled=false;m.lease=null;});session=null;await loadSession();status('Alimentele au rămas pe acest dispozitiv.');}catch(e){status(e.message);}});
  $('cloud-local').addEventListener('click',async()=>{try{await store.changeHousehold(null,{keepLocal:false});$('cloud-enable').hidden=false;status('Folosești alimentele salvate numai pe acest dispozitiv.');changed();}catch(e){status(e.message);}});
  $('cloud-devices').addEventListener('click',async e=>{const id=e.target.closest('[data-cloud-revoke]')?.dataset.cloudRevoke;if(!id)return;try{await api('devices/'+id+'/revoke',{body:{}});await loadSession();}catch(e){status(e.message);}});
  $('cloud-family-details').addEventListener('toggle',()=>{if($('cloud-family-details').open)familyDetails().catch(e=>status(e.message));});
  $('cloud-family-edit').addEventListener('submit',async e=>{e.preventDefault();const b=e.submitter;b.disabled=true;try{await api('rename',{body:{name:$('cloud-family-input').value}});await loadSession();await familyDetails();status('Denumirea a fost păstrată.');}catch(e){status(e.message);}finally{b.disabled=false;}});
  $('cloud-members').addEventListener('click',async e=>{const b=e.target.closest('[data-cloud-remove]');if(!b)return;b.disabled=true;try{await api('members/'+b.dataset.cloudRemove+'/remove',{body:{}});await familyDetails();status('Accesul la familie a fost retras. Copiile deja salvate pe alte dispozitive pot rămâne acolo.');}catch(e){status(e.message);}finally{b.disabled=false;}});
  $('cloud-families').addEventListener('click',async e=>{const b=e.target.closest('[data-cloud-switch]');if(!b)return;if(busy){status('Așteaptă încheierea sincronizării, apoi deschide familia.');return;}b.disabled=true;try{await api('switch',{body:{household_id:b.dataset.cloudSwitch}});await loadSession();await connect(false);await familyDetails();status('Familia este deschisă. Alimentele celuilalt spațiu sunt păstrate separat.');}catch(e){status(e.message);}finally{b.disabled=false;}});
  window.addEventListener('home-changed',()=>schedule());window.addEventListener('online',()=>schedule(0));window.addEventListener('hashchange',()=>incoming().catch(e=>status(e.message)));setInterval(()=>{if(!document.hidden)schedule(0);},30000);
  store.setPhotoLoader(async id=>{const meta=await store.syncRead();if(!session||!meta.enabled||meta.household_id!==session.household_id||!navigator.onLine)return null;const key=meta.household_id+':'+id;if(!downloads.has(key))downloads.set(key,(async()=>{try{const blob=await api('photos/'+id,{raw:true});if(blob.type!=='image/jpeg'||blob.size>1000000)return null;await store.putPhoto(id,blob);return blob;}catch{setTimeout(()=>downloads.delete(key),30000);return null;}})());return downloads.get(key);});
  try{await loadSession();await incoming();const meta=await store.syncRead();if(session&&meta.enabled&&meta.household_id===session.household_id)schedule(0);if(session&&meta.pending_invite)await showInvite();}catch(e){status(navigator.onLine?e.message:'Salvat pe acest dispozitiv.');}
}
export function currentStatus(){return message;}
