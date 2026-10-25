<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Http,Migrations,Problem};
use App\Domain\DigitalReceipt;

final class ReceiptController {
    public static function handle(string $method): never {
        header('Allow: POST');header('X-Robots-Tag: noindex, nofollow');
        if($method!=='POST')throw new Problem(405,'method','Metodă nepermisă.');
        if(\App\Core\Api::bearer()===null)Http::sameOrigin();Auth::rate('receipt-preview|'.\App\Core\Api::clientIp(),120,3600);
        if(!Migrations::integrations())throw new Problem(503,'receipt_setup','Bonurile digitale nu sunt disponibile momentan.');
        $d=Http::input(2000);$token=$d['token']??null;if(!is_string($token))throw new Problem(404,'receipt','Bonul nu este disponibil.');Http::json(DigitalReceipt::preview($token));
    }
}
