<?php
declare(strict_types=1);
namespace App\Core;
final class Http {
    public static function json(mixed $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');$body=Db::json($data);if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')header('Content-Length: '.strlen($body));else echo $body;exit;}
    public static function redirect(string $path): never {header('Location: '.$path,true,303);exit;}
    public static function view(string $view,array $data=[],int $status=200): void {
        http_response_code($status);extract($data,EXTR_SKIP);ob_start();require ROOT.'/app/Views/'.$view.'.php';$content=ob_get_clean();require ROOT.'/app/Views/layout.php';
    }
    public static function input(int $max=2000000,array $allowed=['application/json','application/x-www-form-urlencoded','multipart/form-data']): array {
        if((int)($_SERVER['CONTENT_LENGTH']??0)>$max)throw new Problem(413,'body_size','Cererea este prea mare.');
        $type=strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]));
        if($type===''&&(int)($_SERVER['CONTENT_LENGTH']??0)===0&&!isset($_SERVER['HTTP_TRANSFER_ENCODING']))return [];
        if(!in_array($type,$allowed,true)||!in_array(strtolower($_SERVER['HTTP_CONTENT_ENCODING']??'identity'),['','identity'],true))throw new Problem(415,'media_type','Formatul cererii nu este acceptat.');
        if($type==='application/json'){$raw=file_get_contents('php://input',false,null,0,$max+1);if(strlen($raw)>$max)throw new Problem(413,'body_size','Cererea este prea mare.');try{$d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(400,'json','JSON incorect.');}if(!is_array($d)||!str_starts_with(ltrim($raw),'{'))throw new Problem(400,'json','Este necesar un obiect JSON.');return $d;}
        return $_POST;
    }
    public static function sameOrigin(): void {
        $origin=$_SERVER['HTTP_ORIGIN']??null;
        if($origin!==null&&rtrim($origin,'/')!==rtrim(Config::get('url'),'/'))throw new Problem(403,'origin','Originea cererii nu este permisă.');
        if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')throw new Problem(403,'origin','Cerere externă respinsă.');
    }
}
