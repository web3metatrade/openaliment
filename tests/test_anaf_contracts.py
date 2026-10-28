"""Registration actions remain synchronized with routes, schemas and AI documentation."""
import json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];spec=json.loads((ROOT/'contracts/platform-openapi.json').read_text(encoding='utf-8'))
ops={v['operationId']:v for methods in spec['paths'].values() for v in methods.values()};checks=[]
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append(name)
matrix=json.loads((ROOT/'docs/api/anaf-action-coverage.json').read_text(encoding='utf-8'))
for action in matrix['actions']:check('Documented API action '+action['action'],action['operation_id'] in ops)
ids=['organizationLookup','organizationLookupResult','organizationLegalProfile','organizationLegalHistory','organizationLegalCheck','organizationLegalConfirm','adminOrganizationLegalProfile']
for oid in ids:
 op=ops[oid];check('Complete reference '+oid,op.get('x-reference-complete') is True and len(op['description'])>80)
check('Read access to profile is scoped',ops['organizationLegalProfile']['x-required-scope']=='read')
check('Confirmation requires delegated write scope',ops['organizationLegalConfirm']['x-required-scope']=='write')
check('Anonymous lookup requires CSRF',any(p['name']=='X-CSRF-Token' and p['required'] for p in ops['organizationLookup']['parameters']))
check('Lookup response recovery includes stale-state errors','409' in ops['organizationLookupResult']['responses'])
check('Network checks are not wrapped in idempotent SQL transactions',not any(p['name']=='Idempotency-Key' for p in ops['organizationLegalCheck']['parameters']))
check('Profile confirmation uses an idempotency key',any(p['name']=='Idempotency-Key' for p in ops['organizationLegalConfirm']['parameters']))
fields=json.loads((ROOT/'contracts/anaf-fields.json').read_text(encoding='utf-8'))
check('58 source fields are maintained in one mapping',len(fields['fields'])==58 and len(fields['groups'])==7)
check('Renderer uses the same mapping as the API builder','contracts/anaf-fields.json' in (ROOT/'app/Domain/AnafPresentation.php').read_text(encoding='utf-8'))
guide=(ROOT/'docs/api/pages/inregistrare-anaf.md').read_text(encoding='utf-8')
for word in ['cookie','CSRF','not_found','expected_version_id','directly_verified','503','409']:check('Guide covers '+word,word in guide)
check('Existing registration supports both contracts','anyOf' in spec['components']['schemas']['Registration'])
check('Source records permit future fields',spec['components']['schemas']['AnafRecord']['additionalProperties'] is True)
print(json.dumps({'passed':len(checks),'failed':0}))
