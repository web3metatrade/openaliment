export const ALLERGENS = Object.freeze({gluten:'gluten',crustaceans:'crustacee',eggs:'ouă',fish:'pește',peanuts:'arahide',soy:'soia',milk:'lapte',nuts:'fructe cu coajă lemnoasă',celery:'țelină',mustard:'muștar',sesame:'susan',sulphites:'sulfiți',lupin:'lupin',molluscs:'moluște'});
export const VERSION = 'openaliment.home.v1';
export const id = () => [...crypto.getRandomValues(new Uint8Array(16))].map(n=>n.toString(16).padStart(2,'0')).join('');
export const initial = () => ({schema_version:VERSION,items:[],events:[],preferences:{allergens:[]},shopping:[]});
export function today(now=new Date()) {
  const p=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Bucharest',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(now);
  const part=k=>p.find(v=>v.type===k).value;return `${part('year')}-${part('month')}-${part('day')}`;
}
export function checkedDate(input) {
  if(input===null)return null;
  if(!input||!['day','month','year'].includes(input.precision)||!['use_by','best_before','reminder'].includes(input.kind)||!['producer','barcode','user','receipt'].includes(input.source))throw Error('Data nu este validă.');
  const re={day:/^\d{4}-\d{2}-\d{2}$/,month:/^\d{4}-\d{2}$/,year:/^\d{4}$/};
  if(typeof input.value!=='string'||!re[input.precision].test(input.value))throw Error('Data nu este validă.');
  const [y,m=1,d=1]=input.value.split('-').map(Number),check=new Date(Date.UTC(y,m-1,d));
  if(y<1900||y>2200||check.getUTCFullYear()!==y||check.getUTCMonth()!==m-1||check.getUTCDate()!==d)throw Error('Data nu este validă.');
  return {kind:input.kind,value:input.value,precision:input.precision,source:input.source,...(typeof input.raw==='string'?{raw:input.raw.slice(0,80)}:{})};
}
export function dateEnd(value) {
  const d=checkedDate(value);if(!d)return null;
  const [y,m=1,day=1]=d.value.split('-').map(Number);
  return d.precision==='day'?Date.UTC(y,m-1,day):d.precision==='month'?Date.UTC(y,m,0):Date.UTC(y,12,0);
}
export function dateText(value) {
  if(!value)return 'Data nu este cunoscută';
  const [y,m,d]=value.value.split('-');
  if(value.precision==='year')return y;
  if(value.precision==='month')return `${m}.${y}`;
  return `${d}.${m}.${y}`;
}
export function status(item,day=today()) {
  if(item.state==='consumed')return {rank:9,tone:'muted',text:'Terminat'};
  const p=item.profile;
  if(p.batch_state==='recalled')return {rank:0,tone:'danger',text:'Lot retras · nu folosi'};
  if((p.alerts||[]).length)return {rank:0,tone:'danger',text:p.alert_scope==='batch'?'Alertă pentru acest lot':'Verifică lotul · există alerte'};
  if(p.batch_state==='on_hold')return {rank:0,tone:'danger',text:'Lot în așteptarea clarificărilor'};
  const expiry=item.expiry;
  if(!expiry)return {rank:5,tone:'muted',text:'Data nu este cunoscută'};
  const days=Math.round((dateEnd(expiry)-Date.parse(day+'T00:00:00Z'))/86400000);
  if(expiry.kind==='reminder')return {rank:days<=0?2:4,tone:'neutral',text:days<=0?'Memento pentru azi':`Memento · ${dateText(expiry)}`};
  if(days<0)return {rank:1,tone:expiry.kind==='use_by'?'danger':'attention',text:expiry.kind==='use_by'?'Termen depășit · nu consuma':'Data preferată a trecut'};
  if(days===0)return {rank:2,tone:'attention',text:expiry.kind==='use_by'?'A se consuma până azi':'De preferință înainte de azi'};
  if(days===1)return {rank:3,tone:'attention',text:expiry.kind==='use_by'?'A se consuma până mâine':'De preferință înainte de mâine'};
  return {rank:4,tone:'neutral',text:`${expiry.kind==='use_by'?'Până la':'De preferință înainte de'} ${dateText(expiry)}`};
}
export function allergenWarnings(profile,selected) {
  const a=profile.food_data?.allergens;
  if(!selected.length)return [];
  if(!a||a.status!=='declared')return ['Informațiile despre alergeni nu sunt verificate. Citește eticheta.'];
  const contains=selected.filter(k=>a.contains?.includes(k)),maybe=selected.filter(k=>a.may_contain?.includes(k));
  const out=[];
  if(contains.length)out.push('Conține: '+contains.map(k=>ALLERGENS[k]).join(', ')+'.');
  if(maybe.length)out.push('Poate conține: '+maybe.map(k=>ALLERGENS[k]).join(', ')+'.');
  return out;
}
export function carbs(profile,amount,unit) {
  const n=profile.food_data?.nutrition;
  if(!n||n.status!=='declared'||!Number.isFinite(n.values?.carbohydrate_g)||!Number.isFinite(amount)||amount<=0)return null;
  let base;
  if(n.basis==='per_100_g'&&unit==='g')base=100;
  else if(n.basis==='per_100_ml'&&unit==='ml')base=100;
  else if(n.basis==='per_portion'&&n.portion?.unit===unit&&n.portion.amount>0)base=n.portion.amount;
  else return null;
  return {grams:Math.round(n.values.carbohydrate_g*amount/base*100)/100,jurisdiction:n.jurisdiction,preparation:n.preparation};
}
export function gs1Qualifiers(value) {
  if(value===null||value===undefined)return null;
  if(typeof value!=='object'||Array.isArray(value))throw Error('Calificatori GS1 incorecți.');
  const out={};
  for(const ai of Object.keys(value)) {
    const v=value[ai];if(!['22','10','21','235'].includes(ai)||typeof v!=='string'||!v.length||v.length>(ai==='235'?28:20)||!/^[!"%&'()*+,\-./:;<=>?0-9A-Z_a-z]+$/.test(v))throw Error('Calificator GS1 incorect.');
  }
  if(Object.hasOwn(value,'235')&&Object.keys(value).length>1)throw Error('TPX se utilizează separat de ceilalți calificatori.');
  for(const ai of ['22','10','21','235'])if(Object.hasOwn(value,ai))out[ai]=value[ai];
  return Object.keys(out).length?out:null;
}
export function scanIdentity(parsed,code='') {
  if(!parsed.gtin14)return JSON.stringify(['native',code]);
  const q=parsed.gs1_qualifiers||{};
  if(q['21'])return JSON.stringify([parsed.gtin14,'21',q['21']]);
  if(q['235'])return JSON.stringify([parsed.gtin14,'235',q['235']]);
  return JSON.stringify([parsed.gtin14,q['22']??null,parsed.lot_code??null]);
}
export function cachedProfile(raw,parsed) {
  const p=cleanProfile(raw);p.alerts_checked_at=null;
  if(parsed.gtin14){p.gs1_qualifiers=gs1Qualifiers(parsed.gs1_qualifiers);p.observed_dates=parsed.observed_dates??[];if(p.expiry?.source!=='producer')p.expiry=parsed.expiry;}
  return p;
}
export function unknownProfile({gtin14=null,lot_code=null,expiry=null,gs1_qualifiers=null,observed_dates=[]}={}) {
  return {schema_version:'openaliment.consumer-product.v1',name:'Aliment',gtin14,lot_code,gs1_qualifiers:gs1Qualifiers(gs1_qualifiers),observed_dates,source:{kind:'unavailable'},food_data:{schema_version:'openaliment.food.v1',allergens:{status:'unknown',contains:[],may_contain:[]},nutrition:{status:'unknown',basis:null,preparation:null,jurisdiction:null,values:null,portion:null},after_opening:null},expiry,alerts:[],alerts_checked_at:null,alert_scope:'unknown',notices:[],simulation:false};
}
export function parseCode(code,base=globalThis.location?.origin||'https://openaliment.ro',year=new Date().getFullYear()) {
  if(typeof code!=='string'||code.length>4096)throw Error('Codul nu poate fi citit.');code=code.trim();if(!code||/[\\\x00-\x20\x7f]/.test(code))throw Error('Codul nu poate fi citit.');
  const gtin=value=>{if(!/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/.test(value)||/^0+$/.test(value))throw Error('Codul de bare nu este valid.');let sum=0;for(let j=value.length-2,weight=3;j>=0;j--,weight=4-weight)sum+=Number(value[j])*weight;if((10-sum%10)%10!==Number(value.at(-1)))throw Error('Codul de bare nu este valid.');return value.padStart(14,'0');};
  if(/^\d+$/.test(code))return {gtin14:gtin(code),lot_code:null,gs1_qualifiers:null,expiry:null};
  let u;try{u=new URL(code,base);}catch{throw Error('Codul nu poate fi citit.');}
  if(!['http:','https:'].includes(u.protocol)||u.username||u.password||u.hash||(u.protocol!=='https:'&&u.origin!==base))throw Error('Adresa QR nu este validă.');
  // Read identifier octets before URL's dot-segment normalization can change their meaning.
  const path=code.startsWith('/')?code.split(/[?#]/,1)[0]:(code.match(/^[a-z][a-z0-9+.-]*:\/\/[^/?#]*([^?#]*)/i)?.[1]??u.pathname);
  const m=path.match(/\/01\/(\d{14})(?:\/|$)/);if(!m)return {gtin14:null,lot_code:null,gs1_qualifiers:null,expiry:null};
  const out={gtin14:gtin(m[1]),lot_code:null,gs1_qualifiers:null,observed_dates:[],expiry:null},suffix=path.slice(m.index).replace(/\/+$/,'').split('/').slice(3);
  if(suffix.length%2!==0)throw Error('Adresa QR este incompletă.');const qualifiers={},order=['22','10','21','235'];let last=-1;
  for(let j=0;j<suffix.length;j+=2){const ai=suffix[j],position=order.indexOf(ai);if(position<0||position<=last)throw Error('Ordinea calificatorilor GS1 este incorectă.');last=position;qualifiers[ai]=decodeURIComponent(suffix[j+1]);}
  out.gs1_qualifiers=gs1Qualifiers(qualifiers);out.lot_code=qualifiers['10']??null;
  // URI Syntax 1.7.0 §4.11 permits both separators. Decode once; '+' remains '+'.
  const params=new Map();
  const query=code.includes('?')?code.slice(code.indexOf('?')+1):'';
  for(const pair of query.split(/[&;]/)){
    if(!pair)continue;const at=pair.indexOf('=');if(at<0)throw Error('Parametru QR fără valoare.');
    const key=decodeURIComponent(pair.slice(0,at)),value=decodeURIComponent(pair.slice(at+1));
    if(!key||/[\x00-\x1f\x7f]/.test(key+value))throw Error('Parametru QR incorect.');
    if(params.has(key)&&['linkType','context','15','17'].includes(key))throw Error('Codul conține date duplicate.');
    params.set(key,value);
  }
  for(const ai of ['15','17']) {
    if(!params.has(ai))continue;
    const raw=params.get(ai);if(!/^\d{6}$/.test(raw))throw Error('Data din cod nu este validă.');let y=Math.floor(year/100)*100+Number(raw.slice(0,2));if(y<year-49)y+=100;if(y>year+50)y-=100;
    const month=raw.slice(2,4),day=raw.slice(4);out.expiry=checkedDate({kind:ai==='17'?'use_by':'best_before',value:`${y}-${month}`+(day==='00'?'':`-${day}`),precision:day==='00'?'month':'day',source:'barcode',raw});
    out.observed_dates.push(out.expiry);
  }
  return out;
}
export function cleanProfile(raw) {
  if(!raw||raw.schema_version!=='openaliment.consumer-product.v1')throw Error('Informațiile alimentului nu sunt valide.');
  const out=unknownProfile();
  out.gs1_qualifiers=gs1Qualifiers(raw.gs1_qualifiers);
  if(raw.observed_dates!==undefined&&(!Array.isArray(raw.observed_dates)||raw.observed_dates.length>2))throw Error('Date observate incorecte.');
  out.observed_dates=(raw.observed_dates??[]).map(d=>{const date=checkedDate(d);if(!date||date.source!=='barcode'||date.kind==='reminder')throw Error('Dată observată incorectă.');return date;});
  const strings=['name','category','producer','ingredients','allergen_text','storage_text','after_opening_text','nutrition_text','net_quantity','product_id','trade_item_id','batch_id','gtin14','lot_code','public_url','alerts_checked_at','alert_scope','batch_state'];
  for(const key of strings)if(typeof raw[key]==='string')out[key]=raw[key].slice(0,key==='name'?180:5000);
  if(out.gtin14&&!/^\d{14}$/.test(out.gtin14))throw Error('Identificator incorect.');
  out.expiry=checkedDate(raw.expiry??null);
  if(out.alerts_checked_at&&(!Number.isFinite(Date.parse(out.alerts_checked_at))||Date.parse(out.alerts_checked_at)>Date.now()+60000))out.alerts_checked_at=null;
  const allergens=raw.food_data?.allergens;
  if(allergens&&allergens.status==='declared')out.food_data.allergens={status:'declared',contains:(allergens.contains||[]).filter(k=>Object.hasOwn(ALLERGENS,k)),may_contain:(allergens.may_contain||[]).filter(k=>Object.hasOwn(ALLERGENS,k))};
  const n=raw.food_data?.nutrition;
  if(n?.status==='declared'&&['per_100_g','per_100_ml','per_portion'].includes(n.basis)&&['as_sold','as_prepared'].includes(n.preparation)&&['EU','US','other'].includes(n.jurisdiction)) {
    const values={};for(const k of ['energy_kj','energy_kcal','fat_g','saturates_g','carbohydrate_g','sugars_g','polyols_g','fibre_g','protein_g','salt_g'])if(Number.isFinite(n.values?.[k])&&n.values[k]>=0&&n.values[k]<=100000)values[k]=n.values[k];
    const portion=n.portion&&Number.isFinite(n.portion.amount)&&n.portion.amount>0&&['g','ml'].includes(n.portion.unit)?{amount:n.portion.amount,unit:n.portion.unit}:null;
    out.food_data.nutrition={status:'declared',basis:n.basis,preparation:n.preparation,jurisdiction:n.jurisdiction,values,portion};
  }
  const o=raw.food_data?.after_opening;
  if(o&&Number.isInteger(o.hours)&&o.hours>=1&&o.hours<=8760&&typeof o.conditions==='string')out.food_data.after_opening={hours:o.hours,conditions:o.conditions.slice(0,500)};
  out.notices=Array.isArray(raw.notices)?raw.notices.filter(s=>typeof s==='string').slice(0,20).map(s=>s.slice(0,1000)):[];
  out.alerts=Array.isArray(raw.alerts)?raw.alerts.slice(0,30).map(a=>({id:String(a.id||'').slice(0,32),message:String(a.message||'').slice(0,3000),lot_code:typeof a.lot_code==='string'?a.lot_code.slice(0,120):null})):[];
  out.source={kind:['producer_approved','demonstration','unavailable','external'].includes(raw.source?.kind)?raw.source.kind:'unavailable'};
  for(const k of ['version_id','version','sha256','approved_at','checked_at','name','license','url'])if(typeof raw.source?.[k]==='string')out.source[k]=raw.source[k].slice(0,1000);
  out.simulation=raw.simulation===true;
  return out;
}
export function addItem(state,profile,code='',photoId=null,now=new Date().toISOString()) {
  const p=cleanProfile(profile),item={id:id(),revision:1,profile:p,code:typeof code==='string'?code.slice(0,4096):'',name:p.name,photo_id:photoId,quantity:1,unit:'pack',location:'home',state:'available',added_at:now,opened_at:null,consumed_at:null,expiry:p.expiry,updated_at:now};
  if(state.items.length>=2000)throw Error('Ai multe produse salvate. Exportă inventarul sau arhivează elementele vechi.');
  state.items.push(item);const event={id:id(),type:'add',item_id:item.id,before:null,after:structuredClone(item),created_at:now};state.events.push(event);return {item,event};
}
export function changeItem(state,itemId,action,data={},now=new Date().toISOString()) {
  const item=state.items.find(i=>i.id===itemId);if(!item)throw Error('Alimentul nu mai este disponibil.');
  const before=structuredClone(item);
  if(action==='open'){if(item.state==='consumed')throw Error('Alimentul este deja terminat.');if(item.opened_at)return null;item.opened_at=now;}
  else if(action==='consume'){if(item.state==='consumed')return null;item.state='consumed';item.consumed_at=now;}
  else if(action==='edit') {
    if(typeof data.name==='string'&&data.name.trim())item.name=data.name.trim().slice(0,180);
    if(Object.hasOwn(data,'expiry'))item.expiry=checkedDate(data.expiry);
    if(['home','fridge','freezer','pantry'].includes(data.location))item.location=data.location;
    if(Number.isFinite(data.quantity)&&data.quantity>0&&data.quantity<=100000)item.quantity=data.quantity;
    if(['pack','g','ml'].includes(data.unit))item.unit=data.unit;
  }else throw Error('Acțiune necunoscută.');
  item.revision++;item.updated_at=now;const event={id:id(),type:action,item_id:item.id,before,after:structuredClone(item),created_at:now};state.events.push(event);return {item,event};
}
export function undo(state,eventId,now=new Date().toISOString()) {
  const e=state.events.find(x=>x.id===eventId);if(!e||e.undone_at)throw Error('Această acțiune a fost deja anulată.');
  const index=state.items.findIndex(x=>x.id===e.item_id),current=state.items[index];
  if(!current||current.revision!==e.after?.revision)throw Error('Produsul s-a schimbat între timp. Deschide-l pentru corectare.');
  const before=structuredClone(current);let after=null;
  if(e.before===null)state.items.splice(index,1);
  else {after=structuredClone(e.before);for(const k of ['alerts','alerts_checked_at','alert_scope','batch_state','notices'])if(Object.hasOwn(current.profile,k))after.profile[k]=structuredClone(current.profile[k]);after.revision=current.revision+1;after.updated_at=now;state.items[index]=after;}
  e.undone_at=now;state.events.push({id:id(),type:'undo',item_id:e.item_id,before,after,created_at:now,undoes:e.id});
}
export function cleanItem(v) {
  if(!v||!/^[a-f0-9]{32}$/.test(v.id)||!['available','consumed'].includes(v.state))throw Error('Identificator de ambalaj incorect.');
  const profile=cleanProfile(v.profile),item={id:v.id,revision:Number.isSafeInteger(v.revision)&&v.revision>0?v.revision:1,profile,code:typeof v.code==='string'?v.code.slice(0,4096):'',name:typeof v.name==='string'?v.name.slice(0,180):profile.name,photo_id:/^[a-f0-9]{32}$/.test(v.photo_id||'')?v.photo_id:null,quantity:1,unit:'pack',location:'home',state:v.state,added_at:null,opened_at:null,consumed_at:null,expiry:checkedDate(v.expiry??null),updated_at:null};
  for(const k of ['added_at','opened_at','consumed_at','updated_at']){const value=v[k];if(value===null)item[k]=null;else if(typeof value==='string'&&/^\d{4}-\d{2}-\d{2}T/.test(value)&&Number.isFinite(Date.parse(value)))item[k]=value;else throw Error('Istoricul are o dată incorectă.');}
  if(['home','fridge','freezer','pantry'].includes(v.location))item.location=v.location;
  if(Number.isFinite(v.quantity)&&v.quantity>0&&v.quantity<=100000)item.quantity=v.quantity;
  if(['pack','g','ml'].includes(v.unit))item.unit=v.unit;
  if(v.imported===true)item.imported=true;
  if(v.receipt){const r=v.receipt;if(!/^[a-f0-9]{32}$/.test(r.receipt_id)||typeof r.line_id!=='string'||!r.line_id||r.line_id.length>80||!Number.isSafeInteger(r.unit_index)||r.unit_index<0||r.unit_index>2000)throw Error('Proveniență de bon incorectă.');item.receipt={receipt_id:r.receipt_id,line_id:r.line_id,unit_index:r.unit_index};}
  return item;
}
export function importState(raw) {
  if(!raw||raw.schema_version!==VERSION||!Array.isArray(raw.items)||raw.items.length>2000)throw Error('Fișierul de inventar nu este acceptat.');
  const state=initial(),seen=new Set();
  for(const v of raw.items) {
    if(seen.has(v.id))throw Error('Identificator de ambalaj repetat.');seen.add(v.id);state.items.push(cleanItem(v));
  }
  if(raw.events!==undefined&&!Array.isArray(raw.events))throw Error('Istoricul nu este valid.');
  if((raw.events||[]).length>50000)throw Error('Istoricul este prea mare pentru acest import.');
  const eventIds=new Set();for(const e of raw.events||[]) {
    if(!e||!/^[a-f0-9]{32}$/.test(e.id)||eventIds.has(e.id)||!/^[a-f0-9]{32}$/.test(e.item_id)||!['add','open','consume','edit','undo'].includes(e.type)||typeof e.created_at!=='string'||!Number.isFinite(Date.parse(e.created_at)))throw Error('Eveniment de istoric incorect.');
    eventIds.add(e.id);const before=e.before===null?null:cleanItem(e.before),after=e.after===null?null:cleanItem(e.after);if((before&&before.id!==e.item_id)||(after&&after.id!==e.item_id))throw Error('Referință de istoric incorectă.');
    const event={id:e.id,type:e.type,item_id:e.item_id,before,after,created_at:e.created_at};if(typeof e.undone_at==='string'&&Number.isFinite(Date.parse(e.undone_at)))event.undone_at=e.undone_at;if(/^[a-f0-9]{32}$/.test(e.undoes||''))event.undoes=e.undoes;state.events.push(event);
  }
  state.preferences.allergens=Array.isArray(raw.preferences?.allergens)?[...new Set(raw.preferences.allergens.filter(k=>Object.hasOwn(ALLERGENS,k)))]:[];
  if(Array.isArray(raw.shopping))state.shopping=raw.shopping.slice(0,500).filter(v=>v&&typeof v.name==='string').map(v=>({id:id(),name:v.name.slice(0,180),done:v.done===true}));
  return state;
}
