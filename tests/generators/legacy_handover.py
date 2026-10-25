"""Build a synthetic legacy package; never read or redistribute the historical pilot."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DESTINATION = ROOT / 'tests/runtime/legacy-handover'


def build(destination=DESTINATION):
    destination = Path(destination).resolve()
    runtime = (ROOT / 'tests/runtime').resolve()
    if not destination.is_relative_to(runtime):
        raise ValueError('Synthetic packages must stay in tests/runtime.')
    files = {}

    def document(name, value):
        files[name] = (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode('utf-8')

    marker = {'synthetic': True, 'purpose': 'Legacy import regression; no original recipe, costs or documents.'}
    document('SYNTHETIC_TEST_PACKAGE.json', marker)
    document('04_import/product_pbt.json', {
        **marker, 'schema_version': 'openbread.product_seed.v0.1',
        'name': 'Produs sintetic pentru import OpenBread',
    })
    recipe = {**marker, 'schema_version': 'openbread.recipe.v0.1',
              'basis': {'external_input_total_g': 3790}, 'formula': [], 'process': []}
    document('04_import/recipe_r1_general.json', recipe)
    document('04_import/recipe_r1.json', recipe)
    for name in ['cost3_scenarios', 'document_catalog', 'submission_pbt_draft', 'field_mapping']:
        document('04_import/' + name + '.json', {**marker, 'items': []})
    for number in range(1, 19):
        name = 'Paine_Buna_Tehnic_BT01_Pachet_tipografie.zip' if number == 6 else f'synthetic-{number:02}.txt'
        files['01_originale/' + name] = f'SYNTHETIC LEGACY SOURCE {number}; not an original or usable archive.\n'.encode()
        files[f'02_extrase_text/synthetic-{number:02}.txt'] = f'SYNTHETIC EXTRACT {number}; no historical text.\n'.encode()
    for number in range(1, 8):
        files[f'03_bt01_recuperat/synthetic-{number:02}.txt'] = f'SYNTHETIC RECOVERY {number}; not a validated result.\n'.encode()
    for relative, contents in files.items():
        path = destination / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        if path.is_symlink():
            raise ValueError('Synthetic output cannot be a symlink.')
        path.write_bytes(contents)
    manifest = ''.join(hashlib.sha256(contents).hexdigest() + '  ' + relative + '\n'
                       for relative, contents in sorted(files.items()))
    (destination / 'HASHES.sha256').write_text(manifest, encoding='utf-8', newline='\n')
    return {'synthetic': True, 'files': len(files), 'historical_materials_read': False,
            'manifest_sha256': hashlib.sha256(manifest.encode()).hexdigest()}


if __name__ == '__main__':
    print(json.dumps(build()))
