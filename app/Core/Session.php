<?php
declare(strict_types=1);
namespace App\Core;
final class Session {
    public static function start(): void {
        if(session_status()===PHP_SESSION_ACTIVE)return;
        $storage=Config::get('storage');if(!is_string($storage)||!is_dir($storage))throw new \RuntimeException('Private session storage unavailable.');
        $directory=rtrim($storage,'/\\').'/private/sessions';if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new \RuntimeException('Private session storage unavailable.');
        @chmod($directory,0700);session_save_path($directory);ini_set('session.gc_probability','0');
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        session_name('oa_session'); session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool)Config::get('session_secure',true),'httponly'=>true,'samesite'=>'Lax']); session_start();
    }
    public static function csrf(): string { self::start();return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
    public static function verify(): void { $token=$_POST['_csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($token)||!hash_equals(self::csrf(),$token))throw new Problem(419,'csrf','Sesiunea formularului a expirat. Reîncarcă pagina.'); }
    public static function login(string $id,bool $verified=false): void { self::start();$user=Db::one('SELECT password_hash FROM users WHERE id=? AND active=1',[$id]);if(!$user)throw new Problem(401,'authentication','Contul nu este disponibil.');session_regenerate_id(true); $_SESSION=['user_id'=>$id,'credential_version'=>hash('sha256',$user['password_hash']),'csrf'=>bin2hex(random_bytes(32)),'last_activity'=>time(),'authenticated_at'=>time(),'session_started_at'=>time(),'mfa_verified_at'=>$verified?time():0];\App\Domain\ProducerSessions::attach($id,$verified); }
    public static function flash(string $message): void {self::start();$_SESSION['flash']=$message;}
}
