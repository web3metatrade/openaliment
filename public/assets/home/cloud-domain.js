import * as D from './domain.js?v=8';
const overlay=['alerts','alerts_checked_at','alert_scope','batch_state','notices'];
const validId=v=>typeof v==='string'&&/^[a-f0-9]{32}$/.test(v);
const counter=v=>Number.isSafeInteger(v)&&v>=0;
function snapshot(r,id){if(!r||!counter(r.server_revision)||typeof r.deleted!=='boolean'||(!r.deleted&&(!r.item||r.item.id!==id))||r.deleted&&r.item!==null)throw Error('Răspuns de sincronizare incorect.');}
function validatePage(page){if(!page||!validId(page.household_id)||!counter(page.cursor)||!Array.isArray(page.changes)||page.changes.length>100)throw Error('Răspuns de sincronizare incorect.');for(const c of page.changes){if(c.kind==='item'){if(!validId(c.value?.item_id))throw Error('Referință de ambalaj incorectă.');snapshot(c.value,c.value.item_id);}else if(c.kind!=='history'||!validId(c.value?.id))throw Error('Istoric de sincronizare incorect.');}}
const stable=v=>v===null||typeof v!=='object'?JSON.stringify(v):Array.isArray(v)?'['+v.map(stable).join(',')+']':'{'+Object.keys(v).sort().map(k=>JSON.stringify(k)+':'+stable(v[k])).join(',')+'}';
export function contentText(item){if(item===null)return 'null';const value=D.cleanItem(item);delete value.revision;delete value.updated_at;for(const k of overlay)delete value.profile[k];return stable(value);}
export async function digest(text){return [...new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text)))].map(x=>x.toString(16).padStart(2,'0')).join('');}
export async function fingerprints(items){return new Map(await Promise.all(items.map(async item=>{const text=contentText(item);return [item.id,{text,hash:await digest(text)}];})));}
export async function prepare(inventory,meta){
  const hashes=await fingerprints(inventory.items),ops=[],seen=new Set();
  for(const item of inventory.items){seen.add(item.id);if(meta.conflicts.some(c=>c.item_id===item.id))continue;const hash=hashes.get(item.id).hash;if(meta.ack[item.id]?.hash!==hash)ops.push({event_id:D.id(),item_id:item.id,expected_revision:meta.ack[item.id]?.revision||0,kind:'put',item:D.cleanItem(item),local_hash:hash});if(ops.length===10)break;}
  if(ops.length<10)for(const [id,ack]of Object.entries(meta.ack))if(!inventory.items.some(i=>i.id===id)&&ack.hash!==null&&!meta.conflicts.some(c=>c.item_id===id)){ops.push({event_id:D.id(),item_id:id,expected_revision:ack.revision,kind:'delete',local_hash:null});if(ops.length===10)break;}
  const accepted=new Set(meta.history_ack),history=inventory.events.filter(e=>!accepted.has(e.id)).slice(0,10).map(e=>structuredClone(e));return ops.length||history.length?{operations:ops,history}:null;
}
export function wire(outbox){return {operations:outbox.operations.map(({local_hash,...op})=>op),history:outbox.history};}
export function acknowledge(meta,outbox,response){
  if(!response||!Array.isArray(response.accepted)||!Array.isArray(response.history_accepted)||!Array.isArray(response.conflicts))throw Error('Confirmare de sincronizare incorectă.');
  for(const a of response.accepted){const op=outbox.operations.find(o=>o.event_id===a.event_id);if(!op||a.item_id!==op.item_id||!counter(a.server_revision)||a.server_revision<1)throw Error('Confirmare de sincronizare incorectă.');}
  for(const id of response.history_accepted)if(!outbox.history.some(e=>e.id===id))throw Error('Confirmare de istoric incorectă.');
  for(const c of response.conflicts){if(!outbox.operations.some(o=>o.item_id===c.item_id))throw Error('Conflict de sincronizare incorect.');snapshot(c.current,c.item_id);}
  for(const accepted of response.accepted||[]){const op=outbox.operations.find(o=>o.event_id===accepted.event_id);if(op)meta.ack[op.item_id]={hash:op.local_hash,revision:accepted.server_revision};}
  meta.history_ack=[...new Set([...meta.history_ack,...(response.history_accepted||[])])];
  for(const conflict of response.conflicts||[]){const index=meta.conflicts.findIndex(c=>c.item_id===conflict.item_id);if(index<0)meta.conflicts.push(conflict);else meta.conflicts[index]=conflict;}
  meta.outbox=null;
}
export async function pageFingerprints(page){validatePage(page);const items=page.changes.filter(c=>c.kind==='item'&&!c.value.deleted).map(c=>c.value.item);return fingerprints(items);}
export function applyPage(inventory,meta,page,localHashes,remoteHashes){
  validatePage(page);if(page.cursor<meta.cursor)throw Error('Cursorul de sincronizare este mai vechi decât datele salvate.');
  if(page.household_id!==meta.household_id)throw Error('Sincronizarea aparține altei familii.');
  const eventIds=new Set(inventory.events.map(e=>e.id)),historyAck=new Set(meta.history_ack);
  for(const change of page.changes){
    if(change.kind==='history'){
      const clean=D.importState({schema_version:D.VERSION,items:[],events:[change.value],preferences:{allergens:[]},shopping:[]}).events[0];if(!eventIds.has(clean.id)){inventory.events.push(clean);eventIds.add(clean.id);}historyAck.add(clean.id);continue;
    }
    const r=change.value,id=r.item_id,index=inventory.items.findIndex(i=>i.id===id),current=index<0?null:inventory.items[index],known=meta.ack[id],snapshot=localHashes.get(id),currentText=contentText(current);
    const localHash=current===null?null:snapshot&&snapshot.text===currentText?snapshot.hash:undefined,remoteHash=r.deleted?null:remoteHashes.get(id)?.hash;
    if(known&&known.revision>r.server_revision)continue;
    if(known&&known.revision===r.server_revision&&known.hash===remoteHash)continue;
    const dirty=localHash===undefined||(known?localHash!==known.hash:current!==null);
    if(dirty&&localHash!==remoteHash){const conflict={item_id:id,current:r};const old=meta.conflicts.findIndex(c=>c.item_id===id);if(old<0)meta.conflicts.push(conflict);else meta.conflicts[old]=conflict;continue;}
    if(r.deleted){if(index>=0)inventory.items.splice(index,1);}
    else{const next=D.cleanItem(r.item);next.profile.alerts_checked_at=null;if(current){for(const k of overlay)if(Object.hasOwn(current.profile,k))next.profile[k]=structuredClone(current.profile[k]);if(currentText!==contentText(next))next.revision=Math.max(current.revision,next.revision)+1;}
      if(index>=0)inventory.items[index]=next;else{if(inventory.items.length>=2000)throw Error('Inventarul ar depăși 2.000 de ambalaje.');inventory.items.push(next);}}
    meta.ack[id]={hash:remoteHash,revision:r.server_revision};meta.conflicts=meta.conflicts.filter(c=>c.item_id!==id);
  }
  // Undo may arrive before the original event when an old client is backfilled.
  const events=new Map(inventory.events.map(e=>[e.id,e]));for(const e of inventory.events)if(e.type==='undo'&&e.undoes){const target=events.get(e.undoes);if(target)target.undone_at=e.created_at;}
  meta.history_ack=[...historyAck];
  meta.cursor=page.cursor;
}
export function resolve(inventory,meta,id,choice,hash){
  const c=meta.conflicts.find(c=>c.item_id===id);if(!c)return;const r=c.current,remote=r.deleted?null:D.cleanItem(r.item);meta.ack[id]={hash,revision:r.server_revision};
  if(choice==='remote'){const index=inventory.items.findIndex(i=>i.id===id);if(!remote){if(index>=0)inventory.items.splice(index,1);}else{const current=index>=0?inventory.items[index]:null;if(current){for(const k of overlay)if(Object.hasOwn(current.profile,k))remote.profile[k]=structuredClone(current.profile[k]);remote.revision=Math.max(current.revision,remote.revision)+1;inventory.items[index]=remote;}else inventory.items.push(remote);}}
  meta.conflicts=meta.conflicts.filter(c=>c.item_id!==id);meta.outbox=null;
}
