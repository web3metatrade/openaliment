<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/program.php';
ob_start();
use App\Core\{Auth, Config, Db, Migrations, Problem, Session};
use App\Domain\{Contributors, ProgramAccess, ProgramAdministration, ProgramApplications, ProgramAssessments, ProgramJobs, ProgramLabs, ProgramLearning, ProgramMedia, ProgramPartners, ProgramQueries, ProgramService, ProgramVerifications};
$passed = 0;
function pc(string $name, bool $ok): void { global $passed; if (!$ok) { throw new RuntimeException('FAIL ' . $name); } $passed++; }
function pr(string $name, callable $run, int $status): void { try { $run(); } catch (Problem $e) { pc($name, $e->status === $status); return; } throw new RuntimeException('Missing rejection: ' . $name); }
function pm(array $user, callable $run): array { return Db::atomic(static function () use ($user, $run): array { Db::one('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]); return $run(); }); }
function programToken(string $recipient): string {
    $files = glob(Config::get('storage') . '/private/mail-test/*.json') ?: [];
    usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    foreach ($files as $file) { $mail = json_decode(file_get_contents($file), true); if ($mail['recipient'] === $recipient && preg_match('/#token=([a-f0-9]{64})/', $mail['text'], $m)) { return $m[1]; } }
    throw new RuntimeException('Test proof missing');
}
$tradeCount = (int) Db::one('SELECT COUNT(*) AS n FROM trade_items')['n'];
Migrations::upgradeProgram(); Migrations::upgradeProgram(); pc('Program migrations are additive and repeatable', Migrations::program());
pc('Standalone program activation installs account API and security prerequisites', Migrations::api() && Migrations::security() && Migrations::readiness());
$adminId = Db::id();
Db::insert('users', ['id' => $adminId, 'email' => 'program-reviewer-' . $adminId . '@example.invalid', 'display_name' => 'Evaluator sintetic independent', 'password_hash' => \App\Core\Passwords::hash(bin2hex(random_bytes(18))), 'is_admin' => 1, 'email_verified' => 1, 'created_at' => Db::now()]);
$admin = Db::one('SELECT * FROM users WHERE id=?', [$adminId]);
Session::login($admin['id'], true);
ProgramLearning::seed($admin); ProgramLearning::seed($admin);
$course = ProgramLearning::catalog()['items'][0];
pc('Initial course seeded once', count(ProgramLearning::catalog()['items']) === 1);
$tag = Db::id();
$registration = ProgramVerifications::register(['email' => 'learner-' . $tag . '@example.invalid', 'display_name' => 'Cursant sintetic', 'password' => bin2hex(random_bytes(18)), 'policy_accepted' => true]);
$student = Db::one('SELECT * FROM users WHERE id=?', [$registration['id']]);
pc('Learner account has no fabricated organization', !Db::one('SELECT tenant_id FROM memberships WHERE user_id=?', [$student['id']]));
pc('Personal home is learning', ProgramAccess::home($student) === '/invatare/cont');
$mail = ProgramVerifications::request($student, ['purpose' => 'personal_email']);
$token = programToken($student['email']);
$preview = ProgramVerifications::preview(['token' => $token]);
pc('Preview does not confirm proof', !Db::one('SELECT email_verified FROM users WHERE id=?', [$student['id']])['email_verified']);
ProgramVerifications::confirm($student, ['token' => $token, 'accepted' => true]);
pr('Proof cannot be consumed twice', fn() => ProgramVerifications::confirm($student, ['token' => $token, 'accepted' => true]), 410);
$student = Db::one('SELECT * FROM users WHERE id=?', [$student['id']]);
pc('Personal email verified independently', (bool) $student['email_verified']);
$otherId = Db::id();
Db::insert('users', ['id' => $otherId, 'email' => 'program-other-' . $otherId . '@example.invalid', 'display_name' => 'Alt cursant sintetic', 'password_hash' => \App\Core\Passwords::hash(bin2hex(random_bytes(18))), 'created_at' => Db::now()]);
$other = Db::one('SELECT * FROM users WHERE id=?', [$otherId]);
$profileData = ['name' => 'Contribuitor sintetic', 'slug' => 'contribuitor-' . $tag, 'summary' => 'Testarea locală a consimțământului.', 'categories' => ['testing'], 'links' => [], 'consent' => true];
$contributor = pm($student, fn() => Contributors::save($student, null, $profileData));
pr('Draft contributor is not public', fn() => Contributors::profile($contributor['id']), 404);
$contributor = pm($student, fn() => Contributors::submit($student, $contributor['id'], ['expected_revision' => $contributor['revision'], 'consent' => true]));
$contributor = pm($admin, fn() => Contributors::review($admin, $contributor['id'], ['expected_revision' => $contributor['revision'], 'action' => 'approve', 'reason' => 'Verificare sintetică locală.']));
pc('Consented approved contributor is public', Contributors::profile($contributor['id'])['profile']['name'] === $profileData['name']);
pr('Another learner cannot edit contributor', fn() => Contributors::save($other, $contributor['id'], $profileData + ['expected_revision' => $contributor['revision']]), 404);
pm($student, fn() => Contributors::withdraw($student, $contributor['id']));
pr('Consent withdrawal immediately hides profile', fn() => Contributors::profile($contributor['id']), 404);
$enrollment = pm($student, fn() => ProgramLearning::enroll($student, ['release_id' => $course['release_id'], 'specialization' => 'erp-integration', 'policy_accepted' => true]));
pr('Another learner cannot read progress', fn() => ProgramLearning::enrollment($other, $enrollment['id']), 404);
pr('Course cannot be skipped before exam', fn() => ProgramAssessments::start($student, ['enrollment_id' => $enrollment['id']]), 409);
foreach ($enrollment['lessons'] as $lesson) {
    $result = pm($student, fn() => ProgramLearning::progress($student, $enrollment['id'], $lesson['id'], ['expected_revision' => 0, 'answer' => $lesson['practice']['answer']], true));
    pc('Practice records completion ' . $lesson['id'], $result['completed']);
    pr('Stale progress cannot overwrite ' . $lesson['id'], fn() => ProgramLearning::progress($student, $enrollment['id'], $lesson['id'], ['expected_revision' => 0, 'completed' => false]), 409);
}
$bank = pm($admin, fn() => ProgramAssessments::bank($admin, null, ['bank' => oa_program_test_bank()]));
$bankRelease = pm($admin, fn() => ProgramAssessments::publish($admin, $bank['id'], ['expected_revision' => $bank['revision'], 'course_release_id' => $course['release_id'], 'reviewed' => true, 'rights_confirmed' => true]));
$attempt = pm($student, fn() => ProgramAssessments::start($student, ['enrollment_id' => $enrollment['id']]));
pc('Candidate receives 30 questions without marking keys', count($attempt['items']) === 30 && !str_contains(Db::json($attempt), 'explanation') && !str_contains(Db::json($attempt), 'SYNTHETIC-identity-'));
$resume = pm($student, fn() => ProgramAssessments::start($student, ['enrollment_id' => $enrollment['id']]));
pc('Resume preserves attempt and server deadline', $resume['id'] === $attempt['id'] && $resume['due_at'] === $attempt['due_at']);
pr('Another learner cannot see attempt', fn() => ProgramAssessments::attempt($other, $attempt['id']), 404);
$bankByKey = array_column(oa_program_test_bank()['questions'], null, 'key');
$items = Db::all('SELECT id,question_key FROM assessment_items WHERE attempt_id=?', [$attempt['id']]);
foreach ($items as $item) {
    pm($student, fn() => ProgramAssessments::answer($student, $attempt['id'], $item['id'], ['expected_revision' => 0, 'answer' => $bankByKey[$item['question_key']]['answer']]));
}
$result = pm($student, fn() => ProgramAssessments::submit($student, $attempt['id']));
pc('Exact grading promotes a perfect attempt', $result['passed'] && $result['score_basis_points'] === 10000);
pc('Theory alone grants no credential', ProgramQueries::credentials($student)['items'] === []);
pr('Another learner cannot see result', fn() => ProgramAssessments::result($other, $result['id']), 404);
pr('Answer cannot change after submission', fn() => ProgramAssessments::answer($student, $attempt['id'], $items[0]['id'], ['expected_revision' => 1, 'answer' => 'wrong']), 409);
foreach (['P01', 'P02', 'P03'] as $scenario) {
    $lab = pm($student, fn() => ProgramLabs::create($student, ['enrollment_id' => $enrollment['id'], 'scenario' => $scenario]));
    $lab = pm($student, fn() => ProgramLabs::save($student, $lab['id'], ['expected_revision' => $lab['revision'], 'payload' => oa_program_test_labs()[$scenario]]));
    $lab = pm($student, fn() => ProgramLabs::submit($student, $lab['id']));
    pc('Automatic lab checks await human review ' . $scenario, $lab['state'] === 'submitted');
    pr('No reviewer role from course participation ' . $scenario, fn() => ProgramLabs::review($student, $lab['id'], []), 403);
    $review = ['identity_points' => 25, 'history_points' => 25, 'security_points' => 25, 'explanation_points' => 25, 'mandatory_checks' => true, 'reason' => 'Evaluare sintetică independentă, exclusiv locală.'];
    $lab = pm($admin, fn() => ProgramLabs::review($admin, $lab['id'], $review));
    pc('Independent rubric validates lab ' . $scenario, $lab['state'] === 'validated');
}
$credentials = ProgramQueries::credentials($student)['items']; $credential = $credentials[0];
pc('Credential issued after theory and three labs', count($credentials) === 1 && $credential['state'] === 'active');
pc('Public credential hides learner by default', !isset(ProgramQueries::credential($credential['public_id'])['name']));
pr('No partner status from personal qualification alone', fn() => ProgramPartners::profile(Db::id()), 404);
$badCheck = ProgramPartners::check($student, ['cui' => '1234']);
pc('Unknown CUI remains explicitly not found', $badCheck['state'] === 'not_found');
$check = ProgramPartners::check($student, ['cui' => 'RO900000001']);
pr('Corrected CUI invalidates old check', fn() => ProgramPartners::checkResult($student, $badCheck['id']), 409);
$app = pm($student, fn() => ProgramPartners::create($student, ['company_check_id' => $check['id'], 'cui' => $check['cui'], 'company_confirmed' => true]));
pc('ANAF saved with immutable fiscal source', $app['company']['cui'] === '900000001' && !$app['identity_verified']);
$app = pm($student, fn() => ProgramPartners::save($student, $app['id'], ['expected_revision' => $app['revision'], 'description' => 'Servicii sintetice de integrare, numai pentru QA local.', 'services' => ['ERP'], 'regions' => ['Test local'], 'languages' => ['ro']]));
$contactEmail = 'company-' . $tag . '@example.invalid';
$app = pm($student, fn() => ProgramPartners::contact($student, $app['id'], ['expected_revision' => $app['revision'], 'kind' => 'email', 'value' => $contactEmail, 'public_consent' => true]));
ProgramVerifications::request($student, ['purpose' => 'partner_contact', 'object_id' => $app['contacts'][0]['id']]);
ProgramVerifications::confirm($student, ['token' => programToken($contactEmail), 'accepted' => true]);
$app = pm($student, fn() => ProgramPartners::submit($student, $app['id'], ['expected_revision' => $app['revision'], 'policy_accepted' => true]));
pr('Applicant cannot verify own company', fn() => ProgramPartners::review($student, $app['id'], ['expected_revision' => $app['revision'], 'action' => 'approve', 'reason' => 'Self review', 'representation_verified' => true]), 403);
$app = pm($admin, fn() => ProgramPartners::review($admin, $app['id'], ['expected_revision' => $app['revision'], 'action' => 'approve', 'reason' => 'Mandat și prezentare sintetice, verificate în QA local.', 'representation_verified' => true]));
$publicPartner = ProgramPartners::profile($app['profile_id']);
pc('Eligible company automatically appears with active authorization', $publicPartner['cui'] === '900000001' && $publicPartner['authorizations'][0]['state'] === 'active');
pc('Company registry never claims GS1 accreditation', $publicPartner['authorizations'][0]['gs1_accreditation'] === false);
pc('Public partner excludes learner results and private evidence', !str_contains(Db::json($publicPartner), 'score_basis_points') && !str_contains(Db::json($publicPartner), 'password'));
$saved = $publicPartner;
$app = pm($student, fn() => ProgramPartners::contact($student, $app['id'], ['expected_revision' => $app['revision'], 'kind' => 'email', 'value' => 'changed-' . $tag . '@example.invalid', 'public_consent' => true]));
pr('Contact replacement immediately hides authorization without cron', fn() => ProgramPartners::profile($app['profile_id']), 404);
pc('Existing authorization verification reflects missing prerequisite', ProgramQueries::authorization($saved['authorizations'][0]['id'])['state'] === 'unavailable');
pm($admin, fn() => ProgramAdministration::credential($admin, $credential['id'], ['action' => 'revoke', 'reason' => 'Revocare sintetică QA.']));
pc('Credential revocation immediately affects public verification', ProgramQueries::credential($credential['public_id'])['state'] === 'revoked');
pc('Learner export never includes private exam bank', !str_contains(Db::json(ProgramQueries::export($student)), 'SYNTHETIC-identity-'));
$import = ProgramQueries::importPreview($student, ['package' => ProgramQueries::export($student)]);
pc('Import cannot mint credentials', $import['credentials_accepted'] === 0 && !$import['completion_accepted']);
$company2 = ProgramPartners::check($student, ['cui' => '900000002']);
$newApp = pm($student, fn() => ProgramPartners::create($student, ['company_check_id' => $company2['id'], 'cui' => $company2['cui'], 'company_confirmed' => true, 'previous_profile_id' => $app['profile_id']]));
pc('Company change creates a separate fiscal identity', $newApp['company']['cui'] === '900000002' && $newApp['tenant_id'] !== $app['tenant_id']);
pc('Company change preserves old legal identity and personal learning', ProgramPartners::application($student, $app['id'])['company']['cui'] === '900000001' && ProgramLearning::enrollment($student, $enrollment['id'])['id'] === $enrollment['id']);
pc('Company change gives no new representation or authorization', !$newApp['identity_verified'] && $newApp['profile_id'] === null);
pc('Program has not rewritten producer records', $tradeCount === (int) Db::one('SELECT COUNT(*) AS n FROM trade_items')['n']);
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/api/v2/program/me/enrollments'; $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'program-test-' . Db::id();
$calls = 0; $once = static function () use (&$calls): array { $calls++; return ['id' => '1' . str_repeat('0', 31)]; };
$receipt = ProgramAccess::mutation($student, ['value' => 1], $once); $replay = ProgramAccess::mutation($student, ['value' => 1], $once);
pc('Program retries execute a mutation exactly once', $calls === 1 && $receipt === $replay);
pr('Idempotency key with different payload conflicts', fn() => ProgramAccess::mutation($student, ['value' => 2], $once), 409);
$image = imagecreatetruecolor(20, 20); ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
$asset = ProgramMedia::put($student, ['purpose' => 'avatar', 'rights_confirmed' => true, 'content_base64' => base64_encode($png)]);
pc('Uploaded raster is reencoded with stable content hash', $asset['mime'] === 'image/png' && is_file(ProgramMedia::read($student, $asset['id'])['path']));
pr('Unapproved private image is not public', fn() => ProgramMedia::read(null, $asset['id']), 404);
pr('Different user cannot read private media', fn() => ProgramMedia::read($other, $asset['id']), 404);
pr('SVG upload rejected', fn() => ProgramMedia::put($student, ['purpose' => 'avatar', 'rights_confirmed' => true, 'content_base64' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>')]), 422);
file_put_contents(__DIR__ . '/runtime/program-domain-fixtures.json', Db::json(['student_id' => $student['id'], 'admin_id' => $admin['id'], 'course_id' => $course['id'], 'course_release_id' => $course['release_id'], 'bank_id' => $bank['id'], 'bank_release_id' => $bankRelease['id'], 'enrollment_id' => $enrollment['id'], 'attempt_id' => $attempt['id'], 'result_id' => $result['id'], 'credential_id' => $credential['id'], 'application_id' => $app['id'], 'new_application_id' => $newApp['id'], 'profile_id' => $app['profile_id'], 'contributor_id' => $contributor['id'], 'asset_id' => $asset['id']]));
ob_end_clean(); echo Db::json(['passed' => $passed, 'failed' => 0]) . PHP_EOL;
