<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Config,Db,Http,Problem,Session};
use App\Domain\Accounts;
final class SetupController {
    public static function handle(string $method): void {
        if(!Config::get('setup_token_hash')||is_file(Config::get('storage').'/installed.lock'))throw new Problem(404,'not_found','Pagina nu a fost găsită.');
        if($method==='POST'){
            Session::verify();if(!hash_equals(Config::get('setup_token_hash'),hash('sha256',(string)($_POST['setup_token']??''))))throw new Problem(403,'setup','Token incorect.');
            if(PHP_VERSION_ID<80400)throw new Problem(422,'php_version','Selectează PHP 8.4 sau o versiune suportată mai nouă în cPanel.');
            foreach(['pdo_mysql','mbstring','fileinfo','openssl']as$ext)if(!extension_loaded($ext))throw new Problem(422,'extension','Extensie PHP necesară: '.$ext);
            $pdo=Db::pdo();$lock=Db::one("SELECT GET_LOCK('openaliment_setup',0) AS acquired");if(!$lock['acquired'])throw new Problem(409,'setup','Instalarea este deja în curs.');
            try{
                Db::migration(static function(){ $sql=file_get_contents(ROOT.'/database/migrations/001_initial.sql');foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['001_initial',Db::now()]); });
                if(Db::one('SELECT id FROM users LIMIT 1'))throw new Problem(409,'setup','Baza conține deja conturi. Instalarea inițială este blocată.');
                $account=Accounts::register($_POST,true);\App\Core\Migrations::upgradeResolver();\App\Core\Migrations::upgradeExternal(true);
                if(!is_dir(Config::get('storage')))mkdir(Config::get('storage'),0700,true);file_put_contents(Config::get('storage').'/installed.lock',Db::now(),LOCK_EX);Session::login($account['id']);Http::redirect('/manage');
            }finally{Db::run("SELECT RELEASE_LOCK('openaliment_setup')");}
        }
        Http::view('auth',['mode'=>'setup','title'=>'Configurare OpenAliment']);
    }
}
