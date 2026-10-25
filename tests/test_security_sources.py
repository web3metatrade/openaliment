"""Offline tests for redacted focused scanning and the dependency inventory."""
import importlib.util,json,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def module(name):
    spec=importlib.util.spec_from_file_location(name,ROOT/'tools/qa'/f'{name}.py');value=importlib.util.module_from_spec(spec);spec.loader.exec_module(value);return value
scanner=module('security_sources');advisories=module('advisories')
class SecuritySourceTests(unittest.TestCase):
    def test_secret_location_is_reported_without_value(self):
        value='oa_'+''.join(str(i%10) for i in range(64));rows=scanner.findings('app/example.php','<?php\n// '+value)
        self.assertEqual(rows,[{'file':'app/example.php','line':2,'rule':'platform-token'}]);self.assertNotIn(value,json.dumps(rows))
    def test_documented_placeholders_and_plain_hashes_are_not_secrets(self):
        self.assertEqual(scanner.findings('example.md','oa_'+('0'*64)+' '+('a'*64)),[])
    def test_printer_credential_is_redacted_like_other_access_tokens(self):
        secret='oa_print_'+''.join(str(i%10) for i in range(64))
        result=scanner.findings('config/agent.example.json',secret)
        self.assertEqual(result[0]['rule'],'platform-token');self.assertNotIn(secret,json.dumps(result))
    def test_dangerous_php_call_is_distinct_from_database_method(self):
        self.assertEqual(scanner.findings('app/probe.php',"<?php $db->exec('SET time_zone');"),[])
        self.assertEqual(scanner.findings('app/probe.php',"<?php eval($untrusted);")[0]['rule'],'dangerous-php-function')
    def test_inventory_includes_bundled_php_npm_and_python(self):
        packages,manual=advisories.inventory();names={p['package']['name'] for p in packages}
        self.assertTrue({'phpmailer/phpmailer','bootstrap','Pillow','@zxing/browser'}<=names)
        self.assertEqual(next(p['version'] for p in packages if p['package']['name']=='Pillow'),'13.0.0')
        self.assertTrue(any(p['file']=='qrcodegen.js' for p in manual))
if __name__=='__main__':
    result=unittest.TextTestRunner().run(unittest.defaultTestLoader.loadTestsFromTestCase(SecuritySourceTests));failed=len(result.failures)+len(result.errors);print(json.dumps({'passed':result.testsRun-failed,'failed':failed}));raise SystemExit(0 if result.wasSuccessful() else 1)
