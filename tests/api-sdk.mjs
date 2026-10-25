import fs from 'node:fs';
import {OpenAliment,pkceParameters,ApiError} from '../tools/sdk/openaliment.mjs';
const base=process.env.OA_TEST_BASE_URL,fixture=JSON.parse(fs.readFileSync(new URL('./runtime/api-platform-fixtures.json',import.meta.url))),checks=[];
const check=(name,value)=>{if(!value)throw Error(name);checks.push({name,passed:true});console.log('PASS '+name);};
const api=new OpenAliment({baseUrl:base,allowLoopback:true});
check('JS SDK resolves demo without credentials',(await api.food('9520000000011')).simulation);
const privateApi=new OpenAliment({baseUrl:base,token:fixture.read_key,allowLoopback:true});let found=false;for await(const product of privateApi.collection('/api/v2/manage/products?limit=1'))if(product.id===fixture.product_id)found=true;
check('JS SDK paginates private collection',found);
try{new OpenAliment({baseUrl:base});throw Error('Unexpected insecure base');}catch(error){check('JS SDK rejects insecure origin by default',error.message==='Folosește originea HTTPS a platformei.');}
try{await privateApi.request('GET','//evil.invalid');throw Error('Unexpected external path');}catch(error){check('JS SDK cannot forward token outside origin',error.message==='Cale API incorectă.');}
try{await new OpenAliment({baseUrl:base,token:'oa_'+'0'.repeat(64),allowLoopback:true}).request('GET','/api/v2/manage/products');throw Error('Unexpected success');}catch(error){check('JS SDK returns structured authorization error',error instanceof ApiError&&error.status===401&&Boolean(error.requestId));}
const pending=await pkceParameters('a'.repeat(32),'ro.exemplu.aplicatie:/callback');check('JS SDK builds PKCE S256 with fresh state',pending.parameters.code_challenge.length===43&&pending.verifier.length>=43&&pending.parameters.state===pending.state);
fs.writeFileSync(new URL('./runtime/reports/api-sdk-javascript.json',import.meta.url),JSON.stringify({passed:checks.length,failed:0,checks},null,2));console.log(JSON.stringify({passed:checks.length,failed:0}));
