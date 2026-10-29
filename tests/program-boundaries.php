<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/program.php';
use App\Core\{Db, Problem};
use App\Domain\{ProgramJobs, ProgramLearning, ProgramQueries, ProgramAssessments, ProgramAccess, ProgramPartners};
use App\Domain\{ProgramLabs, ProgramMedia};
$f=json_decode(file_get_contents(__DIR__.'/runtime/program-domain-fixtures.json'),true);$passed=0;
function pb(string $name,bool $yes): void {global $passed;if(!$yes)throw new RuntimeException($name);$passed++;}
function pbr(string $name,callable $run,int $status): void {try{$run();}catch(Problem $e){pb($name,$e->status===$status);return;}throw new RuntimeException('Expected rejection: '.$name);}
$student=Db::one('SELECT * FROM users WHERE id=?',[$f['student_id']]);$admin=Db::one('SELECT * FROM users WHERE id=?',[$f['admin_id']]);
App\Core\Session::login($admin['id'], true);
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/program/local-quota';
    $beforeBudget=oa_test_config();App\Core\Config::load(array_replace($beforeBudget,['security_limits'=>['program_write_user_kib_day'=>1]]));$rejectedEvent=Db::id();
    try {
        $_SERVER['HTTP_IDEMPOTENCY_KEY']='quota-'.Db::id();
        pbr('Oversized receipt exceeds the write storage budget',fn()=>ProgramAccess::mutation($student,['value'=>1],static function()use($student,$rejectedEvent):array{ProgramAccess::event($student,'quota',$rejectedEvent,'test');return ['data'=>str_repeat('x',4096)];}),429);
        pb('Storage budget rejection rolls the mutation back',!Db::one('SELECT id FROM program_events WHERE object_id=?',[$rejectedEvent]));
    } finally {App\Core\Config::load($beforeBudget);}
