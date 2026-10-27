// External observations never become producer declarations or lot-specific facts.
import {id,undo as originalUndo} from './domain.js?v=8';
export function cleanRecord(raw){
  if(!raw||raw.schema_version!=='openaliment.external-food.v1'||raw.provider!=='openfoodfacts'||!/^[a-f0-9]{32}$/.test(raw.id)||!/^\d{14}$/.test(raw.gtin14)||JSON.stringify(raw).length>262144)throw Error('Fișa externă nu este validă.');
  if(!raw.licenses?.database?.id||!Array.isArray(raw.images)||!Array.isArray(raw.nutrition?.sets)||!Array.isArray(raw.allergens?.contains)||!Array.isArray(raw.allergens?.may_contain))throw Error('Proveniența fișei externe lipsește.');
  for(const set of raw.nutrition.sets)if(!['per_100_g','per_100_ml','per_portion'].includes(set.basis)||!['reported','estimated'].includes(set.kind)||!set.values||Object.values(set.values).some(v=>!Number.isFinite(v)||v<0))throw Error('Valorile externe nu sunt valide.');
  const result=structuredClone(raw),age=Date.now()-Date.parse(result.observed_at||result.fetched_at);
  result.stale=Boolean(result.stale)||!Number.isFinite(age)||age>86400000;
  result.sensitive_usable=result.sensitive_usable===true&&Number.isFinite(age)&&age>=-300000&&age<30*86400000;
  result.images=result.images.slice(0,4).map(image=>({...image,local_url:localImage(image.local_url)}));return result;
}
export function warnings(record,preferences=[]){
  if(!record||!preferences.length)return [];
  const a=record.allergens;if(!record.sensitive_usable||!['reported','derived'].includes(a.status))return ['Open Food Facts: alergenii nu sunt confirmați. Verifică ambalajul.'];
  const contains=preferences.filter(x=>a.contains.includes(x)),traces=preferences.filter(x=>a.may_contain.includes(x));
  return [...(contains.length?['Open Food Facts semnalează alergeni din preferințele tale. Verifică ambalajul.']:[]),...(traces.length?['Open Food Facts semnalează posibile urme de alergeni. Verifică ambalajul.']:[]),...(!contains.length&&!traces.length?['Absența alergenilor nu este confirmată de această fișă. Verifică ambalajul.']:[])];
}
export function carbs(record,profile,amount,unit){
  if(!record?.sensitive_usable||profile?.batch_id||profile?.lot_code||Object.keys(profile?.gs1_qualifiers||{}).some(k=>['10','21','22','235'].includes(k))||!Number.isFinite(amount)||amount<=0||amount>100000)return null;
  const basis=unit==='g'?'per_100_g':unit==='ml'?'per_100_ml':null;
  const sets=record.nutrition.sets.filter(s=>s.kind==='reported'&&s.preparation==='as_sold'&&s.basis===basis&&Number.isFinite(s.values.carbohydrate_g)&&!s.modifiers?.carbohydrate_g);
  if(!sets.length||sets.some(s=>s.values.carbohydrate_g!==sets[0].values.carbohydrate_g))return null;
  return Math.round(sets[0].values.carbohydrate_g*amount)/100;
}
export function enrich(state,record,now=new Date().toISOString()){
  let count=0;
  for(const item of state.items){
    if(item.state==='consumed'||item.profile?.gtin14!==record.gtin14||item.profile.source?.kind!=='unavailable')continue;
    const before=structuredClone(item),p=item.profile;p.name=record.name||p.name;p.source={kind:'external',name:'Open Food Facts',version_id:record.id,version:record.revision,sha256:record.sha256,checked_at:record.observed_at,license:'ODbL-1.0',url:record.source_url};
    if(item.name===before.profile.name)item.name=p.name;
    item.revision++;item.updated_at=now;state.events.push({id:id(),type:'edit',item_id:item.id,before,after:structuredClone(item),created_at:now});count++;
  }
  return count;
}
export function undo(state,eventId,now=new Date().toISOString()){
  const event=state.events.find(e=>e.id===eventId),index=state.items.findIndex(i=>i.id===event?.item_id),current=state.items[index];
  if(event?.type!=='add'||event.undone_at||!current||current.revision!==event.after.revision+1||event.after.profile.source?.kind!=='unavailable'||current.profile.source?.kind!=='external')return originalUndo(state,eventId,now);
  const content=item=>{const value=structuredClone(item);delete value.revision;delete value.updated_at;if(value.name===value.profile.name)delete value.name;delete value.profile.name;delete value.profile.source;for(const k of ['alerts','alerts_checked_at','alert_scope','batch_state','notices'])delete value.profile[k];return JSON.stringify(value);};
  const latest=state.events.filter(e=>e.item_id===current.id).at(-1);
  if(latest?.type!=='edit'||latest.before?.revision!==event.after.revision||content(event.after)!==content(current))return originalUndo(state,eventId,now);
  // Only source enrichment has intervened; an intentional personal edit still prevents stale undo.
  state.items.splice(index,1);event.undone_at=now;state.events.push({id:id(),type:'undo',item_id:event.item_id,before:structuredClone(current),after:null,created_at:now,undoes:event.id});
}

function localImage(raw){try{const base=globalThis.location?.origin||'https://openaliment.ro',u=new URL(raw,base);return u.origin===base&&/^\/api\/v2\/public\/external-assets\/[a-f0-9]{32}$/.test(u.pathname)&&!u.search&&!u.hash?u.pathname:null;}catch{return null;}}
