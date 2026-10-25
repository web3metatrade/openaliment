"""Executable producer → item → approved label → recipe-free lot → QR example.

Requires the standard-library client from /api/sdk/python as openaliment.py on
PYTHONPATH (the repository copy is found automatically). No credentials are saved.
"""
import argparse
import json
import os
import sys
import uuid
from pathlib import Path
from urllib.parse import urlsplit

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'sdk'))
from openaliment import OpenAliment


def run(base_url, token, state_file, publish=False):
    host = urlsplit(base_url).hostname
    local = host in ['127.0.0.1', 'localhost', '::1']
    api = OpenAliment(base_url, token, allow_insecure_loopback=local)
    public = OpenAliment(base_url, allow_insecure_loopback=local)
    state_file = Path(state_file)
    state = json.loads(state_file.read_text(encoding='utf-8')) if state_file.exists() else {'origin': base_url.rstrip('/'), 'job': 'erp-' + uuid.uuid4().hex, 'steps': {}}
    if state['origin'] != base_url.rstrip('/'):
        raise ValueError('Use a separate state file for each environment.')

    def persist():
        state_file.parent.mkdir(parents=True, exist_ok=True)
        temporary = state_file.with_suffix(state_file.suffix + '.tmp')
        temporary.write_text(json.dumps(state, indent=2) + '\n', encoding='utf-8')
        os.chmod(temporary, 0o600)
        temporary.replace(state_file)

    persist()  # Stable operation keys survive a timeout before the first response.

    def create(step, path, body):
        if step in state['steps']:
            return state['steps'][step]
        result = api.request('POST', '/api/v2/manage/' + path, body, idempotency_key=state['job'] + '-' + step)
        state['steps'][step] = result
        persist()
        return result

    product = create('product', 'products', {'name': 'Iaurt natural — exemplu ERP', 'category': 'lactate'})
    item = create('item', 'products/' + product['id'] + '/trade-items', {'name': 'Pahar 200 g'})
    label = create('label-e1', 'trade-items/' + item['id'] + '/labels', {'version_key': 'E1', 'name': 'Iaurt natural', 'category': 'lactate', 'ingredients': 'Lapte, culturi lactice.', 'allergens': 'Lapte.', 'net_quantity': '200 g', 'operator': 'Producător exemplu, adresa declarată'})
    if not publish:
        return {'stage': 'draft', 'product_id': product['id'], 'trade_item_id': item['id'], 'label_id': label['id']}
    create('publish-label-e1', 'labels/' + label['id'] + '/publish', {})
    batch = create('batch', 'trade-items/' + item['id'] + '/batches', {'lot_code': 'ERP-' + state['job'][-12:], 'product_info_version_id': label['id'], 'recipe_version_id': None})
    create('publish-batch', 'batches/' + batch['id'] + '/publish', {})
    qr = api.request('GET', '/api/v2/manage/qr/batch/' + batch['id'])
    approved = public.request('GET', '/api/v2/public/batches/' + batch['id'])
    if approved['product_info_version_id'] != label['id'] or 'recipe_version_id' in approved:
        raise RuntimeError('Unexpected public projection.')
    food = public.food(qr['url'])
    if food['batch_id'] != batch['id'] or food['name'] != 'Iaurt natural':
        raise RuntimeError('QR lookup did not identify the approved batch.')
    return {'stage': 'published', 'product_id': product['id'], 'trade_item_id': item['id'], 'batch_id': batch['id'], 'qr_url': qr['url'], 'digital_link': qr['digital_link'], 'recipe_required': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', required=True, help='HTTPS origin, or HTTP loopback for an isolated test installation')
    parser.add_argument('--state', required=True, type=Path, help='Private operation state, separate for each environment; contains no API secret')
    parser.add_argument('--publish', action='store_true', help='Explicitly approve the example label and lot in the selected account')
    args = parser.parse_args()
    token = os.environ.get('OPENALIMENT_API_KEY')
    if not token:
        parser.error('Set OPENALIMENT_API_KEY privately; never pass it in the command line or state file.')
    print(json.dumps(run(args.base_url, token, args.state, args.publish), ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
