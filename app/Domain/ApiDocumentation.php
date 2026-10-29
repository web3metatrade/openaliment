<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Problem};

/** Public documentation only: no account, key, product or household data is loaded. */
final class ApiDocumentation {
    private static ?array $catalog=null;
    public static function catalog(): array {
        if(self::$catalog!==null)return self::$catalog;
        $file=ROOT.'/contracts/api-documentation.json';
        if(!is_file($file))throw new Problem(503,'documentation_unavailable','Documentația nu este disponibilă temporar.');
        return self::$catalog=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
    }
    public static function page(string $path): array {
        $plain=preg_replace('/\.md\z/','',$path);
        $id=$plain==='/api/docs'?'overview':basename($plain);
        $page=self::catalog()['pages'][$id]??null;
        if(!$page||$page['path']!==$plain)throw new Problem(404,'not_found','Pagina de documentație nu a fost găsită.');
        if(!isset($page['markdown'])){
            $group=$page['group']??'guides';
            if(!preg_match('/\A[a-z-]+\z/D',$group))throw new Problem(503,'documentation_unavailable','Documentația nu este disponibilă temporar. Încearcă din nou mai târziu.');
            $file=ROOT.'/contracts/api-docs/'.$group.'.json';
            if(!is_file($file))throw new Problem(503,'documentation_unavailable','Documentația nu este disponibilă temporar. Încearcă din nou mai târziu.');
            $part=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
            $page=$part[$id]??throw new Problem(503,'documentation_unavailable','Documentația nu este disponibilă temporar. Încearcă din nou mai târziu.');
        }
        return $page;
    }
    public static function operation(array $page): array {
        $spec=json_decode(file_get_contents(ROOT.'/contracts/platform-openapi.json'),true,64,JSON_THROW_ON_ERROR);
        $operation=$spec['paths'][$page['endpoint']][strtolower($page['method'])];
        foreach($operation['responses']as$status=>$response){
            if(!isset($response['$ref']))continue;
            if(!preg_match('~\A#/components/responses/([A-Za-z0-9_-]+)\z~D',$response['$ref'],$match)||!isset($spec['components']['responses'][$match[1]]))throw new Problem(503,'documentation_unavailable','Descrierea răspunsului nu este disponibilă temporar.');
            $operation['responses'][$status]=array_replace($spec['components']['responses'][$match[1]],array_diff_key($response,['$ref'=>true]));
        }
        return ['operation'=>$operation,'schemas'=>$spec['components']['schemas']];
    }
    public static function schema(array $schema,array $schemas): array {
        $ref=$schema['$ref']??'';return str_starts_with($ref,'#/components/schemas/')?($schemas[basename($ref)]??$schema):$schema;
    }
    public static function markdown(array $page): string {
        $text=($page['markdown']??null)??self::page($page['path'])['markdown'];
        foreach(['../../contracts/platform-openapi.json'=>'/api/platform-openapi.json','../../tools/sdk/openaliment.py'=>'/api/sdk/python','../../tools/sdk/openaliment.mjs'=>'/api/sdk/javascript','../../tools/sdk/OpenAlimentClient.kt'=>'/api/sdk/kotlin','../INTEGRARI_PARTENERI.md'=>'/api/docs/guides/conectoare','../IMPORT_EXPORT.md'=>'/api/docs/guides/conectoare']as$old=>$new)$text=str_replace(']('.$old.')',']('.$new.')',$text);
        $text=preg_replace_callback('~\]\((/(?!/)[^\s)]+)\)~',static fn($m)=>']('.url($m[1]).')',$text);
        return $text."\nSursă: ".url($page['path'])."\nVersiune Markdown: ".url($page['path'].'.md')."\n";
    }
    public static function llms(bool $full=false): string {
        $catalog=self::catalog();$out="# OpenAliment API\n\nDocumentație publică pentru integrări ERP, aplicații și agenți AI.\nCitește manifestul pentru capabilitățile disponibile; documentația nu acordă acces la date private.\n\n";
        $out.='- Manifest: '.url('/api/manifest.json')."\n- OpenAPI: ".url('/api/platform-openapi.json')."\n- Index JSON: ".url('/api/docs/index.json')."\n\n";
        foreach($catalog['navigation']as$page){
            if($full)$out.="\n---\n\n".self::markdown($catalog['pages'][$page['id']]);
            else $out.='- ['.$page['title'].']('.url($page['path'].'.md').')'.(isset($page['method'])?' — '.$page['method'].' '.$page['endpoint']:'')."\n";
        }return $out;
    }
}
