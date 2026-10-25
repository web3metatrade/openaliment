import * as D from './domain.js?v=8';
import * as store from './store.js?v=8';
export function cleanReceipt(raw) {
  if(!raw||raw.schema_version!=='openaliment.receipt.v1'||!/^[a-f0-9]{32}$/.test(raw.id)||typeof raw.merchant!=='string'||raw.merchant.length>180||!Array.isArray(raw.lines)||raw.lines.length<1||raw.lines.length>500)throw Error('Bonul nu este valid.');
  const seen=new Set(),lines=raw.lines.map(l=>{
    if(!l||typeof l.line_id!=='string'||!l.line_id||l.line_id.length>80||seen.has(l.line_id)||typeof l.name!=='string'||!l.name||l.name.length>180||!Number.isFinite(l.quantity)||l.quantity<=0||l.quantity>100000||!['pack','g','ml'].includes(l.unit)||l.unit==='pack'&&!Number.isInteger(l.quantity))throw Error('O linie a bonului nu este validă.');seen.add(l.line_id);
    const gtin=l.gtin14??null;if(gtin!==null)D.parseCode(gtin);if(gtin!==null&&!/^\d{14}$/.test(gtin))throw Error('Identificator incorect în bon.');
    if(l.lot_code!==null&&l.lot_code!==undefined&&(typeof l.lot_code!=='string'||l.lot_code.length>120))throw Error('Lot incorect în bon.');
    return {line_id:l.line_id,name:l.name,gtin14:gtin,lot_code:l.lot_code??null,quantity:l.quantity,unit:l.unit,expiry:D.checkedDate(l.expiry??null)};
  });return {id:raw.id,merchant:raw.merchant,lines,simulation:raw.simulation===true};
}
export function receiptCode(line,origin=globalThis.location?.origin||'https://openaliment.ro') {
  if(!line.gtin14)return '';
  return line.lot_code&&line.lot_code.length<=20?origin+'/01/'+line.gtin14+'/10/'+encodeURIComponent(line.lot_code):line.gtin14;
}
export function mergeReceipt(state,receipt,profiles) {
  const r=cleanReceipt({...receipt,schema_version:'openaliment.receipt.v1'}),key=v=>JSON.stringify([v.receipt_id,v.line_id,v.unit_index]);
  const seen=new Set(state.items.filter(i=>i.receipt).map(i=>key(i.receipt)));
  for(const e of state.events)if(e.type==='add'&&!e.undone_at&&e.after?.receipt)seen.add(key(e.after.receipt));
  const pending=[];
  for(const line of r.lines){const count=line.unit==='pack'?line.quantity:1;if(count>2000)throw Error('Bonul conține prea multe ambalaje pentru acest dispozitiv.');for(let j=0;j<count;j++){const ref={receipt_id:r.id,line_id:line.line_id,unit_index:j};if(!seen.has(key(ref)))pending.push({line,ref});}}
  if(state.items.length+pending.length>2000)throw Error('Bonul ar depăși limita de 2.000 de ambalaje. Nu am adăugat nimic.');
  for(const {line,ref}of pending){const profile=D.cleanProfile(profiles.get(line.line_id)||D.unknownProfile({gtin14:line.gtin14,lot_code:line.lot_code}));
    if(profile.source.kind==='unavailable')profile.name=line.name;
    if(line.expiry&&profile.expiry&&(line.expiry.value!==profile.expiry.value||line.expiry.kind!==profile.expiry.kind||line.expiry.precision!==profile.expiry.precision)){profile.expiry=null;profile.notices.push('Data din bon diferă de cea publicată. Verifică ambalajul și confirmă data.');}
    else if(line.expiry&&!profile.expiry)profile.expiry={...line.expiry,source:'receipt'};
    if(r.simulation){profile.simulation=true;profile.notices.push('Bon demonstrativ cu date simulate.');}
    const result=D.addItem(state,profile,receiptCode(line));result.item.quantity=line.unit==='pack'?1:line.quantity;result.item.unit=line.unit;result.item.receipt=ref;result.event.after=structuredClone(result.item);
  }return pending.length;
}
export async function importReceipt(raw,onProgress=()=>{}) {
  const receipt=cleanReceipt(raw),profiles=new Map(),cache=new Map();let processed=0;
  for(let at=0;at<receipt.lines.length;at+=4)await Promise.all(receipt.lines.slice(at,at+4).map(async line=>{
    const code=receiptCode(line);
    if(code){if(!cache.has(code))cache.set(code,(async()=>{try{const response=await fetch('/api/v2/public/food?'+new URLSearchParams({code}),{credentials:'omit',cache:'no-store',signal:AbortSignal.timeout(5000)});if(response.ok)return D.cleanProfile(await response.json());}catch{}return D.unknownProfile({gtin14:line.gtin14,lot_code:line.lot_code});})());profiles.set(line.line_id,await cache.get(code));}
    onProgress(++processed,receipt.lines.length);
  }));
  return store.update(state=>mergeReceipt(state,receipt,profiles));
}
