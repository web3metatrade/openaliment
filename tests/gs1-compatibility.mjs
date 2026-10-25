import assert from 'node:assert/strict';
import {readFile,writeFile} from 'node:fs/promises';
import vm from 'node:vm';
import * as D from '../public/assets/home/domain.js';
const fixture=JSON.parse(await readFile(new URL('./fixtures/gs1-compatibility.json',import.meta.url),'utf8')),checks=[];
function check(name,fn){fn();checks.push({name,passed:true});}
for(const c of fixture.cases){
  let p,accepted;try{p=D.parseCode(c.url,'https://openaliment.ro',fixture.reference_year);accepted=true;}catch{accepted=false;}
  check(c.name+' validity',()=>assert.equal(accepted,c.valid));if(!accepted)continue;
  check(c.name+' identity',()=>{assert.equal(p.gtin14,c.gtin14);assert.deepEqual(p.gs1_qualifiers,c.qualifiers);});
  check(c.name+' date',()=>assert.deepEqual(p.expiry,c.expiry));
}
const base='https://openaliment.ro/01/09520000000011/10/L/21/';
const a=D.parseCode(base+'A'),b=D.parseCode(base+'B');
check('Two serials are two scan identities',()=>assert.notEqual(D.scanIdentity(a),D.scanIdentity(b)));
check('GTIN plus serial remains the same identity across optional qualifiers',()=>assert.equal(D.scanIdentity(a),D.scanIdentity(D.parseCode(base.replace('/10/L','')+'A'))));
const original=D.unknownProfile(a);original.expiry={kind:'best_before',value:'2026-10-30',precision:'day',source:'barcode'};
check('Offline cache cannot copy another units serial or barcode date',()=>{const p=D.cachedProfile(original,b);assert.equal(p.gs1_qualifiers['21'],'B');assert.equal(p.expiry,null);});
check('Inventory export import retains serial and CPV',()=>{const state=D.initial();D.addItem(state,D.unknownProfile(D.parseCode(base.replace('/10/L','/22/2A/10/L')+'A')));assert.deepEqual(D.importState(JSON.parse(JSON.stringify(state))).items[0].profile.gs1_qualifiers,{'22':'2A','10':'L','21':'A'});});
const context={};vm.runInNewContext(await readFile(new URL('../public/assets/vendor/qrcodegen.js',import.meta.url),'utf8'),context);
check('512-byte QR-L reference leaves a four-module quiet zone outside text',()=>{const qr=context.qrcodegen.QrCode.encodeSegments([context.qrcodegen.QrSegment.makeBytes(Array(512).fill(97))],context.qrcodegen.QrCode.Ecc.LOW,1,40,-1,false);assert.ok(qr.size<=77);assert.ok(40-4*5>=0&&40+(qr.size+4)*5<=600);assert.ok(120-4*5>=75&&120+(qr.size+4)*5<570);});
await writeFile(new URL('./runtime/reports/gs1-compatibility-js.json',import.meta.url),JSON.stringify({passed:checks.length,failed:0,checks},null,2));console.log(JSON.stringify({passed:checks.length,failed:0}));
