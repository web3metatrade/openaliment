"""Check isolated deployment targets and synthetic import reproducibility, offline."""
import hashlib
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def load(name, relative):
    spec = importlib.util.spec_from_file_location(name, ROOT / relative)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


prepare = load('deploy_prepare', 'tools/deploy/prepare.py').prepare
client = load('deploy_client', 'tools/deploy/client.py')
legacy = load('legacy_fixture', 'tests/generators/legacy_handover.py')


class CollaborationTests(unittest.TestCase):
    def setUp(self):
        (ROOT / 'tests/runtime').mkdir(parents=True, exist_ok=True)
        self.temporary = tempfile.TemporaryDirectory(prefix='collaboration-', dir=ROOT / 'tests/runtime')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)

    def test_staging_and_production_credentials_are_distinct_and_host_bound(self):
        production = prepare(self.root)
        staging = prepare(self.root, environment='staging')
        first = json.loads(Path(production['private_config']).read_text())
        second = json.loads(Path(staging['private_config']).read_text())
        self.assertNotEqual(first['key'], second['key'])
        self.assertNotEqual(first['connection_id'], second['connection_id'])
        self.assertEqual(second['endpoint'], 'https://dev.openaliment.ro/openaliment-connect.php')
        self.assertEqual(first['endpoint'], 'https://openaliment.ro/openaliment-connect.php')
        for result, credential, host in [(production, first, 'openaliment.ro'), (staging, second, 'dev.openaliment.ro')]:
            script = Path(result['upload_file']).read_text()
            self.assertIn("const OA_DEPLOY_HOST = '" + host + "';", script)
            self.assertNotIn(credential['key'], script)
            client.Client(Path(result['private_config']))
        second['endpoint'] = first['endpoint']
        Path(staging['private_config']).write_text(json.dumps(second))
        with self.assertRaises(client.DeployError):
            client.Client(Path(staging['private_config']))

    def test_generating_staging_preserves_existing_production_credentials(self):
        production = prepare(self.root)
        before = Path(production['private_config']).read_bytes()
        prepare(self.root, environment='staging')
        self.assertEqual(Path(production['private_config']).read_bytes(), before)
        with self.assertRaises(RuntimeError):
            prepare(self.root, environment='staging')

    def test_unknown_environment_is_rejected_before_creating_files(self):
        with self.assertRaises(ValueError):
            prepare(self.root, environment='other')
        self.assertEqual(list(self.root.iterdir()), [])

    def test_synthetic_legacy_package_has_a_reproducible_complete_manifest(self):
        first = legacy.build(self.root / 'handover')
        second = legacy.build(self.root / 'handover')
        self.assertEqual(first, second)
        self.assertFalse(first['historical_materials_read'])
        for line in (self.root / 'handover/HASHES.sha256').read_text().splitlines():
            digest, relative = line.split('  ', 1)
            self.assertEqual(hashlib.sha256((self.root / 'handover' / relative).read_bytes()).hexdigest(), digest)
        seed = json.loads((self.root / 'handover/04_import/product_pbt.json').read_text(encoding='utf-8'))
        self.assertTrue(seed['synthetic'])
        self.assertEqual(seed['schema_version'], 'openbread.product_seed.v0.1')

    def test_synthetic_package_cannot_write_into_originals(self):
        with self.assertRaises(ValueError):
            legacy.build(ROOT / 'OpenBread_Codex_v1_2026-10-23')


if __name__ == '__main__':
    result = unittest.TextTestRunner().run(unittest.defaultTestLoader.loadTestsFromTestCase(CollaborationTests))
    failed = len(result.failures) + len(result.errors)
    print(json.dumps({'passed': result.testsRun - failed, 'failed': failed}))
    raise SystemExit(0 if result.wasSuccessful() else 1)
