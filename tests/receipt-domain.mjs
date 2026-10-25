import assert from 'node:assert/strict';
import {readFile,writeFile} from 'node:fs/promises';
import * as D from '../public/assets/home/domain.js';
import {cleanReceipt,mergeReceipt} from '../public/assets/home/receipt.js';
const receipt=JSON.parse(await readFile(new URL('./fixtures/digital-receipt.json',import.meta.url),'utf8')),checks=[];
function test(name,fn){fn();checks.push({name,passed:true});console.log('PASS '+name);}
test('Different merchant format arrives as the same receipt contract',()=>assert.equal(cleanReceipt(receipt).lines.length,2));
const state=D.initial();test('Two identical packs remain separate physical packages',()=>{assert.equal(mergeReceipt(state,receipt,new Map()),3);assert.equal(state.items.length,3);assert.notEqual(state.items[0].id,state.items[1].id);});
test('Unknown dates and nutrition remain unknown',()=>{assert.equal(state.items[0].expiry,null);assert.equal(state.items[0].profile.food_data.nutrition.values,null);});
test('Simulated receipt is visible as simulation',()=>assert.equal(state.items[0].profile.simulation,true));
test('Receipt repeated after consume does not resurrect packages',()=>{D.changeItem(state,state.items[0].id,'consume');assert.equal(mergeReceipt(state,receipt,new Map()),0);});
test('Receipt provenance survives export/import validation',()=>{const restored=D.importState(JSON.parse(JSON.stringify(state)));assert.deepEqual(restored.items[0].receipt,state.items[0].receipt);assert.equal(mergeReceipt(restored,receipt,new Map()),0);});
test('Partial package consumption does not affect identical package',()=>assert.equal(state.items[1].state,'available'));
test('Receipt without GTIN can still be added',()=>{const r=structuredClone(receipt);r.id=D.id();r.lines=[{...r.lines[0],gtin14:null,quantity:1}];assert.equal(mergeReceipt(D.initial(),r,new Map()),1);});
test('Duplicate receipt line IDs rejected before mutation',()=>{const r=structuredClone(receipt);r.lines[1].line_id=r.lines[0].line_id;assert.throws(()=>mergeReceipt(D.initial(),r,new Map()));});
test('Conflicting receipt and producer dates require confirmation',()=>{const r=structuredClone(receipt);r.lines=r.lines.slice(0,1);r.lines[0].quantity=1;r.lines[0].expiry={kind:'use_by',value:'2026-11-01',precision:'day',source:'receipt'};const p=D.unknownProfile();p.expiry={kind:'use_by',value:'2026-11-02',precision:'day',source:'producer'};const s=D.initial();mergeReceipt(s,r,new Map([[r.lines[0].line_id,p]]));assert.equal(s.items[0].expiry,null);assert.match(s.items[0].profile.notices[0],/diferă/);});
test('Oversized receipt inventory rejected without partial changes',()=>{const r=structuredClone(receipt);r.lines[0].quantity=2000;const s=D.initial();assert.throws(()=>mergeReceipt(s,r,new Map()));assert.equal(s.items.length,0);});
await writeFile(new URL('./runtime/reports/receipt-client-report.json',import.meta.url),JSON.stringify({executed_at:new Date().toISOString(),environment:'pure client domain with simulated retailer fixture',passed:checks.length,failed:0,checks},null,2));
