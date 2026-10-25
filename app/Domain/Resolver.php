<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Problem,Migrations};

final class Resolver {
    public const VERSION='1.2.1';
    public static function preferences(string $header): array {
        $out=[];
        foreach (explode(',',$header) as $entry) {
            $parts=explode(';',trim($entry)); $name=strtolower(trim(array_shift($parts))); $q=1.0;
            if ($name==='') continue;
            foreach ($parts as $part) if (preg_match('/\A\s*q\s*=\s*(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\s*\z/i',$part,$m)) $q=(float)$m[1];
            $out[$name]=$q;
        }
        return $out;
    }
    private static function specificity(array $q): int {
        if (isset($q['235'])) return 50;
        if (isset($q['21'])) return 40;
        return (isset($q['22'])?10:0)+(isset($q['10'])?20:0);
    }
    public static function links(array $uri): array {
        if (!Migrations::resolver()) throw new Problem(503,'resolver_update','Serviciul necesită actualizarea schemei.');
        $item=Db::one("SELECT t.id,t.tenant_id,t.product_id,t.gtin_status,t.verified_at,t.current_info_id,v.snapshot_json FROM gtin_registry g JOIN trade_items t ON t.id=g.trade_item_id AND t.tenant_id=g.tenant_id LEFT JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id AND v.state='published' WHERE g.gtin14=?",[$uri['gtin']]);
        if (!$item) throw new Problem(404,'not_found','Identificatorul nu este înregistrat.');
        $rows=Db::all("SELECT id,qualifiers_json,target_json,is_default FROM resolver_links WHERE tenant_id=? AND trade_item_id=? AND is_current=1 AND state='published' ORDER BY id",[$item['tenant_id'],$item['id']]);
        $hashes=array_fill_keys(array_map(ResolverUri::scopeHash(...),ResolverUri::scopes($uri['qualifiers'])),true); $links=[];
        foreach ($rows as $row) {
            $scope=json_decode($row['qualifiers_json'],true,16,JSON_THROW_ON_ERROR);
            if (!isset($hashes[ResolverUri::scopeHash($scope)])) continue;
            $target=json_decode($row['target_json'],true,16,JSON_THROW_ON_ERROR);
            $target['_rank']=self::specificity($scope); $target['_custom']=1; $target['_id']=$row['id']; $links[]=$target;
        }
        $label=$item['snapshot_json']?json_decode($item['snapshot_json'],true,32,JSON_THROW_ON_ERROR):null;
        $batch=null; $alert=false; $page='/t/'.$item['id']; $nativeRank=0;
        if (isset($uri['qualifiers']['10'])) {
            $batch=Db::one("SELECT b.id,b.snapshot_json,b.product_info_version_id,v.snapshot_json AS label_json FROM batches b JOIN product_info_versions v ON v.id=b.product_info_version_id AND v.tenant_id=b.tenant_id AND v.state='published' WHERE b.trade_item_id=? AND b.tenant_id=? AND BINARY b.lot_code=BINARY ? AND b.visibility='public'",[$item['id'],$item['tenant_id'],$uri['qualifiers']['10']]);
            if ($batch) {
                $label=json_decode($batch['label_json'],true,32,JSON_THROW_ON_ERROR); $page='/b/'.$batch['id']; $nativeRank=20;
                $alert=Db::one('SELECT id FROM alerts WHERE batch_id=? AND tenant_id=? AND resolved_at IS NULL LIMIT 1',[$batch['id'],$item['tenant_id']])!==null;
            } else $page.='?requested_lot='.rawurlencode($uri['qualifiers']['10']);
        }
        if ($label) {
            $types=[ResolverRegistry::NS.'pip',ResolverRegistry::NS.'ingredientsInfo',ResolverRegistry::NS.'allergenInfo',ResolverRegistry::NS.'defaultLink'];
            if (!empty($label['nutrition'])) $types[]=ResolverRegistry::NS.'nutritionalInfo';
            $links[]=['href'=>url($page),'title'=>($batch?'Informații despre lotul '.$uri['qualifiers']['10']:'Informații despre produs').' · '.$label['name'],'types'=>$types,'_rank'=>$nativeRank,'_custom'=>0,'_id'=>'native'];
            $links[]=['href'=>url('/api/v1/'.($batch?'batches/'.$batch['id']:'trade-items/'.$item['id'])),'title'=>'Informații publice în format JSON','types'=>[ResolverRegistry::NS.'masterData'],'type'=>'application/json','_rank'=>$nativeRank,'_custom'=>0,'_id'=>'api'];
            if(isset($label['food_data'])){
                $scan=url(ResolverUri::path($uri['gtin'],$uri['qualifiers'])).($uri['query']!==''?'?'.$uri['query']:'');
                $links[]=['href'=>url('/api/v2/public/food').'?'.http_build_query(['code'=>$scan],'','&',PHP_QUERY_RFC3986),'title'=>'Date alimentare pentru aplicații','types'=>[ResolverRegistry::NS.'masterData'],'type'=>'application/json','context'=>[url('/api/schemas/consumer-product-profile.schema.json')],'_rank'=>$nativeRank-1,'_custom'=>0,'_id'=>'consumer-profile'];
            }
        }
        if (!$links) throw new Problem(404,'not_found','Nu există informații publicate pentru acest identificator.');
        $defaults=array_filter($links,static fn($link)=>in_array(ResolverRegistry::NS.'defaultLink',$link['types'],true));
        if (!$defaults) throw new Problem(503,'default_missing','Destinația implicită nu este disponibilă.');
        usort($defaults,static fn($a,$b)=>($b['_rank']<=>$a['_rank'])?:($b['_custom']<=>$a['_custom'])?:strcmp($a['_id'],$b['_id']));
        $chosen=$defaults[0];
        if (($alert||$item['gtin_status']!=='verified')&&$label) foreach ($links as $link) if ($link['_id']==='native') $chosen=$link;
        foreach ($links as &$link) {
            if ($link['_id']!==$chosen['_id']) $link['types']=array_values(array_diff($link['types'],[ResolverRegistry::NS.'defaultLink']));
        } unset($link);
        return ['links'=>$links,'default'=>$chosen,'alert'=>$alert,'safe_default'=>$alert||$item['gtin_status']!=='verified','description'=>$label['name']??'Informații asociate GTIN'];
    }
    public static function linkset(array $uri,array $links,string $description): array {
        $context=['anchor'=>ResolverUri::anchor($uri),'description'=>$description];
        foreach ($links as $link) {
            $entry=array_intersect_key($link,array_flip(['href','title','hreflang','type','context']));
            foreach ($link['types'] as $type) {
                if (!isset($context[$type])) $context[$type]=[];
                if (!in_array($entry,$context[$type],true)) $context[$type][]=$entry;
            }
        }
        return ['linkset'=>[$context]];
    }
    private static function mediaScore(string $media,array $preferences): float {
        $top=explode('/',$media)[0];
        return $preferences[$media]??$preferences[$top.'/*']??$preferences['*/*']??0.0;
    }
    private static function languageScore(array $languages,array $preferences): float {
        $score=0.0;
        foreach ($languages as $language) {
            $tag=strtolower($language);
            foreach ($preferences as $wanted=>$q) if ($wanted==='*'||$wanted===$tag||str_starts_with($tag,$wanted.'-')||str_starts_with($wanted,$tag.'-')) $score=max($score,$q);
        }
        return $score;
    }
    public static function choose(array $uri,array $set,string $accept,string $language): array {
        $requested=$uri['parameters']['linkType']??null;
        if ($requested!==null) {
            $type=str_starts_with($requested,'gs1:')?ResolverRegistry::NS.substr($requested,4):$requested;
            $candidates=array_values(array_filter($set['links'],static fn($link)=>in_array($type,$link['types'],true)));
            if (!$candidates) return ['status'=>404,'choices'=>$set['links']];
        } else {
            if ($set['safe_default']??$set['alert']) return ['status'=>307,'link'=>$set['default']];
            $candidates=array_values(array_filter($set['links'],static fn($link)=>in_array(ResolverRegistry::NS.'defaultLinkMulti',$link['types'],true)));
            if (!$candidates) return ['status'=>307,'link'=>$set['default']];
        }
        $languages=self::preferences($language); $media=self::preferences($accept); $wantedContext=$uri['parameters']['context']??null;
        $ranked=[];
        foreach ($candidates as $link) {
            $score=0.0;
            if ($wantedContext!==null) $score+=in_array($wantedContext,$link['context']??[],true)?100:0;
            if ($languages) $score+=self::languageScore($link['hreflang']??[],$languages)*20;
            if ($media) $score+=self::mediaScore($link['type']??'text/html',$media)*10;
            $ranked[]=['link'=>$link,'score'=>$score];
        }
        usort($ranked,static fn($a,$b)=>($b['score']<=>$a['score'])?:($b['link']['_rank']<=>$a['link']['_rank'])?:strcmp($a['link']['_id'],$b['link']['_id']));
        if (count($ranked)===1) return ['status'=>307,'link'=>$ranked[0]['link']];
        if ($requested===null && $ranked[0]['score']===0.0) return ['status'=>307,'link'=>$set['default']];
        $best=array_values(array_filter($ranked,static fn($candidate)=>$candidate['score']===$ranked[0]['score']&&$candidate['link']['_rank']===$ranked[0]['link']['_rank']));
        $hrefs=array_unique(array_map(static fn($entry)=>$entry['link']['href'],$best));
        return count($hrefs)===1?['status'=>307,'link'=>$best[0]['link']]:['status'=>300,'choices'=>array_column($best,'link')];
    }
    public static function location(string $href,string $query): string {
        if ($query==='') return $href;
        $parts=explode('#',$href,2); $base=$parts[0];
        return $base.(str_contains($base,'?')?(str_ends_with($base,'?')||str_ends_with($base,'&')?'':'&'):'?').$query.(isset($parts[1])?'#'.$parts[1]:'');
    }
}
