"""Exercise PR routing, including fork branches that impersonate release refs."""
import importlib.util
import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('branch_policy', ROOT / 'tools/qa/branch_policy.py')
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)
REPOSITORY = 'web3metatrade/openaliment'


def request(base, head, repository=REPOSITORY):
    return {'pull_request': {'base': {'ref': base}, 'head': {
        'ref': head, 'repo': {'full_name': repository} if repository else None,
    }}}


class BranchPolicyTests(unittest.TestCase):
    def test_contributions_and_release_sync_target_dev(self):
        for head, repository in [('feature/qr', 'contributor/openaliment'), ('fix/api', REPOSITORY),
                                 ('main', REPOSITORY), ('dev', 'contributor/openaliment')]:
            with self.subTest(head=head, repository=repository):
                self.assertTrue(policy.validate('pull_request', request('dev', head, repository), REPOSITORY)[0])

    def test_main_accepts_repository_dev_and_explicit_hotfix(self):
        for head in ['dev', 'hotfix/qr-regression']:
            with self.subTest(head=head):
                self.assertTrue(policy.validate('pull_request', request('main', head), REPOSITORY)[0])

    def test_feature_branches_and_forked_release_refs_cannot_target_main(self):
        for head, repository in [('feature/qr', REPOSITORY), ('fix/api', REPOSITORY),
                                 ('dev', 'contributor/openaliment'), ('hotfix/fix', 'contributor/openaliment'),
                                 ('hotfix/', REPOSITORY), ('dev', None)]:
            with self.subTest(head=head, repository=repository):
                self.assertFalse(policy.validate('pull_request', request('main', head, repository), REPOSITORY)[0])

    def test_unknown_and_missing_targets_fail_closed(self):
        self.assertFalse(policy.validate('pull_request', request('unexpected', 'feature/qr'), REPOSITORY)[0])
        self.assertFalse(policy.validate('pull_request', {}, REPOSITORY)[0])

    def test_push_and_manual_checks_do_not_create_a_deployment(self):
        for event in ['push', 'workflow_dispatch']:
            self.assertTrue(policy.validate(event, {}, REPOSITORY)[0])


if __name__ == '__main__':
    result = unittest.TextTestRunner().run(unittest.defaultTestLoader.loadTestsFromTestCase(BranchPolicyTests))
    failed = len(result.failures) + len(result.errors)
    print(json.dumps({'passed': result.testsRun - failed, 'failed': failed}))
    raise SystemExit(0 if result.wasSuccessful() else 1)
