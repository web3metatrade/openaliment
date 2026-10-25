<?php
declare(strict_types=1);
namespace App\Domain;

/** Read-only demonstration, outside tenant data and the commercial GTIN registry. */
final class DemoBread {
    public const GTIN13='9520000000011';
    public const GTIN14='09520000000011';
    public const LOT='DEMO-PBT-01';
    public const PAGE='/demo/paine-buna-tehnic';
    public static function assertCommercial(?string $gtin): void {
        if ($gtin===self::GTIN14) throw new \App\Core\Problem(422,'demo_identifier','Acest GTIN este rezervat exemplului demonstrativ OpenAliment și nu poate fi înregistrat ca articol comercial.');
    }
    public static function address(bool $batch=false): string {
        return url(DigitalLink::path(self::GTIN14,$batch?self::LOT:null));
    }
    public static function asset(bool $batch=false): string {
        return '/assets/demo/pbt-'.($batch?'lot':'produs').'-qr.svg';
    }
    public static function page(bool $batch=false): string {
        return self::PAGE.($batch?'/lot/'.self::LOT:'');
    }
    public static function snapshot(bool $batch=false): array {
        return ['simulation'=>true,'commercial_use'=>false,'name'=>'Pâine Bună Tehnic',
            'category'=>'bread','gtin13'=>self::GTIN13,'gtin14'=>self::GTIN14,
            'identifier_status'=>'demonstration_only','lot_code'=>$batch?self::LOT:null,
            'information_version'=>'DEMO-E1','technical_status'=>'experimental',
            'recipe_reference'=>'R1','recipe_published'=>false,
            'digital_link'=>self::address($batch),
            'notice'=>'Identificator și lot simulate. Nu identifică un articol pentru vânzare. Nu reprezintă rezultate de fabricație validate.'];
    }
    public static function links(array $uri): ?array {
        if ($uri['gtin']!==self::GTIN14) return null;
        $lot=$uri['qualifiers']['10']??null;
        $batch=$lot===self::LOT;
        $page=self::page($batch).($lot!==null&&!$batch?'/lot-necunoscut':'');
        $native=['href'=>url($page),'title'=>'DEMO · Pâine Bună Tehnic'.($batch?' · lot '.self::LOT:''),
            'types'=>[ResolverRegistry::NS.'pip',ResolverRegistry::NS.'defaultLink'],
            '_rank'=>$batch?20:0,'_id'=>'demo-page'];
        $data=['href'=>url(self::page($batch).'/date.json'),'title'=>'Date simulate în format JSON',
            'types'=>[ResolverRegistry::NS.'masterData'],'type'=>'application/json',
            '_rank'=>$batch?20:0,'_id'=>'demo-json'];
        $consumer=['href'=>url('/api/v2/public/food').'?'.http_build_query(['code'=>url($uri['path']).($uri['query']!==''?'?'.$uri['query']:'')],'','&',PHP_QUERY_RFC3986),'title'=>'Date demonstrative pentru aplicații','types'=>[ResolverRegistry::NS.'masterData'],'type'=>'application/json','context'=>[url('/api/schemas/consumer-product-profile.schema.json')],'_rank'=>($batch?20:0)-1,'_id'=>'demo-consumer'];
        return ['links'=>[$native,$data,$consumer],'default'=>$native,'alert'=>false,'safe_default'=>true,
            'description'=>'Demonstrație cu identificator GS1 952 · Pâine Bună Tehnic'];
    }
}
