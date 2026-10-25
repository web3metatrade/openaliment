"""Validate actual local HTTP responses against the published aggregate contract."""
import json
import re
import urllib.parse
from pathlib import Path
from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[1]
SPEC = json.loads((ROOT / 'contracts/platform-openapi.json').read_text(encoding='utf-8'))
OBSERVED = set()
COUNT = 0


def validate(path, method, status, headers, body):
    global COUNT
    plain = urllib.parse.urlsplit(path).path
    for template, methods in SPEC['paths'].items():
        regex = re.sub(r'\{[^}]+\}', '[^/]+', template)
        if not re.fullmatch(regex, plain) or method.lower() not in methods:
            continue
        operation = methods[method.lower()]
        response = operation['responses'].get(str(status))
        if response and response.get('$ref','').startswith('#/components/responses/'):
            response=SPEC['components']['responses'][response['$ref'].rsplit('/',1)[-1]]
        if response is None:
            raise AssertionError(operation['operationId'] + ': undocumented HTTP ' + str(status))
        if isinstance(body, (dict, list)):
            mime = headers.get('Content-Type', '').split(';')[0]
            media = response.get('content', {}).get(mime)
            if media is None:
                raise AssertionError(operation['operationId'] + ': undocumented media ' + mime)
            schema = dict(media['schema'], components=SPEC['components'])
            errors = list(Draft202012Validator(schema).iter_errors(body))
            if errors:
                error = errors[0]
                raise AssertionError(operation['operationId'] + ' HTTP ' + str(status) + ' at ' + str(list(error.path)) + ': ' + error.message[:300])
        elif not response.get('content') and body not in [b'', ''] and not template.endswith('authorize'):
            raise AssertionError(operation['operationId'] + ': unexpected response body')
        COUNT += 1
        if status < 400:
            OBSERVED.add(operation['operationId'])
        return


def report():
    return {'response_checks': COUNT, 'successful_operations': sorted(OBSERVED)}
