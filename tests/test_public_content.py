"""Keep public integration documentation separate from internal delivery notes."""
import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
catalog = json.loads((ROOT / 'contracts/api-documentation.json').read_text(encoding='utf-8'))
pages = {}
for path in (ROOT / 'contracts/api-docs').glob('*.json'):
    if not path.name.startswith('postman-'):
        pages.update(json.loads(path.read_text(encoding='utf-8')))
checks = []


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    checks.append(name)


internal = re.compile(r'(?<![\w/-])(?:docs/(?:development|verification|updates)/|tests/|tools/|app/(?:Core|Controllers)/|(?:\.\./)+(?:contracts|tools|docs)/|(?:OPENBREAD_MASTER|GS1_IMPLEMENTATION|ACASA_SINCRONIZARE|INTEGRARI_PARTENERI|IMPORT_EXPORT|API_ACTION_INVENTORY)[\w.-]*\.md)', re.I)
draft = re.compile(r'\b(?:TODO|FIXME|TBD|lorem ipsum)\b|urmează într-o etapă|necesită implementare|acest increment|în curs de pregătire|conform planului de implementare', re.I)
known_docs = {p['path'] for p in pages.values()}
check('All indexed pages inspected', set(pages) == set(catalog['pages']))
for page in pages.values():
    markdown = page['markdown']
    prose = re.sub(r'```.*?```', '', markdown, flags=re.S)
    check('No internal development references: ' + page['id'], not internal.search(prose))
    check('No unfinished delivery notes: ' + page['id'], not draft.search(prose))
    for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', markdown):
        check('Public link target: ' + page['id'] + ' ' + target, target.startswith(('https://', '/')) or target.startswith('#'))
        if target.startswith('/api/docs/guides/') or target.startswith('/api/docs/reference/'):
            check('Documented page exists: ' + target, target.split('#')[0].removesuffix('.md') in known_docs)
        if page['kind'] == 'guide':
            check('Guide link preserved in HTML: ' + page['id'] + ' ' + target, 'href="' + html.escape(target, quote=True) + '"' in page['html'])
    if page['kind'] == 'guide':
        check('Guide title matches page title: ' + page['id'], markdown.startswith('# ' + page['title'] + '\n'))

complete = json.loads((ROOT / 'contracts/api-developer-guide.json').read_text(encoding='utf-8'))['markdown']
check('Combined guide excludes internal instructions', not internal.search(complete) and not draft.search(complete))
for page in pages.values():
    if page['kind'] == 'guide':
        shifted = re.sub(r'^(#{1,5}) ', r'\1# ', page['markdown'], flags=re.M)
        check('Combined guide includes full portal guide: ' + page['id'], shifted in complete)
postman = json.loads((ROOT / 'contracts/api-postman-collection.json').read_text(encoding='utf-8'))
check('Postman operation count matches contract', postman['info']['description'].startswith(str(catalog['operation_count']) + ' operații.'))

for path in (ROOT / 'app/Views').glob('*.php'):
    text = path.read_text(encoding='utf-8')
    check('Template excludes draft notes: ' + path.name, not draft.search(text))
    # Input placeholders and API parameter notation are purposeful; landing copy
    # must use the actual demonstration identifier instead of a dummy URL.
    if path.name in ['home.php', 'digital-link-guide.php']:
        check('Landing has no dummy GTIN or lot URL: ' + path.name, not re.search(r'\{(?:GTIN|LOT)\}', text))
for directory in ['app/Core', 'app/Controllers', 'app/Domain']:
    for path in (ROOT / directory).glob('*.php'):
        source = path.read_text(encoding='utf-8')
        check('Runtime excludes misleading update promises: ' + path.name, not draft.search(source) and 'se actualizează.' not in source)
check('Demonstration remains explicit', 'DEMONSTRAȚIE · FĂRĂ VÂNZARE' in (ROOT / 'app/Views/demo-bread.php').read_text(encoding='utf-8'))
check('Pilot provenance remains experimental', 'R1 experimentală' in (ROOT / 'app/Views/manage.php').read_text(encoding='utf-8'))
check('Unsupported external lookup remains explicit', 'external_product_sources=false' in complete)
check('Public guide cannot include internal reports wholesale', "ROOT/'docs'/file" not in (ROOT / 'tools/build/api_documentation.py').read_text(encoding='utf-8'))
report = {'passed': len(checks), 'failed': 0, 'pages': len(pages), 'templates': len(list((ROOT / 'app/Views').glob('*.php'))), 'checks': checks}
(ROOT / 'tests/runtime/reports').mkdir(parents=True, exist_ok=True)
(ROOT / 'tests/runtime/reports/public-content.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps({k: v for k, v in report.items() if k != 'checks'}))
