"""Guarantee program routing, schemas, UI actions and authored content stay aligned."""
import json,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'tools/build'))
from program_matrix import document
routes=json.loads((ROOT/'contracts/program/routes.json').read_text('utf8'));spec=json.loads((ROOT/'contracts/platform-openapi.json').read_text('utf8'));checks=[]
def check(name,yes):
 if not yes:raise AssertionError(name)
 checks.append(name)
seen=set();handlers=set();source=(ROOT/'app/Domain/ProgramService.php').read_text('utf8')
for route in routes:
 pair=(route['method'],route['path']);check('Unique route '+str(pair),pair not in seen);seen.add(pair)
 check('Documented '+route['id'],route['path'] in spec['paths'] and route['method'].lower() in spec['paths'][route['path']])
 check('Shared service handles '+route['handler'],"'"+route['handler'][8:]+"'" in source);handlers.add(route['handler'][8:])
 if route['body']:check('Typed body '+route['id'],route['body'] in spec['components']['schemas'])
 if route['auth']=='program':check('Dedicated OAuth scope '+route['id'],route['scope'].split(':')[0] in ['learning','partners','contributors','assets'])
 if route['auth']=='public':check('Only public projections are anonymous',route['method']=='GET' and '/public/' in route['path'])
 if route['method'] in ['POST','PUT','DELETE'] and route['auth'] in ['program-session','program-staff','session-optional']:
  parameters=spec['paths'][route['path']][route['method'].lower()]['parameters']
  check('Browser CSRF is explicitly required '+route['id'],any(p['name']=='X-CSRF-Token' and p.get('required') for p in parameters))
for view in (ROOT/'app/Views/program').glob('*.php'):
 for action in re.findall(r"\$form\('([a-z_]+)'",view.read_text('utf8')):check('UI action '+action,action in handlers or action in ['admin_'])
check('Action matrix is current',(ROOT/'docs/api/PROGRAM_ACTION_MATRIX.md').read_text('utf8')==document())
content=json.loads((ROOT/'contracts/program/learning-content.json').read_text('utf8'))
lessons=[l for m in content['modules'] for l in m['lessons']];check('32 complete lessons',len(lessons)==32 and all(len(l['body'])>150 and l['resources'] for l in lessons))
check('Public exercise explanations are separate from exam questions','questions' not in content)
check('Real bank is not in the published contract',not list((ROOT/'contracts/program').glob('*bank*')))
report={'passed':len(checks),'failed':0};(ROOT/'tests/runtime/reports/program-contracts.json').write_text(json.dumps(report)+'\n',encoding='utf8');print(json.dumps(report))
