<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Http,Problem};
use App\Domain\ConsumerFood;

final class ConsumerController {
    public static function handle(string $method,string $path): void {
        if (str_starts_with($path,'/api/v2/public/')) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
            header('Allow: GET, HEAD, OPTIONS');
            if ($method==='OPTIONS') { http_response_code(204); return; }
        }
        if (!in_array($method,['GET','HEAD'],true)) throw new Problem(405,'method','Metodă nepermisă.');
        header('X-Robots-Tag: noindex, nofollow');
        if ($path==='/api/v2/public/capabilities') Http::json(['schema_version'=>'openaliment.capabilities.v1','date_ocr'=>\App\Domain\ConsumerAssets::ready(),'cloud_sync'=>\App\Core\Config::get('home_sync_enabled',true)===true&&\App\Core\Migrations::home(),'sync_scope'=>['packages','photos','history'],'shopping_sync'=>false,'digital_receipts'=>\App\Core\Migrations::integrations(),'external_sources'=>\App\Domain\ExternalCatalog::available()]);
        if ($path==='/api/v2/public/food') {
            $code=$_GET['code']??null;
            if (!is_string($code)) throw new Problem(422,'scan','Lipsește codul alimentului.');
            Http::json(ConsumerFood::lookup($code));
        }
        if ($path==='/acasa') Http::redirect('/acasa/');
        if ($path==='/acasa/sw.js') {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Service-Worker-Allowed: /acasa/');
            readfile(ROOT.'/public/assets/home/sw.js');return;
        }
        if ($path==='/acasa/manifest.webmanifest') {
            header('Content-Type: application/manifest+json; charset=utf-8');
            echo json_encode(['id'=>'/acasa/','name'=>'OpenAliment Acasă','short_name'=>'Acasă','lang'=>'ro','start_url'=>'/acasa/','scope'=>'/acasa/','display'=>'standalone','background_color'=>'#f6f7f2','theme_color'=>'#124e42','icons'=>[['src'=>'/assets/mark.svg','sizes'=>'any','type'=>'image/svg+xml','purpose'=>'any']]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);return;
        }
        if ($path!=='/acasa/') throw new Problem(404,'not_found','Pagina nu a fost găsită.');
        header('X-OpenAliment-App: home');
        header('Content-Type: text/html; charset=utf-8');
        require ROOT.'/app/Views/consumer-home.php';
    }
}
