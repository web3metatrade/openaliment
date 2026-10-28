<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Core\{Db,Migrations};
use App\Domain\Accounts;
Migrations::upgradeAnaf();
Db::run('DELETE FROM rate_limits');
if(in_array('--reset-rates',$argv,true)){echo "Reset disposable ANAF HTTP rate counters.\n";exit;}
$password=bin2hex(random_bytes(18));$email='anaf-admin-'.Db::id().'@example.invalid';
$admin=Accounts::register(['email'=>$email,'password'=>$password,'display_name'=>'ANAF synthetic admin','organization'=>'ANAF HTTP administration'],true);
file_put_contents(__DIR__.'/runtime/anaf-http-fixtures.json',json_encode(['email'=>$email,'password'=>$password,'admin'=>$admin],JSON_THROW_ON_ERROR));
echo "Prepared private ANAF HTTP fixtures.\n";
