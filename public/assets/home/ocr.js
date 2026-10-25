import {checkedDate} from './domain.js?v=8';
let library=null,worker=null;
async function load(){
  if(globalThis.Tesseract)return globalThis.Tesseract;
  library??=new Promise((resolve,reject)=>{const script=document.createElement('script');script.src='/assets/ocr/tesseract.min.js';script.onload=()=>resolve(globalThis.Tesseract);script.onerror=()=>{library=null;script.remove();reject(Error('Citirea fotografiei nu este disponibilă. Introdu data de pe ambalaj.'));};document.head.append(script);});return library;
}
export function candidates(text){
  const normalized=text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(),best=/de preferint|best before/.test(normalized),use=/consuma pana|use by|expir/.test(normalized),kind=best&&!use?'best_before':use&&!best?'use_by':null;
  const out=[],seen=new Set();const add=(value,precision)=>{try{const date=checkedDate({kind:kind||'reminder',value,precision,source:'user'}),key=value+'|'+precision;if(!seen.has(key)){seen.add(key);out.push({value:date.value,precision,kind});}}catch{}};
  const full=/(?:^|[^0-9])(\d{1,2})[.\/-](\d{1,2})[.\/-](20\d{2})(?!\d)/g;let m,sawComplete=false;
  while((m=full.exec(normalized))){sawComplete=true;add(`${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`,'day');}
  const iso=/(?:^|[^0-9])(20\d{2})[-/.](\d{2})[-/.](\d{2})(?!\d)/g;
  while((m=iso.exec(normalized))){sawComplete=true;add(`${m[1]}-${m[2]}-${m[3]}`,'day');}
  // Month-only dates are candidates only when no complete date was found.
  if(!sawComplete){const month=/(?:^|[^0-9])(0?[1-9]|1[0-2])[.\/-](20\d{2})(?!\d)/g;while((m=month.exec(normalized)))add(`${m[2]}-${m[1].padStart(2,'0')}`,'month');}
  return out;
}
export async function recognize(image,progress=()=>{}){
  const t=await load();
  if(!worker){progress('Se pregătește citirea pe dispozitiv…');worker=t.createWorker('eng',1,{workerPath:'/assets/ocr/worker.min.js',corePath:'/assets/ocr/tesseract-core-lstm.js',langPath:'/assets/ocr/lang',workerBlobURL:false,logger:m=>{if(m.status==='recognizing text')progress('Se citește data… '+Math.round((m.progress||0)*100)+'%');}}).catch(e=>{worker=null;throw e;});}
  const w=await worker;await w.setParameters({tessedit_pageseg_mode:'11',preserve_interword_spaces:'1'});
  const result=await w.recognize(image,{}, {text:true});return {candidates:candidates(result.data.text||''),confidence:result.data.confidence||0};
}
export async function stop(){if(worker){try{(await worker).terminate();}catch{}worker=null;}}
