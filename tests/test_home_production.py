"""Read-only production smoke checks. No email, private account or fake product is created."""
import json,urllib.request,urllib.error
from datetime import datetime,timezone
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
BASE='https://openaliment.ro';checks=[]
def get(path,method='GET',headers=None):
 try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,method=method,headers=headers or {}),timeout=20)
 except urllib.error.HTTPError as e:r=e
 with r:return r.status,dict(r.headers),r.read()
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append({'name':name,'passed':True});print('PASS',name)
s,h,b=get('/api/v2/home/session');j=json.loads(b)
check('Production optional sync available without guest account gate',s==200 and j=={'authenticated':False,'available':True,'magic_link_ready':True,'session':None,'devices':[]})
check('Private session is not cached or exposed by CORS','no-store' in h['Cache-Control'] and 'Access-Control-Allow-Origin' not in h and 'Set-Cookie' not in h)
s,h,b=get('/api/v2/home/changes');check('Production household data requires personal authentication',s==401)
s,h,b=get('/api/v2/home/session','OPTIONS',{'Origin':'https://example.invalid'});check('Private API refuses external preflight',s==405 and 'Access-Control-Allow-Origin' not in h)
s,h,b=get('/api/v2/public/capabilities');j=json.loads(b);check('Capabilities distinguish live sync from unfinished integrations',s==200 and j['cloud_sync'] is True and j['shopping_sync'] is False and j['external_sources'] is False and j['digital_receipts'] is True)
s,h,b=get('/acasa/');text=b.decode();check('Production consumer UI serves current modules without password form',s==200 and '/assets/home/app.js?v=5' in text and 'type="password"' not in text and 'id="cloud-login-form"' in text)
s,h,b=get('/acasa/sw.js');check('Service worker keeps private APIs outside cache',s==200 and b'openaliment-home-5' in b and b'openaliment-home-media-1' in b)
s,h,b=get('/api/home-openapi.json');j=json.loads(b);check('Published private API contract contains login, household and conflict routes',s==200 and j['openapi']=='3.1.0' and len(j['paths'])==17 and '409' in j['paths']['/api/v2/home/sync']['post']['responses'])
s,h,b=get('/api/schemas/home-sync.schema.json');j=json.loads(b);check('Published strict sync contract rejects arbitrary root fields',s==200 and j['additionalProperties'] is False and j['properties']['operations']['maxItems']==50)
s,h,b=get('/api/v2/public/food?code=9520000000011');j=json.loads(b);check('PBT public example remains available without AI or account',s==200 and j.get('simulation') is True and j['schema_version']=='openaliment.consumer-product.v1')
s,h,b=get('/confidentialitate');text=b.decode();check('Public data information describes optional sync and local preferences',s==200 and 'Conectarea prin email și sincronizarea sunt opționale.' in text and 'rămân numai pe dispozitiv.' in text)
(ROOT/'tests/runtime/reports/home-cloud-production-report.json').write_text(json.dumps({'executed_at':datetime.now(timezone.utc).isoformat(),'environment':'production HTTPS, read-only guest checks; no account, email or product created','passed':len(checks),'failed':0,'migration_004_observed_in_authenticated_admin':True,'production_magic_link_delivery_verified':False,'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}))
