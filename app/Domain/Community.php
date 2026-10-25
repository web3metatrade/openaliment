<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Problem};
final class Community {
    public const CRITERIA=['taste'=>'Gust','texture'=>'Textură','aroma'=>'Aromă','freshness'=>'Prospețime','packaging'=>'Ambalaj'];
    public static function criteria(mixed $values): array {
        if($values===null)return [];
        if(!is_array($values)||array_is_list($values)&&$values!==[])throw new Problem(422,'criteria','Notele pe criterii trebuie să fie un obiect.');
        $out=[];foreach($values as$key=>$value){
            if(!is_string($key)||!isset(self::CRITERIA[$key]))throw new Problem(422,'criteria','Criteriu necunoscut.');
            if($value===null||$value==='')continue;
            if(is_string($value)&&preg_match('/\A[1-5]\z/D',$value))$value=(int)$value;
            if(!is_int($value)||$value<1||$value>5)throw new Problem(422,'criteria','Notele pe criterii sunt numere întregi între 1 și 5.');$out[$key]=$value;
        }ksort($out);return $out;
    }
    public static function aggregates(string $product,string $version,?string $batch=null): array {
        $where="f.product_id=? AND f.product_info_version_id=? AND f.status='published' AND f.kind='review'";$params=[$product,$version];if($batch!==null){$where.=' AND f.batch_id=?';$params[]=$batch;}
        $general=Db::one('SELECT COUNT(f.rating) AS responses,AVG(f.rating) AS average FROM feedback f WHERE '.$where,$params);
        $out=['overall'=>['responses'=>(int)$general['responses'],'average'=>$general['average']===null?null:round((float)$general['average'],2)],'criteria'=>[]];
        foreach(self::CRITERIA as$key=>$label)$out['criteria'][$key]=['responses'=>0,'average'=>null,'distribution'=>array_fill_keys(range(1,5),0)];
        if(\App\Core\Migrations::readiness())foreach(Db::all('SELECT c.criterion,c.rating,COUNT(*) AS responses FROM feedback_criteria c JOIN feedback f ON f.id=c.feedback_id WHERE '.$where.' GROUP BY c.criterion,c.rating',$params)as$row){$key=$row['criterion'];if(!isset($out['criteria'][$key]))continue;$n=(int)$row['responses'];$out['criteria'][$key]['responses']+=$n;$out['criteria'][$key]['distribution'][(int)$row['rating']]=$n;}
        foreach($out['criteria']as&$criterion){$n=$criterion['responses'];if($n)$criterion['average']=round(array_sum(array_map(static fn($v,$count)=>$v*$count,array_keys($criterion['distribution']),$criterion['distribution']))/$n,2);}unset($criterion);return $out;
    }
    public static function enabled(): bool {return Config::get('feedback_enabled')&&Config::get('privacy_text')&&Config::get('operator_name')&&Config::get('operator_contact');}
    public static function feedback(array $d): array {
        if(!self::enabled())throw new Problem(503,'feedback_disabled','Feedbackul nu este disponibil momentan.');
        Auth::rate('feedback|'.\App\Core\Api::clientIp(),6,3600);
        if(($d['consent']??false)!==true)throw new Problem(422,'consent','Confirmă informarea privind feedbackul.');
        if(!empty($d['website']))throw new Problem(422,'feedback','Feedbackul nu a putut fi trimis.');
        $batch=Catalog::text($d,'batch_id',32,false);$pub=$batch?Catalog::publicBatch($batch):Catalog::publicItem(Catalog::text($d,'trade_item_id',32));
        if(isset($d['product_info_version_id'])){$version=Catalog::text($d,'product_info_version_id',32);if($batch&&$version!==$pub['product_info_version_id'])throw new Problem(422,'version','Versiunea nu corespunde lotului.');$approved=Db::one("SELECT id FROM product_info_versions WHERE id=? AND trade_item_id=? AND state='published'",[$version,$pub['trade_item_id']]);if(!$approved)throw new Problem(422,'version','Versiunea nu este publicată pentru acest articol.');$pub['product_info_version_id']=$version;}
        if(isset($d['product_id'])&&$d['product_id']!==$pub['product_id'])throw new Problem(422,'product','Produsul și lotul nu corespund.');
        $item=Db::one('SELECT tenant_id FROM trade_items WHERE id=?',[$pub['trade_item_id']]);$kind=$d['kind']??'review';if(!in_array($kind,['review','question','idea','quality'],true))throw new Problem(422,'kind','Tip de feedback incorect.');
        $text=Catalog::text($d,'text',4000);$rating=$d['rating']??null;if($rating!==null&&$rating!==''&&(!is_numeric($rating)||(int)$rating!=$rating||(int)$rating<1||(int)$rating>5))throw new Problem(422,'rating','Nota poate fi între 1 și 5.');
        $criteria=self::criteria($d['criteria']??null);if($criteria&&!\App\Core\Migrations::readiness())throw new Problem(503,'schema_upgrade','Notele pe criterii nu sunt disponibile momentan.');
        if(preg_match('/alergen|muceg|îmboln|imboln|toxiinfec|spital|vomit/iu',$text))$kind='quality';
        $key=(string)($d['submission_key']??$_SERVER['HTTP_IDEMPOTENCY_KEY']??'');if(!preg_match('/\A[A-Za-z0-9_.:\-]{8,128}\z/D',$key))throw new Problem(422,'idempotency','Este necesară cheia unică a trimiterii.');$requestKey=hash('sha256',$key);$fingerprint=[$d['trade_item_id']??null,$d['product_id']??null,$batch,$d['product_info_version_id']??null,$kind,$text,$rating];if($criteria)$fingerprint[]=$criteria;$requestHash=hash('sha256',Db::json($fingerprint));
        $token=bin2hex(random_bytes(32));$id=Db::id();
        return Db::atomic(function()use($item,$id,$pub,$batch,$kind,$text,$rating,$criteria,$token,$requestKey,$requestHash){
            $old=Db::one('SELECT id,status,request_hash FROM feedback WHERE tenant_id=? AND request_key=? FOR UPDATE',[$item['tenant_id'],$requestKey]);if($old){if(!hash_equals($old['request_hash'],$requestHash))throw new Problem(409,'idempotency_conflict','Cheia a fost utilizată pentru alt feedback.');return ['id'=>$old['id'],'status'=>$old['status']];}
            Db::insert('feedback',['id'=>$id,'tenant_id'=>$item['tenant_id'],'product_id'=>$pub['product_id'],'batch_id'=>$batch,'product_info_version_id'=>$pub['product_info_version_id'],'kind'=>$kind,'rating'=>$rating===''?null:$rating,'original_text'=>$text,'contact_token_hash'=>hash('sha256',$token),'request_key'=>$requestKey,'request_hash'=>$requestHash,'consent_version'=>'feedback-v1','created_at'=>Db::now()]);
            foreach($criteria as$criterion=>$score)Db::insert('feedback_criteria',['feedback_id'=>$id,'criterion'=>$criterion,'rating'=>$score]);
            // Raw reports never go to AI. A human approves a redacted public projection first.
            return ['id'=>$id,'status'=>'pending','status_token'=>$token];
        });
    }
    public static function moderate(array $p,string $id,array $d): array {Auth::approve($p);return Db::atomic(function()use($p,$id,$d){
        $f=Catalog::owned('feedback',$id,$p);$status=$d['status']??'';if(!in_array($status,['published','restricted','rejected'],true))throw new Problem(422,'status','Stare incorectă.');
        $reason=Catalog::text($d,'reason',1500);$public=$status==='published'?Catalog::text($d,'public_text',4000):null;
        if($f['kind']==='quality'&&$status==='published')throw new Problem(422,'quality','Sesizările de calitate se gestionează în canalul restricționat.');
        Db::run('UPDATE feedback SET status=?,public_text=? WHERE id=? AND tenant_id=?',[$status,$public,$id,$p['tenant']]);Catalog::audit($p,'feedback.moderated',$id,['status'=>$status,'reason'=>$reason]);
        if($status==='published'&&Config::get('ai')['enabled']&&!Db::one('SELECT id FROM ai_jobs WHERE feedback_id=?',[$id]))Db::insert('ai_jobs',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'feedback_id'=>$id,'available_at'=>Db::now(),'created_at'=>Db::now()]);return ['id'=>$id,'status'=>$status];
    });}
    public static function proposal(array $p,string $product,array $d): array {Auth::write($p);Catalog::owned('products',$product,$p);return Db::atomic(function()use($p,$product,$d){
        if(($d['public']??false)===true)Auth::publicWrite($p);
        $id=Db::id();ProducerQuota::count($p,'change_proposals');Db::insert('change_proposals',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$product,'title'=>Catalog::text($d,'title'),'body'=>Catalog::text($d,'body',5000),'source'=>'human','visibility'=>($d['public']??false)===true?'public':'private','created_at'=>Db::now()]);Catalog::audit($p,'proposal.created',$id);return ['id'=>$id];
    });}
    public static function decide(array $p,string $id,array $d): array {Auth::approve($p);return Db::atomic(function()use($p,$id,$d){Catalog::owned('change_proposals',$id,$p);$state=$d['state']??'';if(!in_array($state,['proposed','planned','accepted','rejected','tested'],true))throw new Problem(422,'state','Stare incorectă.');$reason=Catalog::text($d,'decision',3000);Db::run('UPDATE change_proposals SET state=?,decision=? WHERE id=? AND tenant_id=?',[$state,$reason,$id,$p['tenant']]);Catalog::audit($p,'proposal.decided',$id,['state'=>$state,'reason'=>$reason]);return ['id'=>$id];});}
    public static function vote(string $id,bool $enabled,?int $expected=null): array {
        $u=Auth::user();if(!$u||!$u['email_verified'])throw new Problem(403,'verification','Votul necesită un cont cu contact verificat.');
        Passkeys::full($u);
        return self::voteFor($u['id'],$id,$enabled,$expected);
    }
    public static function voteFor(string $user,string $id,bool $enabled,?int $expected=null): array {
        if(!Db::one('SELECT id FROM users WHERE id=? AND active=1 AND email_verified=1',[$user]))throw new Problem(403,'verification','Votul necesită un cont cu contact verificat.');
        return Db::atomic(function()use($user,$id,$enabled,$expected){
            $proposal=Db::one("SELECT id,tenant_id FROM change_proposals WHERE id=? AND visibility='public' FOR UPDATE",[$id]);if(!$proposal)throw new Problem(404,'not_found','Propunerea nu a fost găsită.');
            if(!\App\Core\Migrations::readiness()){
                if($enabled)Db::run('INSERT IGNORE INTO votes (proposal_id,user_id,created_at) VALUES (?,?,?)',[$id,$user,Db::now()]);else Db::run('DELETE FROM votes WHERE proposal_id=? AND user_id=?',[$id,$user]);return ['proposal_id'=>$id,'voted'=>$enabled,'mutation_version'=>null];
            }
            $row=Db::one('SELECT active,mutation_version FROM community_vote_states WHERE proposal_id=? AND user_id=? FOR UPDATE',[$id,$user]);$version=(int)($row['mutation_version']??0);$active=(bool)($row['active']??false);
            if($expected!==null&&$expected!==$version)throw new Problem(409,'vote_version','Votul s-a schimbat. Recitește starea și retrimite versiunea actuală.');
            if($expected===null&&$version>0&&$active!==$enabled&&(!$active||$version>1))throw new Problem(409,'vote_version_required','Trimite expected_mutation_version pentru a modifica acest vot.');
            if($row&&$active===$enabled)return ['proposal_id'=>$id,'voted'=>$enabled,'state'=>$enabled?'active':'withdrawn','mutation_version'=>$version];
            $version++;Db::run('INSERT INTO community_vote_states (proposal_id,user_id,active,mutation_version,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE active=VALUES(active),mutation_version=VALUES(mutation_version),updated_at=VALUES(updated_at)',[$id,$user,$enabled?1:0,$version,Db::now()]);
            if($enabled)Db::run('INSERT IGNORE INTO votes (proposal_id,user_id,created_at) VALUES (?,?,?)',[$id,$user,Db::now()]);else Db::run('DELETE FROM votes WHERE proposal_id=? AND user_id=?',[$id,$user]);
            return ['proposal_id'=>$id,'voted'=>$enabled,'state'=>$enabled?'active':'withdrawn','mutation_version'=>$version];
        });
    }
}