Db::pdo()->beginTransaction();
try{
    $export=ProgramQueries::export($student);$export['enrollments'][0]['release_id']=str_repeat('f',32);
    pb('Cross-installation import matches immutable content fingerprint',ProgramQueries::importPositions($student,['package'=>$export])['saved_positions']>0);
    $changed=$export;$changed['enrollments'][0]['course_ref']['sha256']=str_repeat('0',64);
    pb('Different course content cannot receive imported reading positions',ProgramQueries::importPositions($student,['package'=>$changed])['saved_positions']===0);
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/program/local-idempotency';$_SERVER['HTTP_IDEMPOTENCY_KEY']='boundary-'.Db::id();$calls=0;
    $run=static function()use(&$calls):array{$calls++;return ['state'=>'saved'];};
    ProgramAccess::mutation($student,['input'=>['value'=>1,'other'=>2,'current_password'=>'synthetic']],$run);ProgramAccess::mutation($student,['input'=>['other'=>2,'value'=>1]],$run);
    pb('Web idempotency ignores key ordering and reconfirmation password without retaining it',$calls===1);
    $g=ProgramAccess::grant($admin,['user_id'=>$student['id'],'role'=>'community_editor']);$g2=ProgramAccess::grant($admin,['user_id'=>$student['id'],'role'=>'community_editor']);
    pb('Repeated delegation does not duplicate an active role',$g['id']===$g2['id']);
    $originalConfig=oa_test_config();App\Core\Config::load(array_replace($originalConfig,['url'=>'https://assets.example.invalid']));
    try {
        $image=imagecreatetruecolor(31,31);ob_start();imagepng($image);$png=base64_encode(ob_get_clean());imagedestroy($image);
        $lessonAsset=ProgramMedia::put($admin,['purpose'=>'lesson','content_base64'=>$png,'rights_confirmed'=>true]);
        pbr('Lesson images are private before publication',fn()=>ProgramMedia::read(null,$lessonAsset['id']),404);
        $manifest=ProgramLearning::release($f['course_release_id'])['manifest'];$manifest['version']='media-'.substr(Db::id(),0,8);
        $manifest['modules'][0]['lessons'][0]['resources'][]=['title'=>'Synthetic lesson image','url'=>'https://assets.example.invalid'.$lessonAsset['url']];
        $draft=ProgramLearning::save($admin,null,['slug'=>'media-'.Db::id(),'manifest'=>$manifest]);ProgramLearning::publish($admin,$draft['id'],['expected_revision'=>$draft['revision'],'rights_confirmed'=>true]);
        pb('Published lesson resource can be read with verified bytes',is_file(ProgramMedia::read(null,$lessonAsset['id'])['path']));
        ProgramMedia::withdraw($admin,$lessonAsset['id']);pbr('Withdrawn lesson resource stops public access immediately',fn()=>ProgramMedia::read(null,$lessonAsset['id']),404);
    } finally { App\Core\Config::load($originalConfig); }
    foreach (['printing-integration'=>'P04','platform-operations'=>'P05','product-data'=>'P06'] as $track=>$specific) {
        $e=ProgramLearning::enroll($student,['release_id'=>$f['course_release_id'],'specialization'=>$track,'policy_accepted'=>true]);
        foreach ($e['lessons'] as $lesson) { ProgramLearning::progress($student,$e['id'],$lesson['id'],['expected_revision'=>0,'answer'=>$lesson['practice']['answer']],true); }
        pb('Same-edition theory is reused for '.$track,ProgramLearning::enrollment($student,$e['id'])['theory_result_id']===$f['result_id']);
        foreach (['P01','P02',$specific] as $scenario) {
            $lab=ProgramLabs::create($student,['enrollment_id'=>$e['id'],'scenario'=>$scenario]);$input=['expected_revision'=>$lab['revision'],'payload'=>oa_program_test_labs()[$scenario]];
            if ($scenario==='P04') {
                $image=imagecreatetruecolor(21,21);ob_start();imagepng($image);$png=base64_encode(ob_get_clean());imagedestroy($image);
                $asset=ProgramMedia::put($student,['purpose'=>'lab_evidence','content_base64'=>$png,'rights_confirmed'=>true]);$input['evidence_asset_id']=$asset['id'];
            }
            ProgramLabs::save($student,$lab['id'],$input);ProgramLabs::submit($student,$lab['id']);
            $review=['identity_points'=>25,'history_points'=>25,'security_points'=>25,'explanation_points'=>25,'mandatory_checks'=>true,'reason'=>'Simulare locală a evaluării independente; nu dovedește tipărire sau compatibilitate POS reală.'];
            if ($scenario==='P04') {
                $rejected=ProgramLabs::review($admin,$lab['id'],$review);
                pb('Printing cannot pass without explicit physical review',$rejected['state']==='needs_changes' && !$rejected['review']['passed']);
                $input['expected_revision']=$rejected['revision'];ProgramLabs::save($student,$lab['id'],$input);ProgramLabs::submit($student,$lab['id']);$review['physical_proof_reviewed']=true;
            }
            pb('Independent practical review '.$track.' '.$scenario,ProgramLabs::review($admin,$lab['id'],$review)['state']==='validated');
            if ($scenario==='P04') {
                $printingLab=$lab['id'];$history=Db::all("SELECT details_json FROM program_events WHERE object_kind='lab' AND object_id=? AND action='reviewed'",[$lab['id']]);
                pb('Both printing decisions retain their rubric and reason',count($history)===2 && count(array_filter($history,fn($r)=>isset(json_decode($r['details_json'],true)['points'])))===2);
            }
        }
        pb('Complete specialization issues its own credential '.$track,(bool)Db::one('SELECT id FROM learning_credentials WHERE user_id=? AND enrollment_id=? AND specialization=?',[$student['id'],$e['id'],$track]));
    }
    Db::run("UPDATE assessment_attempts SET submitted_at='2020-01-01 00:00:00' WHERE id=?",[$f['attempt_id']]);
    Db::run("UPDATE assessment_results SET appeal_json=? WHERE id=?",[Db::json(['state'=>'pending','reason'=>'Protected appeal','at'=>Db::now()]),$f['result_id']]);
    Db::run("UPDATE learning_credentials SET expires_at='2020-01-01 00:00:00' WHERE result_id=?",[$f['result_id']]);
    ProgramJobs::minimizeEvidence();
    pb('Pending appeal protects old answers',Db::one('SELECT response_json FROM assessment_items WHERE attempt_id=? LIMIT 1',[$f['attempt_id']])['response_json']!==null);
    Db::run('UPDATE assessment_results SET appeal_json=NULL WHERE id=?',[$f['result_id']]);ProgramJobs::minimizeEvidence();
    pb('Expired evidence is minimized while decisions survive',Db::one('SELECT response_json FROM assessment_items WHERE attempt_id=? LIMIT 1',[$f['attempt_id']])['response_json']===null && ProgramAssessments::result($student,$f['result_id'])['passed']);
    pbr('Minimized evidence cannot be silently regraded',fn()=>ProgramAssessments::decideAppeal($admin,$f['result_id'],['reason'=>'Late review']),409);
    Db::run("UPDATE assessment_lab_runs SET created_at='2020-01-01 00:00:00',updated_at='2020-01-01 00:00:00' WHERE id=?",[$printingLab]);ProgramJobs::minimizeEvidence();
    pb('Old lab evidence is minimized even without its own theory attempt',Db::one('SELECT payload_json FROM assessment_lab_runs WHERE id=?',[$printingLab])['payload_json']==='{}');
    pb('Minimization also removes the submitted private snapshot',!array_filter(Db::all("SELECT details_json FROM program_events WHERE object_kind='lab' AND object_id=? AND action='submitted'",[$printingLab]),fn($r)=>isset(json_decode($r['details_json'],true)['payload'])));
    $aff=Db::one("SELECT id FROM partner_affiliations WHERE profile_id=? AND user_id=? AND state='active'",[$f['profile_id'],$student['id']]);ProgramPartners::endAffiliation($student,$aff['id']);
    pbr('Former representative cannot alter the company',fn()=>ProgramPartners::authorizeChange($student,$f['application_id']),403);
}finally{Db::pdo()->rollBack();}
echo Db::json(['passed'=>$passed,'failed'=>0]).PHP_EOL;
