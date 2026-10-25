"""Bounded read-only documentation smoke; never uses credentials or creates records."""
import argparse
import ast
import hashlib
import html
import json
import re
import urllib.request
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url',default='https://openaliment.ro')
    args=parser.parse_args();base=args.base_url.rstrip('/')
    if base!='https://openaliment.ro' and not re.fullmatch(r'http://127\.0\.0\.1:[0-9]+',base):
        parser.error('Only the production origin or a loopback test origin is allowed.')
    checks=[]
    def check(name,ok):
        if not ok:raise AssertionError(name)
        checks.append({'name':name,'passed':True})
    def get(path):
        request=urllib.request.Request(base+path,headers={'User-Agent':'OpenAliment-Docs-QA/0.5.2','Accept':'*/*'})
        with urllib.request.urlopen(request,timeout=30) as response:
            data=response.read(12000000)
            check('HTTPS read '+path,response.status==200)
            return response.headers,data
    _,data=get('/api/docs/index.json');index=json.loads(data)
    expected=json.loads((ROOT/'contracts/api-documentation.json').read_text(encoding='utf-8'))
    count=expected['operation_count'];pages=len(expected['navigation'])
    check('Operation and guide counts match tested release',index['operation_count']==count and index['guide_count']==expected['guides'] and len(index['pages'])==pages)
    _,data=get('/api/platform-openapi.json')
    check('Published OpenAPI hash matches tested artifact',hashlib.sha256(data).digest()==hashlib.sha256((ROOT/'contracts/platform-openapi.json').read_bytes()).digest())
    spec=json.loads(data)
    for oid in ['manageProductCreate','manageBatchCreate','homePostApiV2HomeSync','consumerGetApiV2PublicFood']:
        path='/api/docs/reference/'+oid
        _,body=get(path);body=body.decode('utf-8');_,markdown=get(path+'.md')
        embedded=re.search(r'<template id="api-page-markdown">(.*?)</template>',body,re.S)
        check('Copy Page equals Markdown '+oid,bool(embedded) and html.unescape(embedded[1])==markdown.decode('utf-8'))
        check('Response example and explorer '+oid,'Exemplu de răspuns' in body and 'data-api-explorer' in body and 'data-explorer-example' in body)
    for slug in ['modele-date','erori-recuperare','exemple-executabile','testare']:
        _,body=get('/api/docs/guides/'+slug)
        check('New guide '+slug,b'data-docs-page="'+slug.encode()+b'"' in body)
    _,data=get('/api/docs/postman.json');collection=json.loads(data)
    check('Postman full collection has all requests',sum(len(group['item']) for group in collection['item'])==count)
    check('Postman contains response examples',sum(len(item['response']) for group in collection['item'] for item in group['item'])>count)
    check('Postman contains no real access token',next(v['value'] for v in collection['variable'] if v['key']=='access_token')=='')
    _,data=get('/api/docs/examples/erp-workflow.py');ast.parse(data.decode('utf-8'));check('Executable workflow downloadable',b'recipe_version_id' in data and b'publish' in data)
    _,data=get('/llms.txt');check('AI index complete',data.count(b'.md)')==pages)
    _,data=get('/llms-full.txt');check('AI corpus includes detailed guides and operations',b'expected_revision' in data and b'Idempotency-Key' in data and b'manageBatchCreate' in data)
    _,data=get('/api/v2/public/food?code=9520000000011');food=json.loads(data)
    check('PBT remains an explicit demonstration',food['simulation'] is True and food['name']=='Pâine Bună Tehnic' and food['food_data']['allergens']['status']=='unknown')
    _,data=get('/api/manifest.json');manifest=json.loads(data)
    check('API remains activated and independent of private recipes',manifest['authentication']['oauth']['available'] and manifest['capabilities']['private_recipe_in_public_context'] is False)
    report={'environment':'read-only HTTPS; no credentials, email or production mutations','passed':len(checks),'failed':0,'checks':checks}
    out=ROOT/'tests/runtime/reports/api-reference-production.json';out.write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
    print(json.dumps({'passed':len(checks),'failed':0}))


if __name__=='__main__':main()
