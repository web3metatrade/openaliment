<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,Http};
use App\Domain\ApiDocumentation;

final class ApiDocumentationController {
    public static function matches(string $path): bool {
        return in_array($path,['/api/docs','/api/docs/','/api/docs.md','/api/docs/index.json','/api/docs/postman.json','/api/docs/examples/erp-workflow.py','/llms.txt','/llms-full.txt'],true)||preg_match('~\A/api/docs/(?:guides|reference)/[A-Za-z0-9_-]+(?:\.md)?\z~D',$path)===1;
    }
    public static function handle(string $method,string $path): void {
        Api::method($method,['GET','HEAD','OPTIONS']);Api::publicCors();
        if($method==='OPTIONS'){http_response_code(204);return;}
        if(!str_starts_with($path,'/api/'))Api::limit('docs',$path==='/llms-full.txt'?24:1);
        if($path==='/api/docs/'){header('Location: '.url('/api/docs'),true,301);return;}
        if($path==='/llms.txt'||$path==='/llms-full.txt'){
            header('Content-Type: text/plain; charset=utf-8');if($method!=='HEAD')echo ApiDocumentation::llms($path==='/llms-full.txt');return;
        }
        $catalog=ApiDocumentation::catalog();
        if($path==='/api/docs/postman.json'){
            header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="openaliment.postman_collection.json"');
            if($method!=='HEAD'){
                $collection=json_decode(file_get_contents(ROOT.'/contracts/api-postman-collection.json'),true,64,JSON_THROW_ON_ERROR);
                $parts=$collection['x-item-files']??[];unset($collection['x-item-files'],$collection['item']);
                echo substr(json_encode($collection,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),0,-1).',"item":[';
                foreach($parts as$i=>$part){if(!preg_match('/\Apostman-[0-9]{2}\.json\z/D',$part))throw new \RuntimeException('Invalid collection part');if($i)echo ',';readfile(ROOT.'/contracts/api-docs/'.$part);}
                echo ']}';
            }return;
        }
        if($path==='/api/docs/examples/erp-workflow.py'){
            header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="erp-workflow.py"');
            if($method!=='HEAD')echo $catalog['downloads']['erp_workflow'];return;
        }
        if($path==='/api/docs/index.json'){
            Http::json(['schema_version'=>$catalog['schema_version'],'documentation'=>url('/api/docs'),'llms'=>url('/llms.txt'),'operation_count'=>$catalog['operation_count'],'guide_count'=>$catalog['guides'],'pages'=>array_map(static fn($p)=>['title'=>$p['title'],'url'=>url($p['path']),'markdown_url'=>url($p['path'].'.md'),'kind'=>$p['kind'],'operation_id'=>$p['kind']==='operation'?$p['id']:null],$catalog['navigation'])]);
        }
        $page=ApiDocumentation::page($path);$markdown=url($page['path'].'.md');
        header('Link: <'.$markdown.'>; rel="alternate"; type="text/markdown"');header('Vary: Accept',false);
        if(str_ends_with($path,'.md')||self::wantsMarkdown()){
            header('Content-Type: text/markdown; charset=utf-8');if($method!=='HEAD')echo ApiDocumentation::markdown($page);return;
        }
        header('Content-Type: text/html; charset=utf-8');
        $context=$page['kind']==='operation'?ApiDocumentation::operation($page):[];
        $prompt='Citește documentația publică OpenAliment de la '.$markdown.' și ajută-mă să folosesc această parte a API-ului. Verifică permisiunile și limitele documentate. Nu solicita parole, chei API sau cookie-uri.';
        Http::view('api-docs',['title'=>$page['title'].' — Documentație API','description'=>'Documentație OpenAliment API: '.$page['title'].'. Parametri, permisiuni, exemple și acces Markdown.','documentation'=>$catalog,'docPage'=>$page,'docContext'=>$context,'chatgptUrl'=>'https://chatgpt.com/?q='.rawurlencode($prompt),'claudeUrl'=>'https://claude.ai/new?q='.rawurlencode($prompt)]);
    }
    private static function wantsMarkdown(): bool {
        $scores=['markdown'=>0.0,'html'=>0.0];
        foreach(explode(',',$_SERVER['HTTP_ACCEPT']??'text/html')as$entry){
            $parts=array_map('trim',explode(';',$entry));$type=strtolower(array_shift($parts));$q=1.0;
            foreach($parts as$part)if(preg_match('/\Aq=([01](?:\.\d{1,3})?)\z/',$part,$match))$q=(float)$match[1];
            if($type==='text/markdown')$scores['markdown']=max($scores['markdown'],$q);
            if(in_array($type,['text/html','application/xhtml+xml','*/*'],true))$scores['html']=max($scores['html'],$q);
        }return $scores['markdown']>0&&$scores['markdown']>=$scores['html'];
    }
}
