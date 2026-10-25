<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Problem};

final class Administration {
    public static function decide(array $user,string $action,string $id,array $data): array {
        $reason=Catalog::text($data,'evidence',5000);
        return Db::atomic(function()use($user,$action,$id,$data,$reason){
            if($action==='organization.verify'){
                if(!Db::one('SELECT id FROM organizations WHERE id=?',[$id]))throw new Problem(404,'not_found','Organizația nu a fost găsită.');
                Db::run("UPDATE organizations SET identity_status='verified' WHERE id=?",[$id]);Catalog::audit(['tenant'=>$id,'user'=>$user['id']],'organization.verified',$id,['evidence'=>$reason]);
            }elseif($action==='contact.verify'){
                $member=Db::one('SELECT tenant_id FROM memberships WHERE user_id=? ORDER BY tenant_id LIMIT 1',[$id]);if(!$member)throw new Problem(404,'not_found','Contul nu a fost găsit.');
                Db::run('UPDATE users SET email_verified=1 WHERE id=?',[$id]);Catalog::audit(['tenant'=>$member['tenant_id'],'user'=>$user['id']],'contact.manually_verified',$id,['evidence'=>$reason]);
            }elseif($action==='gtin.review'){
                $item=Db::one('SELECT t.*,o.identity_status FROM trade_items t JOIN organizations o ON o.id=t.tenant_id WHERE t.id=? FOR UPDATE',[$id]);if(!$item||!$item['gtin14'])throw new Problem(404,'not_found','Articolul nu are un GTIN înregistrat.');
                $state=$data['state']??'';if(!in_array($state,['verified','disputed','withdrawn'],true))throw new Problem(422,'state','Stare incorectă.');
                if($state==='verified'){
                    if($item['identity_status']!=='verified')throw new Problem(422,'organization','Verifică identitatea organizației și mandatul reprezentantului.');
                    $registered=Db::one('SELECT * FROM gtin_registry WHERE gtin14=? FOR UPDATE',[$item['gtin14']]);
                    if($registered&&$registered['trade_item_id']!==$id)throw new Problem(409,'gtin_conflict','GTIN este atribuit altui articol. Clarifică mandatul înainte de transfer.');
                    if(!$registered)Db::insert('gtin_registry',['gtin14'=>$item['gtin14'],'tenant_id'=>$item['tenant_id'],'trade_item_id'=>$id]);
                }
                Db::run('UPDATE trade_items SET gtin_status=?,claim_evidence=?,verified_by=?,verified_at=COALESCE(verified_at,?) WHERE id=?',[$state,$reason,$user['id'],Db::now(),$id]);Catalog::audit(['tenant'=>$item['tenant_id'],'user'=>$user['id']],'gtin.reviewed',$id,['state'=>$state,'evidence'=>$reason]);
            }else throw new Problem(422,'action','Operațiune incorectă.');
            return ['id'=>$id,'recorded'=>true];
        });
    }
}
