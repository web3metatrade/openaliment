/** Browser/Node client. Store user tokens in the platform's secure storage, not this source file. */
export class ApiError extends Error {
  constructor(status,body,headers){super(body.message||body.error_description||'Cererea API a fost respinsă.');this.status=status;this.code=body.error_code||body.error||'http_error';this.requestId=body.request_id||headers.get('X-Request-ID');this.retryAfter=headers.get('Retry-After');}
}
export class OpenAliment {
  constructor({baseUrl='https://openaliment.ro',token=null,allowLoopback=false}={}){
    const u=new URL(baseUrl),local=allowLoopback&&u.protocol==='http:'&&['localhost','127.0.0.1','[::1]'].includes(u.hostname);
    if((u.protocol!=='https:'&&!local)||u.username||u.password||u.search||u.hash||u.pathname!=='/')throw Error('Folosește originea HTTPS a platformei.');
    this.baseUrl=u.origin;this.token=token;
  }
  async request(method,path,data,{idempotencyKey,signal}={}){
    if(!path.startsWith('/')||path.startsWith('//')||path.includes('\\'))throw Error('Cale API incorectă.');
    const headers={Accept:'application/json'};
    if(this.token)headers.Authorization='Bearer '+this.token;
    if(idempotencyKey)headers['Idempotency-Key']=idempotencyKey;
    if(data!==undefined)headers['Content-Type']='application/json';
    const response=await fetch(this.baseUrl+path,{method,headers,body:data===undefined?undefined:JSON.stringify(data),credentials:'omit',redirect:'error',cache:'no-store',signal:signal||AbortSignal.timeout(15000)});
    const raw=await response.text();let body;try{body=raw?JSON.parse(raw):null;}catch{body=null;}
    if(!response.ok)throw new ApiError(response.status,body||{},response.headers);
    return body===null?raw:body;
  }
  food(code){return this.request('GET','/api/v2/public/food?'+new URLSearchParams({code}));}
  async *collection(path){let cursor=null;do{const page=await this.request('GET',path+(cursor?(path.includes('?')?'&':'?')+new URLSearchParams({cursor}):''));yield*page.items;cursor=page.next_cursor;}while(cursor);}
}
const base64url=bytes=>btoa(String.fromCharCode(...bytes)).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
export async function pkceParameters(clientId,redirectUri,scope='home:read home:write'){
  const verifier=base64url(crypto.getRandomValues(new Uint8Array(48))),state=base64url(crypto.getRandomValues(new Uint8Array(32)));
  const challenge=base64url(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(verifier))));
  return {parameters:{client_id:clientId,redirect_uri:redirectUri,response_type:'code',scope,state,code_challenge:challenge,code_challenge_method:'S256'},verifier,state};
}
