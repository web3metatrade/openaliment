import {initial} from './domain.js?v=8';
const DATABASE='openaliment-home',KEY='inventory';
let database;
let photoLoader=null;
export function setPhotoLoader(fn){photoLoader=fn;}
export function open() {
  if(database)return database;
  database=new Promise((resolve,reject)=>{
    if(!globalThis.indexedDB){reject(Error('Telefonul nu permite salvarea locală în acest browser.'));return;}
    const request=indexedDB.open(DATABASE,1);
    request.onupgradeneeded=()=>{request.result.createObjectStore('state');request.result.createObjectStore('photos');};
    request.onsuccess=()=>{const db=request.result;db.onversionchange=()=>db.close();resolve(db);};
    request.onerror=()=>reject(Error('Nu am putut deschide alimentele salvate. Verifică spațiul disponibil.'));
    request.onblocked=()=>reject(Error('Închide celelalte ferestre OpenAliment și încearcă din nou.'));
  });return database;
}
export async function read() {
  const db=await open();return new Promise((resolve,reject)=>{
    const tx=db.transaction('state','readonly'),r=tx.objectStore('state').get(KEY);r.onsuccess=()=>resolve(r.result||initial());r.onerror=()=>reject(r.error);
  });
}
export async function update(fn,photos=null) {
  const db=await open();return new Promise((resolve,reject)=>{
    const tx=db.transaction(photos?['state','photos']:['state'],'readwrite'),store=tx.objectStore('state'),r=store.get(KEY);let result,error;
    r.onsuccess=()=>{try{const state=r.result||initial();result=fn(state);store.put(state,KEY);if(photos)for(const [key,blob]of photos)tx.objectStore('photos').put(blob,key);}catch(e){error=e;tx.abort();}};
    tx.oncomplete=()=>{resolve(result);globalThis.dispatchEvent?.(new Event('home-changed'));};
    tx.onerror=tx.onabort=()=>reject(error||Error('Nu am putut salva. Verifică spațiul disponibil și încearcă din nou.'));
  });
}
export async function replace(state) {return update(current=>{for(const key of Object.keys(current))delete current[key];Object.assign(current,state);});}
export async function putPhoto(key,blob) {
  if(!(blob instanceof Blob)||blob.size>1000000)throw Error('Fotografia este prea mare.');
  const db=await open();return new Promise((resolve,reject)=>{const tx=db.transaction('photos','readwrite');tx.objectStore('photos').put(blob,key);tx.oncomplete=()=>resolve();tx.onerror=()=>reject(Error('Nu am putut salva fotografia.'));});
}
export async function photo(key) {
  if(!key)return null;const db=await open();const blob=await new Promise((resolve,reject)=>{const r=db.transaction('photos','readonly').objectStore('photos').get(key);r.onsuccess=()=>resolve(r.result||null);r.onerror=()=>reject(r.error);});return blob||await photoLoader?.(key)||null;
}
export const emptySync=()=>({enabled:false,household_id:null,cursor:0,ack:{},history_ack:[],photos:[],outbox:null,conflicts:[],lease:null});
export async function clearLocal(){
  const db=await open();return new Promise((resolve,reject)=>{
    const tx=db.transaction(['state','photos'],'readwrite');tx.objectStore('state').clear();tx.objectStore('photos').clear();tx.objectStore('state').put(initial(),KEY);tx.objectStore('state').put(emptySync(),'cloud');
    tx.oncomplete=()=>{resolve();globalThis.dispatchEvent?.(new Event('home-changed'));};tx.onerror=tx.onabort=()=>reject(Error('Nu am putut șterge copia din acest browser.'));
  });
}
export async function syncRead(){const db=await open();return new Promise((resolve,reject)=>{const r=db.transaction('state','readonly').objectStore('state').get('cloud');r.onsuccess=()=>resolve(r.result||emptySync());r.onerror=()=>reject(r.error);});}
export async function syncUpdate(fn,{notify=false}={}){
  const db=await open();return new Promise((resolve,reject)=>{
    const tx=db.transaction('state','readwrite'),s=tx.objectStore('state'),a=s.get(KEY),b=s.get('cloud');let count=0,result,error;
    const apply=()=>{if(++count!==2)return;try{const inventory=a.result||initial(),meta=b.result||emptySync();result=fn(inventory,meta,s);s.put(inventory,KEY);s.put(meta,'cloud');}catch(e){error=e;tx.abort();}};a.onsuccess=apply;b.onsuccess=apply;
    tx.oncomplete=()=>{resolve(result);if(notify)globalThis.dispatchEvent?.(new Event('home-changed'));};tx.onerror=tx.onabort=()=>reject(error||Error('Nu am putut păstra sincronizarea pe dispozitiv.'));
  });
}
export async function changeHousehold(id,{keepLocal=true}={}){
  if(id!==null&&!/^[a-f0-9]{32}$/.test(id))throw Error('Familia nu este validă.');
  const db=await open();return new Promise((resolve,reject)=>{
    const tx=db.transaction('state','readwrite'),s=tx.objectStore('state'),a=s.get(KEY),b=s.get('cloud'),saved=s.get('household:'+(id||'guest')),savedMeta=s.get('household-sync:'+(id||'guest'));let count=0,error;
    const apply=()=>{if(++count!==4)return;try{const current=a.result||initial(),old=b.result||emptySync();if(old.household_id===id){old.enabled=id!==null;old.lease=null;s.put(old,'cloud');return;}
      s.put(current,'household:'+(old.household_id||'guest'));s.put(old,'household-sync:'+(old.household_id||'guest'));
      const next=saved.result||initial(),meta=savedMeta.result||emptySync();next.preferences=structuredClone(current.preferences);
      if(keepLocal){const ids=new Set(next.items.map(i=>i.id)),events=new Set(next.events.map(e=>e.id));for(const i of current.items)if(!ids.has(i.id))next.items.push(i);for(const e of current.events)if(!events.has(e.id))next.events.push(e);if(next.items.length>2000)throw Error('Inventarul combinat ar depăși 2.000 de ambalaje.');if(!next.shopping.length)next.shopping=structuredClone(current.shopping);}
      if(old.pending_invite)meta.pending_invite=old.pending_invite;meta.enabled=id!==null;meta.household_id=id;meta.lease=null;meta.outbox=null;meta.conflicts=[];s.put(next,KEY);s.put(meta,'cloud');
    }catch(e){error=e;tx.abort();}};for(const r of[a,b,saved,savedMeta])r.onsuccess=apply;
    tx.oncomplete=()=>{resolve();globalThis.dispatchEvent?.(new Event('home-changed'));};tx.onerror=tx.onabort=()=>reject(error||Error('Nu am putut schimba familia.'));
  });
}
