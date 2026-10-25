<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Db,Http,Problem,Session};
use App\Domain\{Catalog,Community,Mutations,Portability};
final class ApiController {
    public static function handle(string $method,string $path): never {
        if($method==='GET'){
            if($path==='/api/v1/products'){
                $limit=min(100,max(1,(int)($_GET['limit']??20)));$page=max(1,min(100000,(int)($_GET['page']??1)));$offset=($page-1)*$limit;
                $rows=Db::all("SELECT t.product_id,t.id AS trade_item_id,v.snapshot_json FROM trade_items t JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id WHERE v.state='published' ORDER BY t.id LIMIT ".$limit.' OFFSET '.$offset);
                $items=[];foreach($rows as$r){$s=json_decode($r['snapshot_json'],true);$items[]=['id'=>$r['product_id'],'trade_item_id'=>$r['trade_item_id'],'name'=>$s['name'],'category'=>$s['category'],'url'=>url('/t/'.$r['trade_item_id'])];}Http::json(['items'=>$items,'page'=>$page,'limit'=>$limit]);
            }
            if(preg_match('~^/api/v1/(products|trade-items|batches)/([a-f0-9]{32})$~',$path,$m))Http::json($m[1]==='batches'?Catalog::publicBatch($m[2]):Catalog::publicItem($m[2],$m[1]==='products'));
            if(preg_match('~^/api/v1/recipe-versions/([a-f0-9]{32})$~',$path,$m)){$r=Db::one("SELECT id,product_id,version_key,snapshot_json,snapshot_sha256,license_id,technical_status FROM recipe_versions WHERE id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL",[$m[1]]);if(!$r)throw new Problem(404,'not_found','Versiunea nu a fost găsită.');Http::json($r);}
            if(preg_match('~^/api/v1/feedback/([a-f0-9]{32})/status$~',$path,$m)){
                $token=$_SERVER['HTTP_X_STATUS_TOKEN']??'';$row=Db::one('SELECT status FROM feedback WHERE id=? AND contact_token_hash=?',[$m[1],hash('sha256',$token)]);if(!$row)throw new Problem(404,'not_found','Feedbackul nu a fost găsit.');Http::json($row);
            }
            if($path==='/api/v1/manage/export'){$p=Auth::principal('export');if(($_GET['format']??'')==='ndjson')Portability::stream($p);Http::json(Portability::export($p));}
            $p=Auth::principal('read');
            if(preg_match('~^/api/v1/manage/trade-items/([a-f0-9]{32})/resolver-links$~',$path,$resolverMatch))Http::json(['items'=>\App\Domain\ResolverRegistry::history($p,$resolverMatch[1])]);
            if($path==='/api/v1/manage/products')Http::json(['items'=>Db::all('SELECT id,name,category,created_at FROM products WHERE tenant_id=? ORDER BY id LIMIT 100',[$p['tenant']])]);
            if(preg_match('~^/api/v1/manage/qr/(item|batch)/([a-f0-9]{32})$~',$path,$m))Http::json(Catalog::qr($p,$m[1],$m[2]));
            if(preg_match('~^/api/v1/manage/(products|trade-items|labels|batches|recipes|documents)/([a-f0-9]{32})$~',$path,$m)){
                $table=['products'=>'products','trade-items'=>'trade_items','labels'=>'product_info_versions','batches'=>'batches','recipes'=>'recipe_versions','documents'=>'documents'][$m[1]];$r=Catalog::owned($table,$m[2],$p);unset($r['storage_key']);Http::json($r);
            }
            throw new Problem(404,'route','Adresa API nu a fost găsită.');
        }
        if($method!=='POST')throw new Problem(405,'method','Metodă nepermisă.');
        if(\App\Core\Api::bearer()===null&&$path!=='/api/v1/feedback')Http::sameOrigin();$d=Http::input(str_ends_with($path,'/import')?24000000:2000000);
        if($path==='/api/v1/feedback')Http::json(Community::feedback($d),201);
        $routes=[
            '~^/api/v1/manage/trade-items/([a-f0-9]{32})/resolver-links$~'=>['resolver.draft','write'],
            '~^/api/v1/manage/resolver-links/([a-f0-9]{32})/publish$~'=>['resolver.publish','publish'],
            '~^/api/v1/manage/resolver-links/([a-f0-9]{32})/withdraw$~'=>['resolver.withdraw','publish'],
            '~^/api/v1/manage/products$~'=>['product.create','write'],
            '~^/api/v1/manage/products/([a-f0-9]{32})/trade-items$~'=>['trade.create','write'],
            '~^/api/v1/manage/trade-items/([a-f0-9]{32})/labels$~'=>['label.create','write'],
            '~^/api/v1/manage/labels/([a-f0-9]{32})/publish$~'=>['label.publish','publish'],
            '~^/api/v1/manage/trade-items/([a-f0-9]{32})/batches$~'=>['batch.create','write'],
            '~^/api/v1/manage/batches/([a-f0-9]{32})/publish$~'=>['batch.publish','publish'],
            '~^/api/v1/manage/batches/([a-f0-9]{32})/release$~'=>['batch.release','publish'],
            '~^/api/v1/manage/batches/([a-f0-9]{32})/alerts$~'=>['alert.create','alerts'],
            '~^/api/v1/manage/alerts/([a-f0-9]{32})/resolve$~'=>['alert.resolve','alerts'],
            '~^/api/v1/manage/products/([a-f0-9]{32})/recipes$~'=>['recipe.create','write'],
            '~^/api/v1/manage/products/([a-f0-9]{32})/proposals$~'=>['proposal.create','write'],
            '~^/api/v1/manage/proposals/([a-f0-9]{32})/decide$~'=>['proposal.decide','publish'],
            '~^/api/v1/manage/documents/([a-f0-9]{32})/publish$~'=>['document.publish','publish'],
            '~^/api/v1/manage/feedback/([a-f0-9]{32})/moderate$~'=>['feedback.moderate','publish']
        ];
        foreach($routes as$pattern=>[$action,$scope])if(preg_match($pattern,$path,$m)){
            $p=Auth::principal($scope);if(!$p['token'])Session::verify();Auth::rate('api|'.($p['token']??$p['user']),120);
            $result=Mutations::idempotent($p,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',hash('sha256',$path.'|'.Db::json($d)),fn()=>Mutations::run($p,$action,$m[1]??'',$d));Http::json($result,201);
        }
        if(in_array($path,['/api/v1/manage/import','/api/v1/manage/import-pbt'],true)){
            $p=Auth::principal('import');if(!$p['token'])Session::verify();$dry=($d['dry_run']??true)!==false;
            $fn=fn()=>str_ends_with($path,'import-pbt')?Portability::pilot($p,$dry):Portability::import($p,$d['package']??[],$dry);
            Http::json($dry?$fn():Mutations::idempotent($p,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',hash('sha256',$path.'|'.Db::json($d)),$fn));
        }
        throw new Problem(404,'route','Adresa API nu a fost găsită.');
    }
}
