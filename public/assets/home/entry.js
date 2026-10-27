let saving=false;
document.addEventListener('click',async event=>{
  const link=event.target.closest('a[data-home-add]');if(!link||event.ctrlKey||event.metaKey||event.shiftKey||event.button!==0)return;
  event.preventDefault();if(saving)return;saving=true;link.setAttribute('aria-busy','true');
  const code=link.dataset.homeAdd;
  try{
    const {addItem,unknownProfile,parseCode}=await import('./domain.js?v=8');const {update}=await import('./store.js?v=10');
    let profile;try{const r=await fetch('/api/v2/public/food?'+new URLSearchParams({code}),{cache:'no-store',credentials:'omit',signal:AbortSignal.timeout(5000)});if(r.ok)profile=await r.json();}catch{}
    if(!profile){profile=unknownProfile(parseCode(code));profile.name=link.dataset.homeName||'Aliment';}
    await update(state=>addItem(state,profile,code));location.assign('/acasa/');
  }catch{location.assign(link.href);}finally{saving=false;link.removeAttribute('aria-busy');}
});
