<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Db,Http,Migrations,Problem,Session};
use App\Domain\{Integrations,Mutations};

final class IntegrationController {
    public static function handle(string $method,string $path): never {
        if(!in_array($method,['GET','POST'],true))throw new Problem(405,'method','Metodă nepermisă.');
        $p=Auth::principal($method==='GET'?'read':'write');
        if(!Migrations::integrations())throw new Problem(503,'integration_setup','Integrarea nu este disponibilă momentan.');
        if($method==='GET'){
            if($path==='/api/v2/manage/connectors')Http::json(['items'=>Integrations::list($p)]);
            if(preg_match('~\A/api/v2/manage/connectors/([a-f0-9]{32})/mappings\z~D',$path,$m))Http::json(['items'=>Integrations::mappings($p,$m[1])]);
        }else{
            if(!$p['token']){Http::sameOrigin();Session::verify();}Auth::write($p);Auth::rate('integration|'.($p['token']??$p['user']),120);$d=Http::input(1100000);
            if($path==='/api/v2/manage/connectors')$fn=fn()=>Integrations::create($p,$d);
            elseif(preg_match('~\A/api/v2/manage/connectors/([a-f0-9]{32})/(mappings|preview|ingest)\z~D',$path,$m)){
                if($m[2]==='preview')Http::json(Integrations::preview($p,$m[1],$d));
                $fn=$m[2]==='mappings'?fn()=>Integrations::mapping($p,$m[1],$d):fn()=>Integrations::ingest($p,$m[1],$d);
            }
            if(isset($fn))Http::json(Mutations::idempotent($p,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',hash('sha256',$path.'|'.Db::json($d)),$fn),201);
        }
        throw new Problem(404,'route','Adresa API nu a fost găsită.');
    }
}
