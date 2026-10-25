<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Api,Auth,Db,Migrations,Problem};

final class ApiKeys {
    public static function owner(array $p): void {
        if($p['role']!=='owner'||$p['token']!==null)throw new Problem(403,'key_administration','Administrarea cheilor necesită contul proprietarului organizației.');
    }
    public static function recent(array $p,array $data): void {
        self::owner($p);
        $identity=Db::one('SELECT id,is_admin FROM users WHERE id=? AND active=1',[$p['user']]);if($identity&&Passkeys::required($identity))Passkeys::recent($identity);
        if(($_SESSION['authenticated_at']??0)>=time()-900)return;
        Auth::rate('key-reauth|'.$p['user'],5,900);
        $user=Db::one('SELECT password_hash FROM users WHERE id=? AND active=1',[$p['user']]);
        if(!$user||!\App\Core\Passwords::verify($data['current_password']??null,$user['password_hash']))throw new Problem(403,'reauthentication','Confirmă parola contului pentru emiterea unei chei.');
        $_SESSION['authenticated_at']=time();
    }
    public static function list(array $p): array {
        self::owner($p);[$limit,$cursor]=Api::page();
        $rows=Db::all('SELECT t.id,t.name,t.scopes,t.expires_at,t.revoked_at,t.created_at,m.prefix,m.last_used_at,m.rotated_from,m.revocation_reason FROM api_tokens t LEFT JOIN api_token_metadata m ON m.token_id=t.id WHERE t.tenant_id=? AND t.id>? ORDER BY t.id LIMIT '.($limit+1),[$p['tenant'],$cursor]);
        return Api::collection($rows,$limit,static function($r){$r['scopes']=json_decode($r['scopes'],true);return $r;});
    }
    public static function create(array $p,array $data): array {
        self::recent($p,$data);return Accounts::token($p,$data);
    }
    public static function revoke(array $p,string $id,string $reason='Revocată de proprietarul organizației'): array {
        self::owner($p);Api::id($id);
        return Db::atomic(function()use($p,$id,$reason){
            if(!Db::one('SELECT id FROM api_tokens WHERE tenant_id=? AND id=? FOR UPDATE',[$p['tenant'],$id]))throw new Problem(404,'not_found','Cheia nu a fost găsită.');
            Db::run('UPDATE api_tokens SET revoked_at=COALESCE(revoked_at,?) WHERE tenant_id=? AND id=?',[Db::now(),$p['tenant'],$id]);
            if(Migrations::api())Db::run('UPDATE api_token_metadata SET revocation_reason=? WHERE token_id=?',[mb_substr($reason,0,500),$id]);
            Catalog::audit($p,'token.revoked',$id);return ['id'=>$id,'revoked'=>true];
        });
    }
    public static function rotate(array $p,string $id,array $data): array {
        self::recent($p,$data);Api::id($id);
        return Db::atomic(function()use($p,$id,$data){
            $old=Db::one('SELECT * FROM api_tokens WHERE tenant_id=? AND id=? AND revoked_at IS NULL AND expires_at>? FOR UPDATE',[$p['tenant'],$id,Db::now()]);
            if(!$old)throw new Problem(404,'not_found','Cheia activă nu a fost găsită.');
            Db::run('UPDATE api_tokens SET revoked_at=? WHERE id=?',[Db::now(),$id]);
            $created=Accounts::token($p,['name'=>$data['name']??$old['name'],'days'=>$data['days']??90,'scopes'=>json_decode($old['scopes'],true)]);
            if(Migrations::api())Db::run('UPDATE api_token_metadata SET rotated_from=? WHERE token_id=?',[$id,$created['id']]);
            Catalog::audit($p,'token.rotated',$id,['replacement_id'=>$created['id']]);return $created+['previous_id'=>$id,'previous_revoked'=>true];
        });
    }
}
