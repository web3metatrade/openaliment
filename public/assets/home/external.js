import * as store from './store.js?v=10';
import {cleanRecord,enrich} from './external-domain.js?v=10';
const pending=new Map(),records=new Map();let loaded=false;
async function json(url,options={}){const response=await fetch(url,{credentials:'omit',cache:'no-store',signal:AbortSignal.timeout(4500),...options});if(!response.ok)throw Error('Sursa externă nu este disponibilă.');return response.json();}
export async function remember(raw){const record=cleanRecord(raw);records.set(record.id,record);try{await store.externalWrite(record);}catch{}try{await store.update(s=>enrich(s,record));globalThis.dispatchEvent?.(new Event('external-ready'));}catch{/* Supplement storage cannot prevent adding food. */}return record;}
export async function forProfile(profile){
  if(!profile?.gtin14||profile.simulation)return null;
  if(!loaded){loaded=true;try{for(const raw of await store.externalList()){const r=cleanRecord(raw);records.set(r.id,r);}}catch{}}
  if(profile.source?.kind!=='external')return [...records.values()].filter(r=>r.gtin14===profile.gtin14).sort((a,b)=>String(b.observed_at).localeCompare(String(a.observed_at)))[0]||null;
  if(!profile.source.version_id)return null;
  const key=profile.source.version_id;if(records.has(key))return records.get(key);
  try{const cached=await store.externalRead(key);if(cached){const r=cleanRecord(cached);records.set(key,r);return r;}if(navigator.onLine)return await remember(await json('/api/v2/public/external-revisions/'+key));}catch{}return null;
}
export async function resolve(code){
  const bundle=await json('/api/v2/public/food/resolve',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({code})});
  if(bundle.schema_version!=='openaliment.consumer-resolution.v1'||!bundle.profile)throw Error('Răspuns neacceptat.');
  if(bundle.supplement)await remember(bundle.supplement);
  else if(bundle.profile.source?.kind==='unavailable'&&bundle.profile.gtin14)void lookup(bundle.profile.gtin14);
  return bundle.profile;
}
export async function lookup(gtin){
  if(pending.has(gtin))return pending.get(gtin);
  const task=(async()=>{try{
    let result=await json('/api/v2/public/food/lookups',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({gtin})});
    for(let n=0;n<2&&result.id&&!result.supplement&&['queued','running','retry'].includes(result.state);n++){await new Promise(r=>setTimeout(r,1500));result=await json('/api/v2/public/food/lookups/'+result.id);}
    if(result.supplement)await remember(result.supplement);
  }catch{}finally{pending.delete(gtin);}})();pending.set(gtin,task);return task;
}
export async function hydrate(){for(const record of records.values())await store.update(s=>enrich(s,record));}

export async function refresh(){if(!navigator.onLine)return;const state=await store.read(),gtins=[...new Set(state.items.filter(i=>i.state!=='consumed'&&i.profile.source?.kind==='unavailable'&&i.profile.gtin14).map(i=>i.profile.gtin14))].slice(0,5);for(const gtin of gtins)try{await resolve(gtin);}catch{}}
