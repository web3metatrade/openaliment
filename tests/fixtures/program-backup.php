<?php
declare(strict_types=1);

/** Small, explicitly synthetic state fixture for backup revocation/integrity, not qualification issuance. */
function oa_program_backup_fixture(array $account): array
{
    \App\Core\Migrations::upgradeProgram();
    $uid=$account['id'];$tenant=$account['tenant_id'];$now=\App\Core\Db::now();$until=gmdate('Y-m-d H:i:s',time()+365*86400);
    \App\Core\Db::run('UPDATE users SET is_admin=1,email_verified=1 WHERE id=?',[$uid]);\App\Core\Session::login($uid,true);
    $admin=\App\Core\Db::one('SELECT * FROM users WHERE id=?',[$uid]);
    \App\Domain\ProgramLearning::seed($admin);$course=\App\Domain\ProgramLearning::catalog()['items'][0];
    $enrollment=\App\Domain\ProgramLearning::enroll($admin,['release_id'=>$course['release_id'],'specialization'=>'erp-integration','policy_accepted'=>true]);
    require_once __DIR__.'/program.php';$bank=\App\Domain\ProgramAssessments::bank($admin,null,['bank'=>oa_program_test_bank()]);
    $release=\App\Domain\ProgramAssessments::publish($admin,$bank['id'],['expected_revision'=>$bank['revision'],'course_release_id'=>$course['release_id'],'reviewed'=>true,'rights_confirmed'=>true]);
    $attempt=\App\Core\Db::id();$result=\App\Core\Db::id();$credential=\App\Core\Db::id();
    \App\Core\Db::insert('assessment_attempts',['id'=>$attempt,'user_id'=>$uid,'enrollment_id'=>$enrollment['id'],'release_id'=>$release['id'],'state'=>'graded','started_at'=>$now,'due_at'=>$until,'submitted_at'=>$now]);
    \App\Core\Db::insert('assessment_results',['id'=>$result,'attempt_id'=>$attempt,'version'=>1,'score_basis_points'=>10000,'passed'=>1,'critical_passed'=>1,'details_json'=>'{}','created_at'=>$now]);
    \App\Core\Db::run('UPDATE assessment_attempts SET result_id=? WHERE id=?',[$result,$attempt]);
    \App\Core\Db::insert('learning_credentials',['id'=>$credential,'public_id'=>\App\Core\Db::id(),'user_id'=>$uid,'enrollment_id'=>$enrollment['id'],'result_id'=>$result,'specialization'=>'erp-integration','issued_at'=>$now,'expires_at'=>$until]);
    \App\Core\Db::insert('program_verifications',['id'=>\App\Core\Db::id(),'user_id'=>$uid,'purpose'=>'personal_email','object_id'=>$uid,'value_text'=>'backup@example.invalid','token_hash'=>hash('sha256',random_bytes(32)),'evidence_json'=>'{}','expires_at'=>$until,'created_at'=>$now]);
    \App\Core\Db::insert('program_staff_grants',['id'=>\App\Core\Db::id(),'user_id'=>$uid,'role'=>'community_editor','granted_by'=>$uid,'created_at'=>$now]);
    $image=imagecreatetruecolor(17,17);ob_start();imagepng($image);$png=base64_encode(ob_get_clean());imagedestroy($image);
    $asset=\App\Domain\ProgramMedia::put($admin,['purpose'=>'avatar','content_base64'=>$png,'rights_confirmed'=>true]);
    $contributor=\App\Domain\Contributors::save($admin,null,['name'=>'Backup synthetic contributor','slug'=>'backup-synthetic','summary'=>'Synthetic restoration fixture.','categories'=>['testing'],'asset_id'=>$asset['id'],'consent'=>true]);
    \App\Domain\Contributors::submit($admin,$contributor['id'],['expected_revision'=>$contributor['revision'],'consent'=>true]);
    \App\Domain\Contributors::review($admin,$contributor['id'],['expected_revision'=>$contributor['revision'],'action'=>'approve','reason'=>'Synthetic backup fixture.']);
    $observation=\App\Domain\OrganizationRegistry::observe('900001006');\App\Domain\OrganizationRegistry::attachObservation($tenant,$uid,$observation['id']);
    $legal=\App\Core\Db::one('SELECT current_version_id FROM organization_legal_profiles WHERE tenant_id=?',[$tenant])['current_version_id'];
    $application=\App\Core\Db::id();$profile=\App\Core\Db::id();$version=\App\Core\Db::id();$contact=\App\Core\Db::id();
    $snapshot=['description'=>'Synthetic backup fixture.','services'=>['ERP'],'regions'=>['Test'],'languages'=>['ro'],'website'=>null,'asset_id'=>null,'legal_name'=>'Companie sintetică program 6','cui'=>'900001006','country'=>'RO','contact_ids'=>[$contact],'application_id'=>$application];
    \App\Core\Db::insert('partner_applications',['id'=>$application,'user_id'=>$uid,'tenant_id'=>$tenant,'legal_version_id'=>$legal,'draft_json'=>\App\Core\Db::json($snapshot),'state'=>'approved','identity_verified'=>1,'reviewed_by'=>$uid,'created_at'=>$now]);
    \App\Core\Db::insert('partner_profiles',['id'=>$profile,'public_name'=>$snapshot['legal_name'],'tenant_id'=>$tenant,'cui'=>'900001006','slug'=>'backup-partner','state'=>'active','observation_id'=>$observation['id'],'fiscal_checked_at'=>$now,'fiscal_state'=>'active','created_at'=>$now]);
    \App\Core\Db::insert('partner_profile_versions',['id'=>$version,'profile_id'=>$profile,'revision'=>1,'legal_version_id'=>$legal,'snapshot_json'=>\App\Core\Db::json($snapshot),'state'=>'approved','reviewed_by'=>$uid,'created_at'=>$now]);
    \App\Core\Db::run('UPDATE partner_profiles SET current_version_id=? WHERE id=?',[$version,$profile]);\App\Core\Db::run('UPDATE partner_applications SET profile_id=? WHERE id=?',[$profile,$application]);
    \App\Core\Db::insert('partner_contacts',['id'=>$contact,'application_id'=>$application,'kind'=>'email','value_text'=>'backup-company@example.invalid','verified_at'=>$now,'expires_at'=>$until,'public_consent'=>1,'created_at'=>$now]);
    \App\Domain\ProgramPartners::affiliate($uid,$profile,$uid,'manager');\App\Domain\ProgramPartners::reconcileFor($uid);
    return ['asset'=>$asset,'contributor_id'=>$contributor['id'],'profile_id'=>$profile,'credential_id'=>$credential];
}
