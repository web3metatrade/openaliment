<?php
declare(strict_types=1);
namespace App\Core;

final class Seo {
    public static function metadata(?string $title=null,?string $description=null,?array $product=null): array {
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
        $pages=[
            '/'=>['Alternativă GS1 Digital Link, QR și API | OpenAliment','Resolver GS1 Digital Link, pagini de produs și lot, QR-uri și integrare ERP prin API. Găzduire independentă pentru producători alimentari.'],
            '/despre'=>['Despre platforma OpenAliment','Pagini de produs și lot, resolver GS1 Digital Link și documentație alimentară deschisă. Află cum funcționează OpenAliment.'],
            '/gs1-digital-link'=>['GS1 Digital Link explicat: GTIN, QR și lot','Ghid GS1 Digital Link pentru alimente: GTIN, EAN și QR, exemple de produs și lot, resolver, integrare ERP și tranziția Sunrise 2027.'],
            '/contribuitori'=>['Contribuitori OpenAliment','Oamenii care contribuie voluntar la codul, documentația, cercetarea și dezvoltarea OpenAliment.'],
            '/parteneri'=>['Parteneri autorizați OpenAliment','Companii verificate în programul OpenAliment pentru integrare ERP, tipărire QR și date de produs.'],
            '/invatare'=>['Cursuri GS1 Digital Link și integrare OpenAliment','Lecții despre GTIN, URI, resolver, API și tipărire, cu exerciții și evaluare pentru parteneri.'],
            '/marketplace'=>['Marketplace: conectori ERP, imprimante și integrări','Descoperă integrări OpenAliment: conectori ERP, agenți pentru echipamente de etichetare și instrumente ale comunității.'],
            '/standarde/gs1'=>['Standarde GS1: Digital Link, resolver și coduri QR','Edițiile oficiale GS1 folosite de OpenAliment, sursele, proveniența și legătura cu tipărirea în linia de producție.'],
            '/produse'=>['Produse alimentare și informații publice','Descoperă alimentele publicate pe OpenAliment: ingrediente, alergeni, producători și informații despre loturi.'],
            '/api/docs'=>['Documentație API, ERP și aplicații','Automatizează produsele, etichetele, loturile, adresele QR și destinațiile resolverului GS1 Digital Link prin API-ul OpenAliment.'],
            '/resolver/profile'=>['Resolver GS1 Digital Link: profil și funcții','Profilul GTIN al resolverului OpenAliment: selecția destinațiilor, linkset, negociere, EPC și administrarea legăturilor prin API.'],
            '/confidentialitate'=>['Confidențialitate','Informații despre prelucrarea datelor și contactul operatorului OpenAliment.'],
        ];
        $known=isset($pages[$path]);
        $publicProduct=$product!==null&&preg_match('~\A/(?:p|t|b)/[a-f0-9]{32}\z~D',$path);
        $publicRecipe=preg_match('~\A/p/[a-f0-9]{32}/v/[^/]+\z~D',$path);
        $publicDocs=preg_match('~\A/api/docs/(?:guides|reference)/[A-Za-z0-9_-]+\z~D',$path)===1;
        $publicProgram=preg_match('~\A/(?:contribuitori/[a-f0-9]{32}|parteneri/[a-f0-9]{32}|invatare/cursuri/[a-f0-9]{32}|invatare/editii/[a-f0-9]{32}/lectii/[a-f0-9]{32})\z~D',$path);
        $public=($publicProgram||$known||$publicProduct||$publicRecipe||$publicDocs||preg_match('~\A/marketplace/[a-z0-9-]+\z~D',$path))&&http_response_code()<400;
        $indexable=$public&&Config::get('environment')==='production';
        if (!$indexable) header('X-Robots-Tag: noindex, nofollow');
        $pageTitle=$pages[$path][0]??($title?:'OpenAliment');
        if (!str_contains($pageTitle,'OpenAliment')) $pageTitle.=' | OpenAliment';
        $desc=$description??($pages[$path][1]??'Informații publicate de producători despre alimentele lor, pe OpenAliment.');
        if ($publicProduct) $desc=mb_substr(($product['label']['name']??'Produs alimentar').' · '.($product['label']['net_quantity']??'').' · Ingrediente, alergeni și informații aprobate de producător.'.(isset($product['batch'])?' Lot '.$product['batch']['lot_code'].'.':''),0,250);
        $base=rtrim((string)Config::get('url'),'/');
        $graph=[];
        if ($path==='/') {
            $graph=[
                ['@type'=>'Organization','@id'=>$base.'/#organization','name'=>'OpenAliment','url'=>$base.'/','logo'=>$base.'/assets/mark.svg'],
                ['@type'=>'WebSite','@id'=>$base.'/#website','name'=>'OpenAliment','url'=>$base.'/','inLanguage'=>'ro-RO','publisher'=>['@id'=>$base.'/#organization']],
                ['@type'=>'Service','@id'=>$base.'/#service','name'=>'Găzduire și resolver GS1 Digital Link pentru alimente','serviceType'=>'Pagini digitale de produs și lot, resolver GTIN și integrare ERP prin API','provider'=>['@id'=>$base.'/#organization'],'url'=>$base.'/','description'=>$desc],
            ];
        }
        if ($path==='/gs1-digital-link') $graph=[
            ['@type'=>'WebPage','@id'=>$base.$path.'#page','name'=>$pageTitle,'description'=>$desc,'url'=>$base.$path,'inLanguage'=>'ro-RO','about'=>['@type'=>'Thing','name'=>'GS1 Digital Link'],'breadcrumb'=>['@id'=>$base.$path.'#breadcrumb']],
            ['@type'=>'BreadcrumbList','@id'=>$base.$path.'#breadcrumb','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'OpenAliment','item'=>$base.'/'],['@type'=>'ListItem','position'=>2,'name'=>'GS1 Digital Link','item'=>$base.$path]]],
        ];
        return ['title'=>$pageTitle,'description'=>$desc,'canonical'=>$base.$path,'image'=>$base.'/assets/openaliment-social.jpg','indexable'=>$indexable,'structured'=>$graph?['@context'=>'https://schema.org','@graph'=>$graph]:null];
    }
    public static function robots(): string {
        if (Config::get('environment')!=='production') return "User-agent: *\nDisallow: /\n";
        return "User-agent: *\nDisallow: /manage\nDisallow: /admin\nDisallow: /setup\nDisallow: /api/v1/manage\nDisallow: /openaliment-connect.php\nSitemap: ".rtrim(Config::get('url'),'/')."/sitemap.xml\n";
    }
    public static function sitemap(): string {
        $paths=['/','/standarde/gs1','/gs1-digital-link','/despre','/produse','/api/docs','/resolver/profile'];
        if (Config::get('privacy_text')) $paths[]='/confidentialitate';
        try { if(Migrations::marketplace()) $paths[]='/marketplace'; } catch(\Throwable) {}
        try { if(Migrations::program()) $paths=array_merge($paths,['/contribuitori','/parteneri','/invatare']); } catch(\Throwable) {}
        $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($paths as $path) $xml.='<url><loc>'.htmlspecialchars(rtrim(Config::get('url'),'/').$path,ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>';
        return $xml.'</urlset>';
    }
}
