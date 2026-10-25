import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../public/assets/api-explorer.js',import.meta.url),'utf8');
const node=(value='')=>({value,textContent:'',disabled:false,checked:false,dataset:{},listeners:{},addEventListener(name,fn){this.listeners[name]=fn;},focus(){this.focused=true;}});
let passed=0;
function check(name,ok){assert.ok(ok,name);passed++;}
function harness(data){
  const status=node(),result=node(),example=node(),form=node(),send=node(),confirm=node(),token=node('oa_'+'a'.repeat(64)),body=node('{"name":"Iaurt","category":"lactate"}'),select=node('0'),path=node('a'.repeat(32));path.dataset={explorerParam:'id',paramLocation:'path'};
  const fields={'[data-explorer-result]':result,'[data-explorer-status]':status,'[data-explorer-example]':example,'[data-explorer-form]':form,'[data-explorer-send]':send,'[data-explorer-confirm]':confirm};
  const calls=[],events={};const panel={querySelector:q=>fields[q],querySelectorAll:()=>[path]};
  const document={querySelector:()=>panel,getElementById:id=>({'api-explorer-data':{content:{textContent:JSON.stringify(data)}},'api-explorer-case':select,'api-explorer-token':token,'api-explorer-body':body}[id])};
  const context={document,location:{origin:'https://openaliment.ro'},addEventListener:(name,fn)=>{events[name]=fn;},URL,URLSearchParams,AbortSignal,TextDecoder,Uint8Array,console,fetch:async(url,options)=>{calls.push({url,options});let done=false;return{status:201,headers:new Map([['X-Request-ID','test-correlation']]),body:{getReader(){return{async read(){if(done)return{done:true};done=true;return{done:false,value:new TextEncoder().encode('{"id":"'+ 'b'.repeat(32)+'"}')};},releaseLock(){},async cancel(){}};}}};}};
  vm.runInNewContext(source,context);
  return{status,result,example,form,send,confirm,token,body,path,calls,events};
}
const metadata={method:'POST',path:'/api/v2/manage/products/{id}/trade-items',mime:'application/json',bearer:true,live:true,cases:[{status:201,mime:'application/json',body:{id:'a'.repeat(32)}}]};
let h=harness(metadata);h.example.listeners.click();
check('Example never sends a request',h.calls.length===0&&h.result.textContent.includes('aaaa'));
await h.form.listeners.submit({preventDefault(){}});
check('Write requires explicit confirmation',h.calls.length===0&&h.confirm.focused);
h.confirm.checked=true;h.token.value='not a bearer';await h.form.listeners.submit({preventDefault(){}});
check('Invalid credential never leaves the page',h.calls.length===0);
h.token.value='oa_'+'a'.repeat(64);await h.form.listeners.submit({preventDefault(){}});
check('One real request on the fixed origin',h.calls.length===1&&h.calls[0].url.origin==='https://openaliment.ro');
const request=h.calls[0].options;
check('No cookies or followed redirects',request.credentials==='omit'&&request.redirect==='error');
check('Secret is in Authorization only',request.headers.Authorization==='Bearer '+h.token.value&&!String(h.calls[0].url).includes(h.token.value));
check('Request body encoded correctly',JSON.parse(request.body).name==='Iaurt');
check('Response is rendered as text',h.result.textContent.includes('bbbb')&&h.status.textContent.includes('201'));
check('Button is released after request',!h.send.disabled);
h.events.pagehide();check('Page exit clears credential',h.token.value==='');
h=harness({...metadata,path:'//evil.invalid/collect'});h.confirm.checked=true;await h.form.listeners.submit({preventDefault(){}});check('External destination rejected',h.calls.length===0);
h=harness({...metadata,path:'/01/{id}'});h.confirm.checked=true;h.path.value='A/B+00%?';await h.form.listeners.submit({preventDefault(){}});check('Path segment encoded once',h.calls[0].url.pathname==='/01/A%2FB%2B00%25%3F');
h=harness({...metadata,live:false});h.confirm.checked=true;await h.form.listeners.submit({preventDefault(){}});check('Ineligible flow cannot be sent',h.calls.length===0);
h=harness(metadata);h.confirm.checked=true;h.body.value='{bad';await h.form.listeners.submit({preventDefault(){}});check('Malformed JSON is rejected before network',h.calls.length===0&&h.status.textContent.includes('JSON'));
const report={passed,failed:0};fs.mkdirSync(new URL('./runtime/reports/',import.meta.url),{recursive:true});fs.writeFileSync(new URL('./runtime/reports/api-explorer.json',import.meta.url),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
