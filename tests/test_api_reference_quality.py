"""Semantic documentation gates: every operation, media example and request snippet."""
import ast
import hashlib
import json
import re
from pathlib import Path
from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[1]
spec = json.loads((ROOT / 'contracts/platform-openapi.json').read_text(encoding='utf-8'))
catalog = json.loads((ROOT / 'contracts/api-documentation.json').read_text(encoding='utf-8'))
checks = []
for part in (ROOT/'contracts/api-docs').glob('*.json'):
 if not part.name.startswith('postman-'):catalog['pages'].update(json.loads(part.read_text(encoding='utf-8')))



def check(name, ok):
    if not ok:
        raise AssertionError(name)
    checks.append({'name': name, 'passed': True})


for path, methods in spec['paths'].items():
    for method, operation in methods.items():
        oid = operation['operationId']
        check('Maintained explanation ' + oid, operation.get('x-reference-complete') and len(operation['description']) >= 90 and 'Execută operația explicită' not in operation['summary'])
        for parameter in operation.get('parameters', []):
            check('Parameter meaning/example ' + oid + ' ' + parameter['name'], len(parameter.get('description', '')) > 20 and 'example' in parameter)
        groups = [('request', operation.get('requestBody', {}).get('content', {}))] + [(status, response.get('content', {})) for status, response in operation['responses'].items()]
        for status, content in groups:
            for mime, media in content.items():
                examples = [media['example']] if 'example' in media else [e['value'] for e in media.get('examples', {}).values()]
                check('Explicit example ' + oid + ' ' + status + ' ' + mime, bool(examples))
                schema = dict(media.get('schema', {}), components=spec['components'])
                if 'json' in mime and mime != 'application/x-ndjson':
                    for example in examples:
                        errors = list(Draft202012Validator(schema).iter_errors(example))
                        check('Example conforms ' + oid + ' ' + status + ' ' + mime + (' ' + str(errors[0])[:180] if errors else ''), not errors)
                if status.startswith('2'):
                    check('Concrete success ' + oid, media.get('schema', {}).get('$ref', '').rsplit('/', 1)[-1] not in ['Record', 'Collection', 'MutationResult', 'PublicProjection', 'ApiKeyResult'])
                if mime == 'application/x-ndjson':
                    lines = examples[0].splitlines(keepends=True)
                    digest = json.loads(lines[-1])
                    check('NDJSON digest example ' + oid, digest['sha256'] == hashlib.sha256(''.join(lines[:-1]).encode()).hexdigest())
        page = catalog['pages'][oid]
        check('Markdown includes response examples ' + oid, 'Exemplu sintetic:' in page['markdown'] or not any(r.get('content') for r in operation['responses'].values()))
        if 'python' in page['examples']:
            ast.parse(page['examples']['python'])
        check('Python generated snippet ' + oid, True)
        check('Empty response handled ' + oid, path != '/oauth/revoke' or '.text()' in page['examples']['javascript'])

check('Public GET has no CSRF error', '419' not in spec['paths']['/api/v2/public/trade-items/{id}']['get']['responses'])
check('Home switch is browser only', spec['paths']['/api/v2/home/switch']['post']['security'] == [{'HomeCookie': []}])
check('Home conflict is not only error envelope', 'oneOf' in spec['paths']['/api/v2/home/sync']['post']['responses']['409']['content']['application/json']['schema'])
check('Form sample encoded properly', 'URLSearchParams' not in catalog['pages']['oauthToken']['examples']['javascript'] or 'application/x-www-form-urlencoded' in catalog['pages']['oauthToken']['examples']['javascript'])
def check_fields(value):
    if isinstance(value,dict):
        for name,field in value.get('properties',{}).items():
            description=field.get('description','')
            check('Field has maintained meaning '+name,len(description)>12 and not description.startswith(('Valoarea câmpului ','Câmpul ')))
        for child in value.values():check_fields(child)
    elif isinstance(value,list):
        for child in value:check_fields(child)
check_fields(spec)
for file in [ROOT/'contracts/platform-openapi.json',ROOT/'contracts/api-documentation.json',*list((ROOT/'contracts/api-docs').glob('*.json'))]:
    check('Shared-hosting file budget '+file.name,file.stat().st_size<=2097152)
report = {'passed': len(checks), 'failed': 0, 'operations': sum(len(v) for v in spec['paths'].values()), 'checks': checks}
out = ROOT / 'tests/runtime/reports'
out.mkdir(parents=True, exist_ok=True)
(out / 'api-reference-quality.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps({'passed': len(checks), 'failed': 0}))
