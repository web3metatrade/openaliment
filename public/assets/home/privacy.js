import * as store from './store.js?v=10';
const $=id=>document.getElementById(id);
let context=null,pending=null;
function show(text){$('cloud-action-status').textContent=text;}
export function initPrivacy(options){
  if(!$('cloud-account-actions'))return;context=options;
  $('cloud-account-actions').addEventListener('click',async event=>{
    const button=event.target.closest('[data-account-action]');if(!button)return;
    button.disabled=true;try{const action=button.dataset.accountAction,payload={};if(action==='leave')payload.household_id=context.session().household_id;
      const result=await context.api('account/action/request',{body:{action,payload}});show(result.message+' Deschide-o în acest browser.');
    }catch(error){show(error.message);}finally{button.disabled=false;}
  });
  $('cloud-confirm-action').addEventListener('click',async()=>{
    if(!pending)return;const button=$('cloud-confirm-action');button.disabled=true;
    try{const result=await context.api('account/action/confirm',{body:{token:pending}});pending=null;
      // Already downloaded copies remain on devices unless their owner explicitly clears them.
      if(result.action==='erase'||result.action==='leave')await store.syncUpdate((_,meta)=>{meta.enabled=false;meta.lease=null;meta.outbox=null;});
      if($('cloud-clear-local').checked)await store.clearLocal();
      $('cloud-action-dialog').close();await context.reload();context.changed();const text=result.action==='erase'?'Contul a fost șters. Copiile de pe alte dispozitive pot rămâne acolo.':'Alegerea a fost confirmată.';show(text);context.status(text);
    }catch(error){$('cloud-action-error').textContent=error.message;}finally{button.disabled=false;}
  });
}
export async function requestTransfer(button,accountId){
  if(!context)return;button.disabled=true;
  try{const result=await context.api('account/action/request',{body:{action:'transfer',payload:{household_id:context.session().household_id,account_id:accountId}}});context.status(result.message+' Deschide-o în acest browser.');}
  catch(error){context.status(error.message);}finally{button.disabled=false;}
}
export function privacyState(session){if(!$('cloud-account-actions'))return;$('cloud-leave-family').hidden=session?.role==='owner';}
export async function incomingPrivacy(token){
  if(!context)throw Error('Confirmarea contului nu este disponibilă acum.');
  if(!/^[a-f0-9]{64}$/.test(token))throw Error('Legătura nu este validă.');
  const result=await context.api('account/action/preview',{body:{token}});pending=token;
  $('cloud-action-title').textContent=({erase:'Șterge contul',transfer:'Transferă administrarea',leave:'Părăsește familia'})[result.action];$('cloud-action-description').textContent=result.description;$('cloud-action-error').textContent='';$('cloud-clear-local').checked=false;$('cloud-local-choice').hidden=result.action==='transfer';$('cloud-action-dialog').showModal();
}
