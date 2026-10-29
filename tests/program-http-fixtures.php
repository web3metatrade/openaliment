<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/program.php';
use App\Core\{Config, Db, Migrations, Passwords};
use App\Domain\{Accounts, ProgramLearning, ProgramAssessments};
Migrations::upgradeProgram();
Db::run('DELETE FROM rate_limits');
foreach (glob(Config::get('storage') . '/cache/api-rates/*.json') ?: [] as $path) {
    if (!is_link($path) && preg_match('/\A[a-f0-9]{2}\.json\z/D', basename($path))) { unlink($path); }
}
if (in_array('--reset-rates', $argv, true)) { echo "Reset disposable program counters.\n"; exit; }
$users = [];
foreach (['admin', 'delegate', 'outsider'] as $kind) {
    $id = Db::id(); $password = bin2hex(random_bytes(18)); $email = 'program-' . $kind . '-' . $id . '@example.invalid';
    Db::insert('users', ['id' => $id, 'email' => $email, 'display_name' => 'Persoană sintetică ' . $kind,
        'password_hash' => Passwords::hash($password), 'is_admin' => $kind === 'admin' ? 1 : 0, 'email_verified' => 1, 'created_at' => Db::now()]);
    $users[$kind] = compact('id', 'email', 'password');
}
$admin = Db::one('SELECT * FROM users WHERE id=?', [$users['admin']['id']]);
ProgramLearning::seed($admin);
if (in_array('--browser', $argv, true)) {
    $course = Db::one("SELECT * FROM learning_courses WHERE slug='integrare-gs1-digital-link'");
    $bank = ProgramAssessments::bank($admin, null, ['bank' => oa_program_test_bank()]);
    ProgramAssessments::publish($admin, $bank['id'], ['expected_revision' => $bank['revision'], 'course_release_id' => $course['current_release_id'], 'reviewed' => true, 'rights_confirmed' => true]);
    if (!Db::one("SELECT id FROM learning_courses WHERE slug='synthetic-html-exam'")) {
        $htmlManifest=json_decode(file_get_contents(ROOT.'/contracts/program/learning-content.json'),true);
        $htmlManifest['title']='Curs sintetic pentru fallback HTML';$htmlManifest['version']='html-1';$htmlManifest['modules']=array_slice($htmlManifest['modules'],0,1);$htmlManifest['modules'][0]['lessons']=array_slice($htmlManifest['modules'][0]['lessons'],0,1);
        $htmlCourse=ProgramLearning::save($admin,null,['slug'=>'synthetic-html-exam','manifest'=>$htmlManifest]);$htmlCourse=ProgramLearning::publish($admin,$htmlCourse['id'],['expected_revision'=>$htmlCourse['revision'],'rights_confirmed'=>true]);
        $htmlBank=ProgramAssessments::bank($admin,null,['bank'=>oa_program_test_bank()]);ProgramAssessments::publish($admin,$htmlBank['id'],['expected_revision'=>$htmlBank['revision'],'course_release_id'=>$htmlCourse['release_id'],'reviewed'=>true,'rights_confirmed'=>true]);
    }
    echo "Prepared synthetic bank for the full browser course.\n"; exit;
}
$epass = bin2hex(random_bytes(18));
$erp = Accounts::register(['email' => 'program-erp-' . Db::id() . '@example.invalid', 'display_name' => 'ERP sintetic', 'organization' => 'ERP QA sintetic', 'password' => $epass], true);
$erpKey = Accounts::token(['tenant' => $erp['tenant_id'], 'user' => $erp['id'], 'role' => 'owner', 'token' => null], ['name' => 'Test de separare program / ERP', 'days' => 1, 'scopes' => ['read']]);
$image = imagecreatetruecolor(20, 20); ob_start(); imagepng($image); $png = base64_encode(ob_get_clean()); imagedestroy($image);
$manifest = json_decode(file_get_contents(ROOT . '/contracts/program/learning-content.json'), true);
$manifest['modules'] = array_slice($manifest['modules'], 0, 1); $manifest['modules'][0]['lessons'] = array_slice($manifest['modules'][0]['lessons'], 0, 1);
$manifest['title'] = 'Curs sintetic HTTP'; $manifest['version'] = 'http-1';
file_put_contents(__DIR__ . '/runtime/program-http-fixtures.json', Db::json(['users' => $users, 'erp_key' => $erpKey['token'], 'bank' => oa_program_test_bank(), 'labs' => oa_program_test_labs(), 'png' => $png, 'manifest' => $manifest]));
echo "Prepared private program HTTP fixtures.\n";
