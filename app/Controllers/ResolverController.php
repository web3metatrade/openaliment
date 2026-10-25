<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Config,Db,Http,Problem,Migrations};
use App\Domain\{DemoBread,Resolver,ResolverRegistry,ResolverUri};

final class ResolverController {
    public static function matches(string $path): bool {
        return preg_match('~\A/(?:[0-9]{2,4}(?:/|$)|eh|ex)~',$path)===1 || in_array($path,['/.well-known/gs1resolver','/resolver/linkset-context','/resolver/profile','/resolver/contexts'],true);
    }
    public static function handle(string $method,string $path): never {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Accept, Accept-Language, Content-Type');
        header('Access-Control-Expose-Headers: Location, Link, Content-Type, X-OpenAliment-Resolver');
        header('Vary: Accept, Accept-Language');
        header('X-OpenAliment-Resolver: '.Resolver::VERSION);
        header('Cache-Control: no-store, max-age=0');
        if ($method==='OPTIONS') { header('Allow: GET, HEAD, OPTIONS'); http_response_code(204); exit; }
        if (!in_array($method,['GET','HEAD'],true)) { header('Allow: GET, HEAD, OPTIONS'); self::json(['error_code'=>'method','message'=>'Metodă nepermisă.'],405); }
        try {
            \App\Core\Api::limit('resolver');
            if (!Migrations::resolver()) throw new Problem(503,'resolver_update','Serviciul resolver nu este disponibil.');
            if ($path==='/.well-known/gs1resolver') self::json(self::description());
            if ($path==='/resolver/linkset-context') {
                self::send(file_get_contents(ROOT.'/contracts/gs1/linkset-context.json'),'application/ld+json');
            }
            if ($path==='/resolver/contexts') {
                $contexts=[];
                foreach (Db::all("SELECT target_json FROM resolver_links WHERE is_current=1 AND state='published'") as $row) {
                    foreach (json_decode($row['target_json'],true)['context']??[] as $value) $contexts[$value]=true;
                }
                self::json(['contexts'=>array_keys($contexts)]);
            }
            if ($path==='/resolver/profile') {
                Http::view('resolver-profile',['title'=>'Resolver GS1 Digital Link','description'=>'Funcțiile și profilul de identificatori ale resolverului OpenAliment.']); exit;
            }
            if (!str_starts_with($path,'/01/')&&!str_starts_with($path,'/eh')&&!str_starts_with($path,'/ex')) throw new Problem(404,'unsupported_key','Cheia primară nu este administrată de acest resolver.');
            $uri=ResolverUri::parse($path,$_SERVER['QUERY_STRING']??'');
            $demo=DemoBread::links($uri);
            if ($demo!==null) { header('X-OpenAliment-Demo: true'); header('X-Robots-Tag: noindex, nofollow'); }
            $set=$demo??Resolver::links($uri); $accept=Resolver::preferences($_SERVER['HTTP_ACCEPT']??'');
            $linkset=in_array($uri['parameters']['linkType']??null,['linkset','all'],true)||($accept['application/linkset+json']??0)>0;
            if ($linkset) self::linkset($uri,$set['links'],$set['description'],200);
            $choice=Resolver::choose($uri,$set,$_SERVER['HTTP_ACCEPT']??'',$_SERVER['HTTP_ACCEPT_LANGUAGE']??'');
            if (isset($choice['link'])) {
                header('Location: '.Resolver::location($choice['link']['href'],$uri['query']),true,$choice['status']);
                self::send('','text/plain',$choice['status']);
            }
            self::linkset($uri,$choice['choices'],$set['description'],$choice['status']);
        } catch (Problem $error) { self::json(['error_code'=>$error->errorCode,'message'=>$error->getMessage()],$error->status); }
        catch (\Throwable) { self::json(['error_code'=>'service_unavailable','message'=>'Serviciul este temporar indisponibil.'],503); }
    }
    public static function description(): array {
        return ['resolverRoot'=>rtrim(Config::get('url'),'/'),'supportedPrimaryKeys'=>['01'],'name'=>'OpenAliment GS1 Digital Link Resolver',
            'supportedLinkType'=>[['namespace'=>ResolverRegistry::NS,'prefix'=>'gs1:']],
            'linkTypeDefaultCanBeLinkset'=>false,
            'supportedContextValuesExternal'=>[['nameOfList'=>'Contexte publicate de producători','url'=>url('/resolver/contexts')]],
            'jsonLdContextLocation'=>url('/resolver/linkset-context'),'extensionProfile'=>url('/resolver/profile')];
    }
    private static function linkset(array $uri,array $links,string $description,int $status): never {
        $body=Resolver::linkset($uri,$links,$description); $preferences=Resolver::preferences($_SERVER['HTTP_ACCEPT']??'');
        header('Link: <'.url('/resolver/linkset-context').'>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"');
        $formats=['application/linkset+json','application/ld+json','application/json','text/html'];$format='text/html';$quality=0.0;
        foreach($formats as $candidate)if(($preferences[$candidate]??0)>$quality){$quality=$preferences[$candidate];$format=$candidate;}
        if ($format==='application/linkset+json') self::json($body,$status,$format);
        if ($format==='application/ld+json') self::json(['@context'=>url('/resolver/linkset-context')]+$body,$status,$format);
        if ($format==='application/json') self::json($body,$status);
        $title=$status===404?'Tipul de informație nu este disponibil':($status===300?'Alege informația dorită':'Informații disponibile');
        $json=json_encode(['@context'=>url('/resolver/linkset-context')]+$body,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        ob_start(); Http::view('resolver-linkset',['title'=>$title,'links'=>$links,'anchor'=>ResolverUri::anchor($uri),'linksetJson'=>$json],$status); $html=ob_get_clean();
        self::send($html,'text/html',$status);
    }
    private static function json(array $value,int $status=200,string $type='application/json'): never { self::send(Db::json($value),$type,$status); }
    private static function send(string $body,string $type,int $status=200): never {
        http_response_code($status); header('Content-Type: '.$type.'; charset=utf-8'); header('Content-Length: '.strlen($body));
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD') echo $body;
        exit;
    }
}
