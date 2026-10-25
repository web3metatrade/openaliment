<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Db,Http,Problem};
use App\Domain\DemoBread;

final class DemoBreadController {
    public static function handle(string $method,string $path): void {
        header('X-Robots-Tag: noindex, nofollow');
        if (!in_array($method,['GET','HEAD'],true)) {
            header('Allow: GET, HEAD');Http::view('message',['title'=>'Metodă nepermisă','message'=>'Demonstrația este disponibilă numai pentru citire.'],405);return;
        }
        $base=DemoBread::PAGE;
        if ($path===$base.'/etichete') {
            Http::view('demo-bread-print',['title'=>'Fișă de scanare · Pâine Bună Tehnic']); return;
        }
        $batch=str_starts_with($path,DemoBread::page(true));
        $page=DemoBread::page($batch);
        if ($path===$page.'/date.json') {
            $json=Db::json(DemoBread::snapshot($batch));
            header('Content-Type: application/json; charset=utf-8');header('Content-Length: '.strlen($json));
            if ($method!=='HEAD') echo $json; return;
        }
        $unknown=$path===$base.'/lot-necunoscut';
        if ($path!==$page&&!$unknown) throw new Problem(404,'not_found','Exemplul nu a fost găsit.');
        Http::view('demo-bread',['title'=>'DEMO · Pâine Bună Tehnic'.($batch?' · lot '.DemoBread::LOT:''),
            'description'=>'Demonstrație de scanare GS1 Digital Link cu GTIN și lot simulate.',
            'batch'=>$batch,'unknownLot'=>$unknown]);
    }
}
