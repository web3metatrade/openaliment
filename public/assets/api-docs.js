'use strict';
(() => {
  const root=document.querySelector('[data-docs-page]');if(!root)return;
  const input=document.getElementById('api-search'),status=document.getElementById('api-search-status'),initialStatus=status.textContent;
  const fold=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('ro');
  const groups=[...root.querySelectorAll('[data-nav-group]')],initialOpen=new Map(groups.map(g=>[g,g.open]));
  const searchRows=[...root.querySelectorAll('[data-doc-search]')],results=document.getElementById('api-search-results');
  const navigation=root.querySelector('nav[aria-label="Ghiduri și referință API"]');
  input.addEventListener('input',()=>{
    const query=fold(input.value.trim()),words=query.split(/\s+/).filter(Boolean);
    results.hidden=!words.length;navigation.hidden=Boolean(words.length);results.querySelector('ul').replaceChildren();
    if(!words.length){status.textContent=initialStatus;for(const group of groups)if(group.tagName==='DETAILS')group.open=initialOpen.get(group);return;}
    const matches=searchRows.map((row,index)=>{
      const title=fold(row.dataset.docTitle||''),endpoint=fold(row.dataset.docEndpoint||''),body=fold(row.dataset.docSearch);
      const score=title.includes(query)?100:words.every(word=>title.includes(word))?80:words.every(word=>endpoint.includes(word))?60:words.every(word=>body.includes(word))?20:0;
      return {row,index,score:score-(score&&endpoint.startsWith('/api/v1/')?10:0)};
    }).filter(result=>result.score>0).sort((a,b)=>b.score-a.score||a.index-b.index);
    for(const {row} of matches.slice(0,30)){const item=document.createElement('li');item.append(row.querySelector('a').cloneNode(true));results.querySelector('ul').append(item);}
    status.textContent=matches.length?matches.length+' rezultate în documentație.'+(matches.length>30?' Primele 30 sunt afișate; restrânge căutarea.':''):'Nu am găsit rezultate. Încearcă denumirea acțiunii sau adresa API.';
  });
  document.addEventListener('keydown',event=>{if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='k'){event.preventDefault();root.querySelector('.api-sidebar').open=true;input.focus();input.select();}if(event.key==='Escape')root.querySelector('.api-page-menu').open=false;});
  const sidebar=root.querySelector('.api-sidebar'),mobile=matchMedia('(max-width: 800px)');sidebar.open=!mobile.matches;mobile.addEventListener('change',()=>{sidebar.open=!mobile.matches;});
  const message=document.getElementById('api-copy-status');
  root.querySelector('[data-close-copy]').addEventListener('click',()=>document.getElementById('api-copy-dialog').close());
  async function copy(text){
    try{if(!navigator.clipboard?.writeText)throw new Error('clipboard');await navigator.clipboard.writeText(text);message.textContent='Conținut copiat.';return true;}
    catch{const dialog=document.getElementById('api-copy-dialog'),field=document.getElementById('api-copy-text');field.value=text;dialog.showModal();field.focus();field.select();message.textContent='Selectează textul din fereastra de copiere.';return false;}
  }
  root.querySelector('[data-copy-page]').addEventListener('click',async event=>{const button=event.currentTarget;button.disabled=true;message.textContent='Pregătim pagina…';
    try{const text=document.getElementById('api-page-markdown').content.textContent;if(!text||text.length>262144)throw new Error('size');await copy(text);
    }catch{message.textContent='Pagina nu a putut fi copiată. Folosește opțiunea Vezi ca Markdown.';}finally{button.disabled=false;}
  });
  for(const block of root.querySelectorAll('[data-copy-code]')){
    const button=document.createElement('button');button.type='button';button.className='api-copy-code';button.textContent='Copiază';button.setAttribute('aria-label','Copiază exemplul de cod');
    button.addEventListener('click',async()=>{if(await copy(block.querySelector('code').textContent)){button.textContent='Copiat';setTimeout(()=>{button.textContent='Copiază';},1800);}});block.append(button);
  }
  const tabs=[...root.querySelectorAll('[data-code-tab]')],panels=[...root.querySelectorAll('[data-code-panel]')];
  const selectTab=tab=>{for(const item of tabs){item.setAttribute('aria-selected',String(item===tab));item.tabIndex=item===tab?0:-1;}for(const panel of panels)panel.hidden=panel.dataset.codePanel!==tab.dataset.codeTab;};
  for(const tab of tabs){tab.addEventListener('click',()=>selectTab(tab));tab.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const index=event.key==='Home'?0:event.key==='End'?tabs.length-1:(tabs.indexOf(tab)+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;selectTab(tabs[index]);tabs[index].focus();});}if(tabs.length)selectTab(tabs[0]);
  const demo=root.querySelector('[data-try-demo]');if(demo)demo.addEventListener('click',async()=>{demo.disabled=true;const panel=document.getElementById('api-demo-result');panel.hidden=false;panel.querySelector('code').textContent='Se încarcă…';
    try{const response=await fetch('/api/v2/public/food?code=9520000000011',{credentials:'omit',redirect:'error',headers:{Accept:'application/json'},signal:AbortSignal.timeout(15000)});const data=await response.json();if(!response.ok)throw new Error(data.message||'Cererea nu a reușit.');panel.querySelector('code').textContent=JSON.stringify(data,null,2);}catch(error){panel.querySelector('code').textContent=error.message;}finally{demo.disabled=false;}
  });
  const legacy={chei:'chei',oauth:'oauth',acasa:'acasa',conectoare:'conectoare'};if(root.dataset.docsPage==='overview'&&legacy[location.hash.slice(1)])location.replace('/api/docs/guides/'+legacy[location.hash.slice(1)]);
  document.addEventListener('click',event=>{const menu=root.querySelector('.api-page-menu');if(!menu.contains(event.target))menu.open=false;});
})();
