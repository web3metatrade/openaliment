'use strict';
(() => {
  const panel=document.querySelector('[data-api-explorer]');if(!panel)return;
  const data=JSON.parse(document.getElementById('api-explorer-data').content.textContent);
  const result=panel.querySelector('[data-explorer-result]'),status=panel.querySelector('[data-explorer-status]');
  const format=value=>typeof value==='string'?value:JSON.stringify(value,null,2);
  panel.querySelector('[data-explorer-example]').addEventListener('click',()=>{
    const sample=data.cases[Number(document.getElementById('api-explorer-case').value)];
    status.textContent='Exemplu sintetic · HTTP '+sample.status+' · nu s-a trimis nicio cerere.';
    result.textContent=format(sample.body);
  });
  addEventListener('pagehide',()=>{const token=document.getElementById('api-explorer-token');if(token)token.value='';});
  panel.querySelector('[data-explorer-form]').addEventListener('submit',async event=>{
    event.preventDefault();if(!data.live)return;
    const button=panel.querySelector('[data-explorer-send]'),confirmation=panel.querySelector('[data-explorer-confirm]');
    if(confirmation&&!confirmation.checked){status.textContent='Confirmă operația înainte de trimitere.';confirmation.focus();return;}
    let path=data.path,query=new URLSearchParams();
    const headers={Accept:data.path.startsWith('/01/')?'application/linkset+json':'application/json'};
    try{
      for(const input of panel.querySelectorAll('[data-explorer-param]')){
        const name=input.dataset.explorerParam,value=input.value;
        if(input.dataset.paramLocation==='path'){
          if(!value)throw new Error('Completează parametrul '+name+'.');
          path=path.replace('{'+name+'}',encodeURIComponent(value));
        }else if(input.dataset.paramLocation==='query'&&value)query.set(name,value);
        else if(name==='Idempotency-Key'&&value)headers[name]=value;
      }
      if(!path.startsWith('/')||path.startsWith('//')||/[{}\r\n]/.test(path))throw new Error('Adresă de operație incorectă.');
      const token=document.getElementById('api-explorer-token');
      if(data.bearer){if(!/^[A-Za-z0-9_-]{20,180}$/.test(token.value))throw new Error('Completează tokenul Bearer propriu, fără prefixul Bearer.');headers.Authorization='Bearer '+token.value;}
      let body;
      if(data.mime){const parsed=JSON.parse(document.getElementById('api-explorer-body').value);headers['Content-Type']=data.mime;body=data.mime==='application/x-www-form-urlencoded'?new URLSearchParams(parsed):JSON.stringify(parsed);}
      const url=new URL(path,location.origin);url.search=query.toString();
      if(url.origin!==location.origin)throw new Error('Destinația trebuie să fie aceeași platformă.');
      button.disabled=true;status.textContent='Cerere în curs…';
      const response=await fetch(url,{method:data.method,headers,body,credentials:'omit',redirect:'error',cache:'no-store',signal:AbortSignal.timeout(15000)});
      const reader=response.body?.getReader();let size=0,chunks=[];
      if(reader){try{while(true){const part=await reader.read();if(part.done)break;size+=part.value.byteLength;if(size>1048576){await reader.cancel();throw new Error('Răspunsul depășește 1 MiB. Folosește clientul API pentru acest volum.');}chunks.push(part.value);}}finally{reader.releaseLock();}}
      const bytes=new Uint8Array(size);let offset=0;for(const chunk of chunks){bytes.set(chunk,offset);offset+=chunk.length;}
      const text=new TextDecoder().decode(bytes);let content=text;try{content=JSON.parse(text);}catch{}
      status.textContent='Răspuns real · HTTP '+response.status+(response.headers.get('X-Request-ID')?' · ID cerere '+response.headers.get('X-Request-ID'):'');
      result.textContent=format(content);
    }catch(error){status.textContent=error instanceof SyntaxError?'Corpul nu este JSON valid.':error.message;}
    finally{button.disabled=false;}
  });
})();
