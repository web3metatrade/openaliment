const status=document.querySelector('[data-account-status]');
const csrf=()=>document.querySelector('input[name="_csrf"]')?.value||'';
function message(text){if(status){status.textContent=text;if(text)status.scrollIntoView({block:'nearest'});}}
async function request(path,data){const response=await fetch(path,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf()},body:JSON.stringify(data)});const result=await response.json();if(!response.ok)throw Error(result.message||'Operațiunea nu a reușit.');if(result.csrf)for(const input of document.querySelectorAll('input[name="_csrf"]'))input.value=result.csrf;return result;}
function decode(value){return Uint8Array.from(atob(value.replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));}
function encode(buffer){let raw='';for(const n of new Uint8Array(buffer))raw+=String.fromCharCode(n);return btoa(raw).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
async function passkey(kind,data={}){
 if(!window.PublicKeyCredential||!navigator.credentials)throw Error('Browserul nu acceptă chei de acces. Folosește un browser actualizat sau un cod de recuperare.');
 const options=await request('/api/v2/account/passkeys/'+kind+'/options',data);options.publicKey.challenge=decode(options.publicKey.challenge);
 if(options.publicKey.user)options.publicKey.user.id=decode(options.publicKey.user.id);
 for(const list of ['allowCredentials','excludeCredentials'])for(const key of options.publicKey[list]||[])key.id=decode(key.id);
 const credential=await navigator.credentials[kind==='create'?'create':'get'](options);if(!credential)throw Error('Confirmarea a fost anulată.');const r=credential.response;
 const payload={credentialId:encode(credential.rawId),clientDataJSON:encode(r.clientDataJSON)};
 for(const field of ['attestationObject','authenticatorData','signature'])if(r[field])payload[field]=encode(r[field]);
 const result=await request('/api/v2/account/passkeys/'+kind+'/confirm',payload);
 if(result.recovery_codes){showCodes(result.recovery_codes);return;}location.assign(result.redirect||'/manage');
}
function showCodes(codes){const box=document.querySelector('[data-recovery-codes]');if(box){box.hidden=false;box.querySelector('pre').textContent=codes.join('\n');message('Păstrează codurile într-un loc sigur. Fiecare poate fi folosit o singură dată.');box.scrollIntoView({block:'center'});}else location.assign('/manage/security');}
async function run(button,fn){if(button)button.disabled=true;message('');try{await fn();}catch(error){message(error.name==='NotAllowedError'?'Confirmarea a fost anulată sau a expirat.':error.message);}finally{if(button)button.disabled=false;}}
document.querySelector('[data-passkey-verify]')?.addEventListener('click',e=>run(e.currentTarget,()=>passkey('get')));
document.querySelector('[data-passkey-create]')?.addEventListener('submit',e=>{e.preventDefault();const form=e.currentTarget;run(form.querySelector('button'),()=>passkey('create',Object.fromEntries(new FormData(form))));});
document.querySelector('[data-passkey-recover]')?.addEventListener('submit',e=>{e.preventDefault();const form=e.currentTarget;run(form.querySelector('button'),async()=>{const r=await request('/api/v2/account/passkeys/recover',Object.fromEntries(new FormData(form)));location.assign(r.redirect);});});
document.querySelector('[data-recovery-codes-new]')?.addEventListener('click',e=>run(e.currentTarget,async()=>showCodes((await request('/api/v2/account/passkeys/recovery-codes',{})).recovery_codes)));
for(const button of document.querySelectorAll('[data-passkey-remove],[data-session-revoke]'))button.addEventListener('click',()=>run(button,async()=>{const path=button.dataset.passkeyRemove?'/api/v2/account/passkeys/'+button.dataset.passkeyRemove+'/remove':'/api/v2/account/sessions/'+button.dataset.sessionRevoke+'/revoke';await request(path,{});location.reload();}));
const recovery=document.querySelector('[data-account-recovery]');
if(recovery){let token=new URLSearchParams(location.hash.slice(1)).get('token');if(token){history.replaceState(null,'',location.pathname);run(null,async()=>{await request('/api/v2/account/password/preview',{token});recovery.querySelector('[data-recovery-request]').hidden=true;recovery.querySelector('[data-recovery-reset]').hidden=false;});}
 recovery.querySelector('[data-recovery-request]')?.addEventListener('submit',e=>{e.preventDefault();const form=e.currentTarget;run(form.querySelector('button'),async()=>message((await request('/api/v2/account/password/request',Object.fromEntries(new FormData(form)))).message));});
 recovery.querySelector('[data-recovery-reset]')?.addEventListener('submit',e=>{e.preventDefault();const form=e.currentTarget;run(form.querySelector('button'),async()=>{await request('/api/v2/account/password/reset',{...Object.fromEntries(new FormData(form)),token});token=null;form.hidden=true;message('Parola a fost schimbată. Te poți conecta cu parola nouă.');});});
}
