<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Problem};

final class HomeHouseholds {
    private static function owner(array $p): void {Db::one('SELECT id FROM home_households WHERE id=? FOR UPDATE',[$p['household_id']]);if(HomeSync::member($p)['role']!=='owner')throw new Problem(403,'home_owner','Numai proprietarul familiei poate face această modificare.');}
    public static function invite(array $p): array {
        return Db::atomic(function()use($p){self::owner($p);$token=bin2hex(random_bytes(32));Db::insert('home_invites',['token_hash'=>hash('sha256',$token),'household_id'=>$p['household_id'],'created_by'=>$p['account_id'],'expires_at'=>gmdate('Y-m-d H:i:s',time()+7*86400),'created_at'=>Db::now()]);HomeAudit::record($p,'invite.created',$p['household_id'],['expires_in_days'=>7]);return ['url'=>url('/acasa/#familie='.$token),'expires_in_days'=>7];});
    }
    public static function preview(string $token): array {
        $hash=self::hash($token);$r=Db::one('SELECT h.name FROM home_invites i JOIN home_households h ON h.id=i.household_id WHERE i.token_hash=? AND i.used_by IS NULL AND i.expires_at>?',[$hash,Db::now()]);if(!$r)throw new Problem(410,'home_invite','Invitația nu mai este disponibilă.');return ['name'=>$r['name']];
    }
    public static function join(array $p,string $token): array {
        $hash=self::hash($token);
        return Db::atomic(function()use($p,$hash){$i=Db::one('SELECT * FROM home_invites WHERE token_hash=? FOR UPDATE',[$hash]);if(!$i||$i['used_by']!==null||$i['expires_at']<=Db::now())throw new Problem(410,'home_invite','Invitația nu mai este disponibilă.');
            Db::one('SELECT id FROM home_households WHERE id=? FOR UPDATE',[$i['household_id']]);if(!Db::one('SELECT account_id FROM home_members WHERE household_id=? AND account_id=?',[$i['household_id'],$p['account_id']])&&(int)Db::one('SELECT COUNT(*) AS n FROM home_members WHERE household_id=?',[$i['household_id']])['n']>=20)throw new Problem(413,'home_members','Familia are deja 20 de membri.');
            // A link can add an editor, never transfer ownership or producer permissions.
            Db::run("INSERT IGNORE INTO home_members (household_id,account_id,role,created_at) VALUES (?,?,'editor',?)",[$i['household_id'],$p['account_id'],Db::now()]);Db::run('UPDATE home_invites SET used_by=? WHERE token_hash=?',[$p['account_id'],$hash]);Db::run('UPDATE home_devices SET household_id=? WHERE id=? AND account_id=?',[$i['household_id'],$p['device_id'],$p['account_id']]);HomeAudit::record(array_replace($p,['household_id'=>$i['household_id']]),'member.joined',$p['account_id']);return ['household_id'=>$i['household_id']];
        });
    }
    public static function members(array $p): array {HomeSync::member($p);return Db::all('SELECT m.account_id,m.role,a.email FROM home_members m JOIN home_accounts a ON a.id=m.account_id WHERE m.household_id=? ORDER BY m.created_at LIMIT 100',[$p['household_id']]);}
    public static function list(array $p): array {return Db::all('SELECT h.id,h.name,m.role FROM home_members m JOIN home_households h ON h.id=m.household_id WHERE m.account_id=? ORDER BY h.created_at,h.id LIMIT 100',[$p['account_id']]);}
    public static function switch(array $p,string $id): array {
        HomeInventory::id($id);return Db::atomic(function()use($p,$id){if(!Db::one('SELECT household_id FROM home_members WHERE account_id=? AND household_id=?',[$p['account_id'],$id]))throw new Problem(403,'home_membership','Nu ai acces la această familie.');Db::run('UPDATE home_devices SET household_id=?,last_seen_at=? WHERE id=? AND account_id=?',[$id,Db::now(),$p['device_id'],$p['account_id']]);HomeAudit::record(array_replace($p,['household_id'=>$id]),'household.selected',$id);return ['household_id'=>$id];});
    }
    public static function rename(array $p,string $name): array {$name=Catalog::text(['name'=>$name],'name',120);return Db::atomic(function()use($p,$name){self::owner($p);Db::run('UPDATE home_households SET name=? WHERE id=?',[$name,$p['household_id']]);HomeAudit::record($p,'household.renamed',$p['household_id']);return ['name'=>$name];});}
    public static function remove(array $p,string $account): array {
        self::owner($p);HomeInventory::id($account);if($account===$p['account_id'])throw new Problem(422,'home_owner','Proprietarul nu se poate elimina din propria familie.');
        return Db::atomic(function()use($p,$account){Db::one('SELECT id FROM home_households WHERE id=? FOR UPDATE',[$p['household_id']]);self::owner($p);Db::run('DELETE FROM home_members WHERE household_id=? AND account_id=? AND role<>?',[$p['household_id'],$account,'owner']);HomeAudit::record($p,'member.removed',$account);return ['removed'=>true];});
    }
    private static function hash(string $token): string {if(!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new Problem(410,'home_invite','Invitație incorectă.');return hash('sha256',$token);}
}
