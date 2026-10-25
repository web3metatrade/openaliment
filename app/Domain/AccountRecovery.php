<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Api,Auth,Config,Db,Mailer,Migrations,Passwords,Problem,SecurityLog,Session};

final class AccountRecovery {
    public static function request(string $email): array {
        AccountChallenges::ready();if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)throw new Problem(422,'email','Verifică adresa de email.');
        if(!Mailer::ready())throw new Problem(503,'email_unavailable','Recuperarea prin email nu este disponibilă momentan.');
        $email=strtolower($email);Auth::rate('recovery-ip|'.Api::clientIp(),10,3600);AccountChallenges::limit('password_reset',hash('sha256',$email));
        $user=Db::one('SELECT id FROM users WHERE email=? AND active=1',[$email]);
        if($user)Db::atomic(function()use($user){$token=AccountChallenges::issue('password_reset',$user['id'],null);
            SecurityOutbox::queue($user['id'],'reset-'.hash('sha256',$token),'Recuperarea contului · OpenAliment',"Ai cerut schimbarea parolei. Deschide legătura și confirmă parola nouă. Legătura expiră în 15 minute și nu dezactivează cheia de acces.\n\n".rtrim((string)Config::get('url'),'/').'/recuperare#token='.$token."\n\nDacă nu ai cerut schimbarea, ignoră mesajul.\n");});
        return ['message'=>'Dacă adresa aparține unui cont, vei primi o legătură de recuperare.'];
    }
    public static function preview(string $token): array {AccountChallenges::read($token,'password_reset');return ['valid'=>true,'expires_in_minutes'=>15,'passkey_preserved'=>true];}
    public static function reset(string $token,array $data): array {
        $password=Passwords::validate($data['password']??null);if(!is_string($data['confirmation']??null)||!hash_equals($password,$data['confirmation']))throw new Problem(422,'password_confirmation','Parolele nu coincid.');
        Auth::rate('reset-confirm|'.Api::clientIp(),10,900);
        return Db::atomic(function()use($token,$password){$challenge=AccountChallenges::read($token,'password_reset',true);$user=Db::one('SELECT id FROM users WHERE id=? AND active=1 FOR UPDATE',[$challenge['user_id']]);if(!$user)throw new Problem(410,'confirmation','Legătura nu mai este disponibilă.');
            AccountChallenges::consume($challenge);Db::run('UPDATE users SET password_hash=? WHERE id=?',[Passwords::hash($password),$user['id']]);
            Db::run('UPDATE producer_sessions SET revoked_at=COALESCE(revoked_at,?) WHERE user_id=?',[Db::now(),$user['id']]);Db::run('UPDATE api_tokens SET revoked_at=COALESCE(revoked_at,?) WHERE user_id=?',[Db::now(),$user['id']]);
            if(Migrations::api())foreach(Db::all('SELECT id FROM api_oauth_grants WHERE user_id=? AND revoked_at IS NULL',[$user['id']])as$grant)OAuth::revokeGrant($grant['id']);
            Db::run("UPDATE account_challenges SET used_at=COALESCE(used_at,?) WHERE user_id=? AND purpose='password_reset'",[Db::now(),$user['id']]);
            SecurityOutbox::notice($user['id'],'password-reset','Parola contului a fost schimbată. Sesiunile și accesurile aplicațiilor au fost retrase.');SecurityLog::record('account.password_reset',['user_id'=>$user['id']]);
            return ['changed'=>true,'login_url'=>'/login','passkey_preserved'=>true];
        });
    }
}
