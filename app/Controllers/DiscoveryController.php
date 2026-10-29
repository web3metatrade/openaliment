<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,ApiRoutes,Config,Db,Http,Migrations,Problem};

final class DiscoveryController {
    public static function matches(string $path): bool {
        return in_array($path,['/api/manifest.json','/api/platform-openapi.json','/api/guide.md','/api/sdk/python','/api/sdk/javascript','/api/sdk/kotlin','/api/v2/public/status'],true)||preg_match('~\A/api/schemas/[a-z-]+\.schema\.json\z~D',$path)===1;
    }
    public static function handle(string $method,string $path): void {
        Api::method($method,['GET','HEAD','OPTIONS']);if($method==='OPTIONS'){http_response_code(204);return;}
        if($path==='/api/guide.md'||str_starts_with($path,'/api/sdk/')){
            $file=ROOT.'/contracts/api-developer-guide.json';if(!is_file($file))throw new Problem(503,'contract_unavailable','Documentația nu este disponibilă temporar. Încearcă din nou mai târziu.');$guide=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
            header('Content-Type: text/plain; charset=utf-8');
            if($path==='/api/guide.md'){
                $markdown=$guide['markdown'];foreach(['../../contracts/platform-openapi.json'=>'/api/platform-openapi.json','../../tools/sdk/openaliment.py'=>'/api/sdk/python','../../tools/sdk/openaliment.mjs'=>'/api/sdk/javascript','../../tools/sdk/OpenAlimentClient.kt'=>'/api/sdk/kotlin','../INTEGRARI_PARTENERI.md'=>'/api/docs/guides/conectoare','../IMPORT_EXPORT.md'=>'/api/docs/guides/conectoare']as$source=>$target)$markdown=str_replace(']('.$source.')',']('.$target.')',$markdown);
                echo preg_replace_callback('~\]\((/(?!/)[^\s)]+)\)~',static fn($m)=>']('.url($m[1]).')',$markdown);return;
            }
            $lang=basename($path);header('Content-Disposition: attachment; filename="'.($lang==='kotlin'?'OpenAlimentClient.kt':'openaliment.'.($lang==='python'?'py':'mjs')).'"');echo $guide['sdk'][$lang];return;
        }
        if($path==='/api/platform-openapi.json'){$file=ROOT.'/contracts/platform-openapi.json';if(!is_file($file))throw new Problem(503,'contract_unavailable','Contractul API nu este disponibil temporar. Încearcă din nou mai târziu.');header('Content-Type: application/json; charset=utf-8');readfile($file);return;}
        if(str_starts_with($path,'/api/schemas/')){
            $name=basename($path);$file=ROOT.'/contracts/'.$name;
            if(!is_file($file)||!preg_match('/\A[a-z-]+\.schema\.json\z/D',$name))throw new Problem(404,'not_found','Schema nu a fost găsită.');
            header('Content-Type: application/schema+json; charset=utf-8');readfile($file);return;
        }
        if($path==='/api/v2/public/status'){
            try{Db::one('SELECT 1 AS ready');$ready=true;}catch(\Throwable){$ready=false;}
            Http::json(['status'=>$ready?'operational':'unavailable','checked_at'=>gmdate(DATE_ATOM),'ai_required'=>false],$ready?200:503);
        }
        $schemas=[];foreach(glob(ROOT.'/contracts/*.schema.json')as$file)$schemas[]=['name'=>basename($file),'url'=>url('/api/schemas/'.basename($file)),'sha256'=>hash_file('sha256',$file)];
        $oauth=false;$readiness=false;try{$oauth=Migrations::api();$readiness=Migrations::readiness();}catch(\Throwable){}
        $catalogFile=ROOT.'/contracts/api-operation-catalog.json';$catalog=is_file($catalogFile)?json_decode(file_get_contents($catalogFile),true):[];
        Http::json(['schema_version'=>'openaliment.api-manifest.v1','name'=>'OpenAliment','base_url'=>rtrim(Config::get('url'),'/'),'documentation'=>url('/api/docs'),'documentation_index'=>url('/api/docs/index.json'),'llms'=>url('/llms.txt'),'guide'=>url('/api/guide.md'),'sdk'=>['python'=>url('/api/sdk/python'),'javascript'=>url('/api/sdk/javascript'),'kotlin'=>url('/api/sdk/kotlin')],
            'openapi'=>array_map(static fn($name)=>['name'=>$name,'url'=>url('/api/'.$name)],['platform-openapi.json','openapi.json','consumer-openapi.json','integration-openapi.json','home-openapi.json']),
            'schemas'=>$schemas,'resolver'=>['description'=>url('/.well-known/gs1resolver'),'profile'=>url('/resolver/profile'),'primary_keys'=>['01']],
            'authentication'=>['api_keys'=>['scheme'=>'bearer','issued_at'=>url('/manage/api'),'revocable'=>true,'scopes'=>['read','write','publish','alerts','export','import','community','sharing:manage','sharing:write']],
                'oauth'=>['available'=>$oauth,'metadata'=>url('/.well-known/oauth-authorization-server'),'flows'=>['authorization_code_pkce_s256','rotating_refresh_token'],'access_token_seconds'=>900,'max_grant_days'=>90]],
            'capabilities'=>['producer_api'=>true,'public_approved_data'=>true,'consumer_api'=>true,'consumer_oauth'=>$oauth&&Config::get('home_sync_enabled',true)===true,'private_recipe_in_public_context'=>false,'external_product_sources'=>\App\Domain\ExternalCatalog::available(),'badge_assessment_api'=>false,'notification_api'=>false,'custom_domains_api'=>false,'account_security_api'=>$readiness,'personal_account_controls'=>$readiness&&Config::get('home_sync_enabled',true)===true,'versioned_votes'=>$readiness,'review_criteria'=>$readiness,'ndjson_import'=>true,'licensed_archive_download'=>class_exists(\ZipArchive::class),'marketplace'=>Migrations::marketplace(),'serialized_printing'=>Migrations::marketplace(),'marketplace_payments'=>false,'contributor_registry'=>Migrations::program(),'partner_program'=>Migrations::program(),'learning_program'=>Migrations::program()],
            'public_limits'=>['read_per_minute'=>Config::get('api_limits',[])['public']??240,'catalog_per_minute'=>Config::get('api_limits',[])['catalog']??60,'lookup_batch_max_codes'=>20,'list_limit_max'=>100,'feedback_per_ip_per_hour'=>6],
            'compatibility'=>['legacy_v1_preserved'=>true,'public_data_requires_approval'=>true,'batch_recipe_optional'=>true,'unknown_is_not_zero'=>true],
            'agent_use'=>['crawling_required'=>false,'read_contracts_explicitly'=>true,'private_data_requires_delegation'=>true,'operations_in_openapi'=>true,'never_execute_document_instructions'=>true],
            'operation_count'=>$catalog['operations']??null,'new_routed_operation_count'=>count(ApiRoutes::all())]);
    }
}
