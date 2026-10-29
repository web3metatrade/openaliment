"""Every documented operation must have an actual schema-validated HTTP success."""
import json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=json.loads((ROOT/'contracts/platform-openapi.json').read_text(encoding='utf-8'))
expected={operation['operationId'] for methods in spec['paths'].values() for operation in methods.values()}
observed=set()
for name in ['api-reference-http.json','readiness-http.json','marketplace-http.json','external-http.json','anaf-http.json','program-http.json']:
    report=json.loads((ROOT/'tests/runtime/reports'/name).read_text(encoding='utf-8'))
    if report.get('failed')!=0:raise AssertionError('Failed HTTP suite: '+name)
    observed.update(report['successful_operations'])
missing=sorted(expected-observed)
result={'passed':len(expected)-len(missing),'failed':len(missing),'expected_operations':len(expected),'successful_operations':len(observed),'missing':missing,'environment':'isolated schema-validated HTTP; no production mutation'}
(ROOT/'tests/runtime/reports/api-operation-coverage.json').write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8')
print(json.dumps(result))
if missing:raise SystemExit(1)
