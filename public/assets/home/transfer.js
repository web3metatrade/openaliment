import {importState} from './domain.js?v=8';
import * as store from './store.js?v=10';
import {cleanRecord} from './external-domain.js?v=10';
const hash=async value=>[...new Uint8Array(await crypto.subtle.digest('SHA-256',value))].map(n=>n.toString(16).padStart(2,'0')).join('');
const encode=bytes=>{let s='';for(let i=0;i<bytes.length;i+=16384)s+=String.fromCharCode(...bytes.subarray(i,i+16384));return btoa(s);};
export async function exportCopy() {
  const inventory=await store.read(),photos=[],ids=new Set(inventory.items.map(i=>i.photo_id).filter(Boolean));
  for(const e of inventory.events)for(const i of [e.before,e.after])if(i?.photo_id)ids.add(i.photo_id);
  for(const id of ids){const blob=await store.photo(id);if(!blob)throw Error('O fotografie lipsește din stocarea dispozitivului. Copia nu a fost creată.');const bytes=new Uint8Array(await blob.arrayBuffer());photos.push({id,mime:blob.type,sha256:await hash(bytes),content_base64:encode(bytes)});}
  const refs=new Set();for(const item of inventory.items)if(item.profile.source?.kind==='external')refs.add(item.profile.source.version_id);
  for(const e of inventory.events)for(const i of [e.before,e.after])if(i?.profile.source?.kind==='external')refs.add(i.profile.source.version_id);
  const external_records=(await store.externalList()).filter(r=>refs.has(r.id));
  const data={inventory,photos,...(external_records.length?{external_records}: {})},serialized=JSON.stringify(data);
  if(new TextEncoder().encode(serialized).length>64000000)throw Error('Copia depășește limita de 64 MB. Nu șterge datele de pe dispozitiv.');
  return new Blob([JSON.stringify({schema_version:external_records.length?'openaliment.home.transfer.v2':'openaliment.home.transfer.v1',exported_at:new Date().toISOString(),data,data_sha256:await hash(new TextEncoder().encode(serialized))})],{type:'application/json'});
}
export async function importCopy(file) {
  if(file.size>64000000)throw Error('Copia trebuie să fie mai mică de 64 MB.');let raw;try{raw=JSON.parse(await file.text());}catch{throw Error('Fișierul nu este o copie validă.');}
  if(!['openaliment.home.transfer.v1','openaliment.home.transfer.v2'].includes(raw.schema_version)||!raw.data||typeof raw.data_sha256!=='string')throw Error('Formatul copiei nu este acceptat.');
  if(await hash(new TextEncoder().encode(JSON.stringify(raw.data)))!==raw.data_sha256)throw Error('Copia este incompletă sau modificată.');
  const incoming=importState(raw.data.inventory),photos=raw.data.photos;
  const external_records=raw.schema_version==='openaliment.home.transfer.v2'?raw.data.external_records:[];
  if(!Array.isArray(external_records)||external_records.length>128)throw Error('Lista fișelor externe nu este validă.');
  const external_checked=external_records.map(cleanRecord);if(new Set(external_checked.map(r=>r.id)).size!==external_checked.length)throw Error('Fișe externe duplicate.');
  if(!Array.isArray(photos)||photos.length>5000)throw Error('Lista fotografiilor nu este validă.');
  const checked=new Map();for(const p of photos){if(!/^[a-f0-9]{32}$/.test(p.id)||checked.has(p.id)||p.mime!=='image/jpeg'||typeof p.content_base64!=='string'||p.content_base64.length>1400000)throw Error('Fotografie incorectă.');let bytes;try{bytes=Uint8Array.from(atob(p.content_base64),c=>c.charCodeAt(0));}catch{throw Error('Fotografie deteriorată.');}if(bytes[0]!==255||bytes[1]!==216||await hash(bytes)!==p.sha256)throw Error('Fotografie deteriorată.');checked.set(p.id,new Blob([bytes],{type:'image/jpeg'}));}
  for(const [key,blob]of checked){const existing=await store.photo(key);if(existing&&await hash(await existing.arrayBuffer())!==await hash(await blob.arrayBuffer()))throw Error('O fotografie existentă diferă de cea din copie. Importul a fost oprit.');}
  for(const item of incoming.items)if(item.photo_id&&!checked.has(item.photo_id))throw Error('Lipsește o fotografie din copie.');
  for(const e of incoming.events)for(const item of [e.before,e.after])if(item?.photo_id&&!checked.has(item.photo_id))throw Error('Lipsește o fotografie din istoricul copiei.');
  // Merge only after the entire package has been validated; IDs preserve duplicate detection.
  const added=await store.update(current=>{
    const existing=new Set(current.items.map(i=>i.id)),events=new Set(current.events.map(e=>e.id)),newIds=new Set();
    if(current.items.length+incoming.items.filter(i=>!existing.has(i.id)).length>2000)throw Error('Inventarul ar depăși limita de ambalaje.');
    let added=0;
    for(const i of incoming.items)if(!existing.has(i.id)){i.imported=true;i.profile.alerts_checked_at=null;current.items.push(i);existing.add(i.id);newIds.add(i.id);added++;}
    for(const e of incoming.events)if(!events.has(e.id)){current.events.push(e);events.add(e.id);}
    current.preferences.allergens=[...new Set([...current.preferences.allergens,...incoming.preferences.allergens])];
    const shopping=new Set(current.shopping.map(i=>i.name));for(const s of incoming.shopping)if(!shopping.has(s.name)){current.shopping.push(s);shopping.add(s.name);}
    return added;
  },checked);
  for(const record of external_checked)try{await store.externalWrite({...record,sensitive_usable:false});}catch{}
  return added;
}
