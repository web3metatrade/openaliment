<?php
declare(strict_types=1);
namespace App\Core;

/** Shared HTTP policy; authentication and domain authorization remain separate. */
final class Api {
    public static function bearer(): ?string {
        $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??null;
        if($header===null||$header==='')return null;
        if(!is_string($header)||!preg_match('/\ABearer ([A-Za-z0-9_-]{20,180})\z/iD',$header,$m)) {
            header('WWW-Authenticate: Bearer realm="OpenAliment", error="invalid_token"');
            throw new Problem(401,'invalid_token','Antetul de autentificare nu este valid.');
        }
        return $m[1];
    }
    public static function id(string $value): string {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$value))throw new Problem(422,'id','Identificator incorect.');return $value;
    }
    public static function installed(): void {if(!Migrations::api())throw new Problem(503,'api_setup','Actualizarea API este în curs.');}
    public static function method(string $method,array $allowed): void {
        header('Allow: '.implode(', ',$allowed));
        if(!in_array($method,$allowed,true))throw new Problem(405,'method','Metodă nepermisă.');
    }
    public static function publicCors(): void {
        header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Expose-Headers: X-Request-ID, RateLimit-Limit, RateLimit-Remaining, RateLimit-Reset, Retry-After, Link');
    }
    public static function limit(string $class='public',int $cost=1,?string $identity=null): void {
        $limits=['public'=>[240,60],'resolver'=>[600,60],'catalog'=>[60,60],'tools'=>[30,60],'docs'=>[120,60],'private-read'=>[240,60],'private-write'=>[120,60],'auth'=>[30,60],'download'=>[30,60]];
        [$limit,$seconds]=$limits[$class]??$limits['public'];
        $overrides=Config::get('api_limits',[]);
        if(is_array($overrides)&&isset($overrides[$class])&&is_int($overrides[$class]))$limit=max(1,min(10000,$overrides[$class]));
        RateLimit::check($class.'|'.($identity??self::clientIp()),$limit,$seconds,$cost);
    }
    public static function clientIp(): string {
        $remote=$_SERVER['REMOTE_ADDR']??'unknown';$trusted=Config::get('trusted_proxy_cidrs',[]);
        $contains=static function(string $ip)use($trusted):bool{
            $bytes=@inet_pton($ip);if($bytes===false)return false;
            foreach($trusted as$range){if(!is_string($range))continue;[$network,$prefix]=array_pad(explode('/',$range,2),2,null);$net=@inet_pton($network);if($net===false||strlen($net)!==strlen($bytes))continue;
                $bits=$prefix===null?strlen($net)*8:(ctype_digit($prefix)?(int)$prefix:-1);if($bits<0||$bits>strlen($net)*8)continue;
                $full=intdiv($bits,8);$rest=$bits%8;if(substr($net,0,$full)===substr($bytes,0,$full)&&(!$rest||((ord($net[$full])^ord($bytes[$full]))&(255<<(8-$rest)))===0))return true;
            }return false;
        };
        if(!$contains($remote))return $remote;
        $forwarded=$_SERVER['HTTP_X_FORWARDED_FOR']??'';if(!is_string($forwarded)||strlen($forwarded)>512)return $remote;
        $chain=array_map('trim',explode(',',$forwarded));if(count($chain)>10)return $remote;
        $current=$remote;foreach(array_reverse($chain)as$ip){if(!$contains($current)||filter_var($ip,FILTER_VALIDATE_IP)===false)break;$current=$ip;}return $current;
    }
    public static function write(array $principal): void {
        if(!($principal['token']??null)){Http::sameOrigin();Session::verify();}
    }
    public static function page(): array {
        $limit=$_GET['limit']??'20';$cursor=$_GET['cursor']??'';
        if(!is_string($limit)||!ctype_digit($limit)||(int)$limit<1||(int)$limit>100)throw new Problem(422,'pagination','Limit trebuie să fie între 1 și 100.');
        if(!is_string($cursor)||($cursor!==''&&!preg_match('/\A[a-f0-9]{32}\z/D',$cursor)))throw new Problem(422,'cursor','Cursor incorect.');
        return [(int)$limit,$cursor];
    }
    public static function collection(array $items,int $limit,?callable $project=null): array {
        $more=count($items)>$limit;$items=array_slice($items,0,$limit);$last=$items?end($items):null;
        return ['items'=>$project?array_map($project,$items):$items,'next_cursor'=>$more?$last['id']:null,'has_more'=>$more,'limit'=>$limit];
    }
    public static function mutation(array $p,array $data,callable $run,int $status=201): never {
        self::write($p);self::limit('private-write',1,$p['token']??$p['user']);
        $hash=hash('sha256',($_SERVER['REQUEST_METHOD']??'POST').'|'.($_SERVER['REQUEST_URI']??'').'|'.Db::json($data));
        Http::json(\App\Domain\Mutations::idempotent($p,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',$hash,$run),$status);
    }
}
