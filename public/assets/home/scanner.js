let controls=null,stream=null,stopped=true,generation=0;
let fallback;
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
export function stop() {stopped=true;generation++;controls?.stop();controls=null;stream?.getTracks().forEach(t=>t.stop());stream=null;}
async function zxing() {
  if(globalThis.ZXingBrowser)return globalThis.ZXingBrowser;
  fallback??=new Promise((resolve,reject)=>{const script=document.createElement('script');script.src='/assets/vendor/zxing-browser.min.js';script.onload=()=>resolve(globalThis.ZXingBrowser);script.onerror=()=>{fallback=null;script.remove();reject(Error('Scannerul nu s-a încărcat. Revino când ai conexiune.'));};document.head.append(script);});
  return fallback;
}
export async function start(video,onCode,onError) {
  stop();stopped=false;const token=generation;
  if(!navigator.mediaDevices?.getUserMedia)throw Error('Camera nu este disponibilă aici. Deschide pagina în browser sau folosește o fotografie.');
  let detector=null;
  try{if(globalThis.BarcodeDetector){const formats=(await BarcodeDetector.getSupportedFormats()).filter(f=>['ean_13','ean_8','upc_a','upc_e','qr_code','data_matrix','code_128','itf'].includes(f));if(['ean_13','ean_8','upc_a','qr_code','data_matrix'].every(f=>formats.includes(f)))detector=new BarcodeDetector({formats});}}catch{}
  if(token!==generation)return;
  if(detector) {
    const s=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280}},audio:false});
    if(token!==generation){s.getTracks().forEach(t=>t.stop());return;}stream=s;video.srcObject=s;await video.play();
    while(!stopped&&token===generation) {
      try{if(video.readyState>=2){const results=await detector.detect(video);if(results.length)await onCode(results[0].rawValue);}}catch(e){if(e.name!=='InvalidStateError')onError?.('Apropie sau îndepărtează puțin ambalajul.');}
      await sleep(450);
    }
  } else {
    const lib=await zxing();if(token!==generation)return;
    const reader=new lib.BrowserMultiFormatReader(undefined,{delayBetweenScanAttempts:450,delayBetweenScanSuccess:1000});
    const c=await reader.decodeFromConstraints({video:{facingMode:{ideal:'environment'},width:{ideal:1280}},audio:false},video,(result)=>{if(result&&!stopped&&token===generation)Promise.resolve(onCode(result.getText())).catch(()=>{});});
    if(token!==generation)c.stop();else controls=c;
  }
}
export async function fromPhoto(image) {
  const lib=await zxing(),reader=new lib.BrowserMultiFormatReader();
  try{return (await reader.decodeFromImageElement(image)).getText();}catch{return null;}
}
