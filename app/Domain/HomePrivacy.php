<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Mailer,Migrations,PrivateKey,Problem};

final class HomePrivacy {
    public static function request(array $p,string $action,array $payload=[]): array {
        AccountChallenges::ready();if(isset($p['grant_id']))throw new Problem(403,'account_session','Confirmă această operațiune în contul personal.');HomeSync::member($p);
        if(!in_array($action,['erase','transfer','leave'],true))throw new Problem(422,'account_action','Operațiune incorectă.');
        if(!Mailer::ready())throw new Problem(503,'email_unavailable','Confirmarea prin email nu este disponibilă momentan.');AccountChallenges::limit('home-action',$p['account_id']);
        $canonical=self::validateAction($p,$action,$payload);$canonical['action']=$action;
        $token=AccountChallenges::issue('home_action',null,$p['account_id'],$canonical,hash('sha256',$p['device_id']));
        $labels=['erase'=>'Ștergerea contului','transfer'=>'Transferul familiei','leave'=>'Părăsirea familiei'];$title=$labels[$action];
        Mailer::securityMessage($p['email'],$title.' · OpenAliment',$title."\n\nConfirmă operațiunea în browserul în care ai cerut-o. Legătura expiră în 15 minute. Deschiderea ei nu execută operațiunea.\n\n".rtrim((string)Config::get('url'),'/').'/acasa/#confirma='.$token."\n\nDacă nu ai cerut această operațiune, ignoră mesajul.\n");
        return ['message'=>'Am trimis o legătură de confirmare pe email.'];
    }
    private static function validateAction(array $p,string $action,array $payload): array {
        if($action==='erase'){
            $shared=Db::one('SELECT h.id FROM home_households h WHERE h.owner_account_id=? AND EXISTS (SELECT 1 FROM home_members m WHERE m.household_id=h.id AND m.account_id<>?) LIMIT 1',[$p['account_id'],$p['account_id']]);
            if($shared)throw new Problem(409,'home_transfer_required','Familia are și alți membri. Transferă mai întâi administrarea ei către un membru.');return [];
        }
        $household=HomeInventory::id($payload['household_id']??$p['household_id']);$member=Db::one('SELECT m.role,h.owner_account_id FROM home_members m JOIN home_households h ON h.id=m.household_id WHERE m.household_id=? AND m.account_id=?',[$household,$p['account_id']]);
        if(!$member)throw new Problem(403,'home_membership','Nu ai acces la această familie.');
        if($action==='leave'){if($member['role']==='owner')throw new Problem(409,'home_transfer_required','Transferă administrarea înainte de a părăsi familia.');return ['household_id'=>$household];}
        $target=HomeInventory::id($payload['account_id']??'');if($member['role']!=='owner'||$target===$p['account_id']||!Db::one("SELECT account_id FROM home_members WHERE household_id=? AND account_id=? AND role='editor'",[$household,$target]))throw new Problem(403,'home_transfer','Alege un membru adult cu drept de modificare.');
        return ['household_id'=>$household,'account_id'=>$target];
    }
    public static function preview(array $p,string $token): array {
        $row=self::challenge($p,$token);$payload=$row['payload'];$action=$payload['action'];self::validateAction($p,$action,$payload);
        $descriptions=['erase'=>'Contul, adresa de email și spațiile în care ești singurul membru vor fi eliminate. Accesurile dispozitivelor și aplicațiilor vor fi retrase. Datele familiilor administrate de alți membri rămân ale familiei. Copiile de pe alte dispozitive pot rămâne acolo.',
            'transfer'=>'Membrul ales va administra această familie. Tu rămâi membru și îți păstrezi accesul la alimente.',
            'leave'=>'Accesul tău la această familie va fi retras. Alimentele rămân disponibile celorlalți membri.'];
        return ['action'=>$action,'description'=>$descriptions[$action],'payload'=>$payload];
    }
    private static function challenge(array $p,string $token,bool $lock=false): array {
        if(isset($p['grant_id']))throw new Problem(403,'account_session','Folosește contul personal.');$row=AccountChallenges::read($token,'home_action',$lock);
        if($row['home_account_id']!==$p['account_id']||!hash_equals((string)$row['session_hash'],hash('sha256',$p['device_id'])))throw new Problem(403,'account_confirmation','Deschide confirmarea în browserul în care ai cerut operațiunea.');return $row;
    }
    public static function confirm(array $p,string $token): array {
        $result=Db::atomic(function()use($p,$token){$row=self::challenge($p,$token,true);$action=$row['payload']['action'];$payload=self::validateAction($p,$action,$row['payload']);AccountChallenges::consume($row);
            Db::one('SELECT id FROM home_accounts WHERE id=? FOR UPDATE',[$p['account_id']]);
            if($action==='erase')return self::erase($p);
            $household=$payload['household_id'];Db::one('SELECT id FROM home_households WHERE id=? FOR UPDATE',[$household]);self::validateAction($p,$action,$row['payload']);
            if($action==='transfer'){
                Db::run("UPDATE home_members SET role='editor' WHERE household_id=? AND account_id=?",[$household,$p['account_id']]);Db::run("UPDATE home_members SET role='owner' WHERE household_id=? AND account_id=?",[$household,$payload['account_id']]);Db::run('UPDATE home_households SET owner_account_id=? WHERE id=?',[$payload['account_id'],$household]);HomeAudit::record(array_replace($p,['household_id'=>$household]),'household.transferred',$payload['account_id']);
            }else{Db::run('DELETE FROM home_members WHERE household_id=? AND account_id=?',[$household,$p['account_id']]);self::revokeAccess($p['account_id'],$household);}
            return ['completed'=>true,'action'=>$action];
        });if(($result['action']??'')==='erase'){HomeIdentity::cookie('');try{self::purgeFiles();self::exportLedger();}catch(\Throwable){error_log('OpenAliment private erasure cleanup deferred to worker.');}}return $result;
    }
    private static function revokeAccess(string $account,?string $household=null): void {
        $params=[Db::now(),$account];$where='account_id=?';if($household!==null){$where.=' AND household_id=?';$params[]=$household;}
        Db::run('UPDATE home_devices SET revoked_at=COALESCE(revoked_at,?) WHERE '.$where,$params);
        if(Migrations::api())foreach(Db::all('SELECT id FROM api_oauth_grants WHERE home_account_id=?'.($household===null?'':' AND household_id=?'),$household===null?[$account]:[$account,$household])as$grant)OAuth::revokeGrant($grant['id']);
    }
    private static function erase(array $p): array {
        self::validateAction($p,'erase',[]);$account=Db::one('SELECT email FROM home_accounts WHERE id=? FOR UPDATE',[$p['account_id']]);$subject=PrivateKey::subject($account['email']);self::revokeAccess($p['account_id']);
        $shared=Db::all('SELECT h.id,h.owner_account_id FROM home_households h JOIN home_members m ON m.household_id=h.id WHERE m.account_id=? AND h.owner_account_id<>?',[$p['account_id'],$p['account_id']]);
        foreach(Db::all('SELECT id FROM home_households WHERE owner_account_id=? FOR UPDATE',[$p['account_id']])as$household){$id=$household['id'];
            if(Db::one('SELECT account_id FROM home_members WHERE household_id=? AND account_id<>? LIMIT 1',[$id,$p['account_id']]))throw new Problem(409,'home_transfer_required','Familia are și alți membri. Transferă mai întâi administrarea ei.');
            if(Migrations::api()){
                foreach(Db::all('SELECT id FROM api_oauth_grants WHERE household_id=?',[$id])as$grant)OAuth::revokeGrant($grant['id']);
                Db::run('UPDATE api_oauth_grants SET household_id=NULL,home_device_id=NULL WHERE household_id=?',[$id]);
            }
            foreach(Db::all('SELECT storage_key FROM home_photos WHERE household_id=?',[$id])as$photo)Db::run('INSERT IGNORE INTO private_file_deletions (id,storage_key,created_at) VALUES (?,?,?)',[Db::id(),$photo['storage_key'],Db::now()]);
            foreach(['home_changes','home_history','home_items','home_photos','home_invites','home_access_events','home_members']as$table)Db::run('DELETE FROM '.$table.' WHERE household_id=?',[$id]);
            Db::run('DELETE FROM home_devices WHERE household_id=?',[$id]);Db::run('DELETE FROM home_households WHERE id=?',[$id]);
        }
        Db::run('DELETE FROM home_invites WHERE created_by=? OR used_by=?',[$p['account_id'],$p['account_id']]);Db::run('DELETE FROM home_members WHERE account_id=?',[$p['account_id']]);
        Db::run('DELETE FROM home_access_events WHERE account_id=?',[$p['account_id']]);
        Db::run('DELETE FROM home_login_tokens WHERE email=?',[$account['email']]);
        Db::run('DELETE FROM account_challenges WHERE home_account_id=?',[$p['account_id']]);
        if(Migrations::api())Db::run('UPDATE api_oauth_grants SET home_account_id=NULL,home_device_id=NULL,household_id=NULL WHERE home_account_id=?',[$p['account_id']]);
        Db::run('UPDATE home_devices SET name=?,token_hash=SHA2(CONCAT(id,?),256),revoked_at=COALESCE(revoked_at,?) WHERE account_id=?',['Dispozitiv deconectat',bin2hex(random_bytes(32)),Db::now(),$p['account_id']]);
        // Keep a pseudonymous FK anchor for events that belong to other household members.
        Db::run('UPDATE home_accounts SET email=? WHERE id=?',['deleted-'.Db::id().'@deleted.invalid',$p['account_id']]);$erasure=Db::id();
        Db::insert('home_erasure_requests',['id'=>$erasure,'subject_sha256'=>$subject,'completed_at'=>Db::now(),'details_json'=>Db::json(['shared_contributions'=>'retained_by_household','account_anchor'=>$p['account_id'],'shared_households'=>$shared])]);
        return ['completed'=>true,'action'=>'erase','receipt_id'=>$erasure,'shared_contributions'=>'retained_by_household','files'=>'queued_for_private_deletion'];
    }
    public static function purgeFiles(int $limit=200): array {
        if(!Migrations::readiness())return ['deleted'=>0];$count=0;
        if(!Db::one("SELECT GET_LOCK('oa_backup',0) AS acquired")['acquired'])return ['deleted'=>0,'deferred'=>true];
        try{
        foreach(Db::all('SELECT id,storage_key FROM private_file_deletions ORDER BY created_at LIMIT '.max(1,min(500,$limit)))as$row){
            if(!preg_match('~\Ahome/[a-f0-9]{32}/[a-f0-9]{32}\.jpg\z~D',$row['storage_key']))continue;
            $base=realpath(rtrim((string)Config::get('storage'),'/\\').'/private/home');$path=rtrim((string)Config::get('storage'),'/\\').'/private/'.$row['storage_key'];$real=realpath($path);
            if($real&&(!$base||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||is_link($path)))continue;
            if(!$real||@unlink($real)){Db::run('DELETE FROM private_file_deletions WHERE id=?',[$row['id']]);$count++;}else Db::run('UPDATE private_file_deletions SET attempts=attempts+1 WHERE id=?',[$row['id']]);
        }return ['deleted'=>$count];
        }finally{Db::run("SELECT RELEASE_LOCK('oa_backup')");}
    }
    public static function exportLedger(): void {
        if(!Migrations::readiness())return;$directory=rtrim((string)Config::get('storage'),'/\\').'/private/erasure';if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))return;
        $file=$directory.'/ledger.ndjson';$handle=fopen($file,'ab');if(!$handle)return;
        try{if(!flock($handle,LOCK_EX))return;
            foreach(Db::all('SELECT id,subject_sha256,completed_at,details_json FROM home_erasure_requests WHERE exported_at IS NULL ORDER BY completed_at,id LIMIT 500')as$row){$row['details']=json_decode($row['details_json'],true,32,JSON_THROW_ON_ERROR);unset($row['details_json']);$line=Db::json($row)."\n";if(fwrite($handle,$line)!==strlen($line))break;fflush($handle);Db::run('UPDATE home_erasure_requests SET exported_at=? WHERE id=?',[Db::now(),$row['id']]);}@chmod($file,0600);
        }finally{flock($handle,LOCK_UN);fclose($handle);}
    }
}
