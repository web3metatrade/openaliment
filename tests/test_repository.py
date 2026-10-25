"""Release selection and determinism, using temporary files only; no DB or network."""
import hashlib
import importlib.util
import json
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('release_builder', ROOT / 'tools/build/package.py')
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


class ReleasePolicyTests(unittest.TestCase):
    def setUp(self):
        self.policy = json.loads(package.MANIFEST.read_text(encoding='utf-8'))

    def test_private_state_and_unexpected_public_executables_are_rejected(self):
        for path in ['config/local.php', 'config/mail.local.php', 'config/another-secret.php', 'app/.env', '.tools/deploy/production.json', 'tests/runtime/home-fixtures.json', 'storage/private/photo.jpg', 'output/imagegen/source.png', 'docs/verification/admin.png', 'docs/ux/evidence/admin.jpg', 'public/openaliment-connect.php', 'public/assets/backdoor.php', 'app/../config/local.php']:
            with self.subTest(path=path):
                self.assertFalse(package.allowed(path, self.policy))

    def test_runtime_profile_is_complete_without_private_or_historical_payloads(self):
        files = package.collect('app')
        for path in ['app/autoload.php', 'app/bootstrap.php', 'app/Support/view_helpers.php', 'public/index.php', 'config/config.example.php', 'storage/.htaccess', 'database/migrations/004_home.sql', 'contracts/home-openapi.json', 'LICENSE']:
            self.assertIn(path, files)
        self.assertFalse(any(path.startswith(('tests/', '.tools/', 'OpenBread_Codex_v1_2026-10-23/')) for path in files))

    def test_full_profile_preserves_historical_import_path_and_compatibility_commands(self):
        if not (ROOT / 'OpenBread_Codex_v1_2026-10-23').is_dir():
            for profile in ['full', 'pilot']:
                with self.assertRaisesRegex(ValueError, 'Required release directory missing: OpenBread_Codex_v1_2026-10-23'):
                    package.collect(profile)
            return
        files = package.collect('full')
        self.assertIn('OpenBread_Codex_v1_2026-10-23/HASHES.sha256', files)
        self.assertIn('tools/build/contracts.py', files)
        self.assertIn('tools/build-contracts.py', files)

    def test_identical_inputs_make_identical_zip_and_verified_manifest(self):
        with tempfile.TemporaryDirectory(prefix='package-', dir=ROOT / 'tests/runtime') as directory:
            first = package.build('app', Path(directory))
            second = package.build('app', Path(directory))
            self.assertEqual(first['sha256'], second['sha256'])
            with zipfile.ZipFile(second['archive']) as archive:
                manifest = json.loads(archive.read('RELEASE_MANIFEST.json'))
                for name, expected in manifest['files_sha256'].items():
                    self.assertEqual(hashlib.sha256(archive.read(name)).hexdigest(), expected)

    def test_output_cannot_land_under_document_root(self):
        with self.assertRaises(ValueError):
            package.build('app', ROOT / 'public')


if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(ReleasePolicyTests)
    result = unittest.TextTestRunner().run(suite)
    failed = len(result.failures) + len(result.errors)
    print(json.dumps({'passed': result.testsRun - failed, 'failed': failed}))
    raise SystemExit(0 if result.wasSuccessful() else 1)
