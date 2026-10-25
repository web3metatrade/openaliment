<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Problem};

final class HomeSync {
    public static function member(array $p,bool $write=false): array {
        $r=Db::one('SELECT role FROM home_members WHERE household_id=? AND account_id=?',[$p['household_id'],$p['account_id']]);if(!$r)throw new Problem(403,'home_membership','Nu mai ai acces la această familie.');if($write)HomeIdentity::write($r);return $r;
    }
    private static function row(?array $r): array {return $r?['item_id'=>$r['id'],'server_revision'=>(int)$r['server_revision'],'seq'=>(int)$r['last_seq'],'deleted'=>(bool)$r['deleted'],'item'=>$r['snapshot_json']===null?null:json_decode($r['snapshot_json'],true,64,JSON_THROW_ON_ERROR)]:['server_revision'=>0,'seq'=>0,'deleted'=>true,'item'=>null];}
    private static function photo(array $p,?array $item): void {if($item!==null&&$item['photo_id']!==null&&!Db::one('SELECT id FROM home_photos WHERE household_id=? AND id=?',[$p['household_id'],$item['photo_id']]))throw new Problem(422,'home_photo','Încarcă fotografia privată înainte de sincronizarea alimentului.');}
    public static function push(array $p,array $data): array {
        if(array_diff(array_keys($data),['operations','history'])||!is_array($data['operations']??null)||!array_is_list($data['operations'])||count($data['operations'])>50||!is_array($data['history']??[])||!array_is_list($data['history']??[])||count($data['history']??[])>50)throw new Problem(422,'home_sync','Cerere de sincronizare incorectă.');
        $ops=[];$events=[];$items=[];
        foreach($data['operations']as$op){if(!is_array($op)||array_diff(array_keys($op),['event_id','item_id','expected_revision','kind','item'])||!in_array($op['kind']??null,['put','delete'],true)||!is_int($op['expected_revision']??null)||$op['expected_revision']<0)throw new Problem(422,'home_sync','Operațiune incorectă.');
            $event=HomeInventory::id($op['event_id']??null);$id=HomeInventory::id($op['item_id']??null);if(isset($events[$event])||isset($items[$id]))throw new Problem(422,'home_sync','Operațiune sau ambalaj repetat în același pachet.');$events[$event]=true;$items[$id]=true;
            if($op['kind']==='put'&&!is_array($op['item']??null)||$op['kind']==='delete'&&isset($op['item']))throw new Problem(422,'home_sync','Conținut de operațiune incorect.');$item=$op['kind']==='put'?HomeInventory::item($op['item']):null;if($item!==null&&$item['id']!==$id)throw new Problem(422,'home_sync','Referință de ambalaj incorectă.');
            $normalized=['event_id'=>$event,'item_id'=>$id,'expected_revision'=>$op['expected_revision'],'kind'=>$op['kind'],'item'=>$item];$ops[]=$normalized;
        }
        $history=[];$historyIds=[];foreach($data['history']??[]as$e){if(!is_array($e))throw new Problem(422,'home_history','Istoric incorect.');$normalized=HomeInventory::history($e);if(isset($historyIds[$normalized['id']]))throw new Problem(422,'home_history','Eveniment repetat.');$historyIds[$normalized['id']]=true;$history[]=$normalized;}
        return Db::atomic(function()use($p,$ops,$history){
            $h=Db::one('SELECT change_seq FROM home_households WHERE id=? FOR UPDATE',[$p['household_id']]);if(!$h)throw new Problem(404,'home_household','Familia nu a fost găsită.');self::member($p,true);
            $cursor=(int)$h['change_seq'];$conflicts=[];$accepted=[];$pending=[];$historyPending=[];$historyAccepted=[];$delta=0;
            foreach($ops as$op){$sha=hash('sha256',Db::json($op));$old=Db::one('SELECT request_sha256,seq,server_revision FROM home_changes WHERE household_id=? AND event_id=?',[$p['household_id'],$op['event_id']]);
                if($old){if(!hash_equals($old['request_sha256'],$sha))throw new Problem(409,'home_event_conflict','Evenimentul a fost folosit cu alte date.');$accepted[]=['event_id'=>$op['event_id'],'item_id'=>$op['item_id'],'server_revision'=>(int)$old['server_revision'],'seq'=>(int)$old['seq']];continue;}
                $current=Db::one('SELECT * FROM home_items WHERE household_id=? AND id=?',[$p['household_id'],$op['item_id']]);$revision=(int)($current['server_revision']??0);
                if($revision!==$op['expected_revision']){$conflicts[]=['event_id'=>$op['event_id'],'item_id'=>$op['item_id'],'current'=>self::row($current)];continue;}
                self::photo($p,$op['item']);$delta+=($op['kind']==='put'?1:0)-($current&&!(bool)$current['deleted']?1:0);$pending[]=[$op,$sha,$current,$revision+1];
            }
            foreach($history as$e){$json=Db::json($e);$sha=hash('sha256',$json);$old=Db::one('SELECT sha256 FROM home_history WHERE household_id=? AND id=?',[$p['household_id'],$e['id']]);if($old){if(!hash_equals($old['sha256'],$sha))throw new Problem(409,'home_history_conflict','Un eveniment de istoric diferă între dispozitive.');$historyAccepted[]=$e['id'];continue;}self::photo($p,$e['before']);self::photo($p,$e['after']);$historyPending[]=[$e,$json,$sha];}
            if($conflicts)return ['accepted'=>$accepted,'history_accepted'=>$historyAccepted,'conflicts'=>$conflicts,'cursor'=>$cursor,'atomic'=>true];
            if((int)Db::one('SELECT COUNT(*) AS n FROM home_items WHERE household_id=? AND deleted=0',[$p['household_id']])['n']+$delta>2000)throw new Problem(413,'home_quota','Familia are deja 2.000 de ambalaje.');
            if((int)Db::one('SELECT COUNT(*) AS n FROM home_history WHERE household_id=?',[$p['household_id']])['n']+count($historyPending)>50000)throw new Problem(413,'home_history_quota','Istoricul depășește limita de sincronizare. Datele locale rămân disponibile.');
            foreach($pending as[$op,$sha,$current,$revision]){++$cursor;$json=$op['item']===null?null:Db::json($op['item']);
                Db::run('INSERT INTO home_items (household_id,id,server_revision,last_seq,deleted,snapshot_json) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE server_revision=VALUES(server_revision),last_seq=VALUES(last_seq),deleted=VALUES(deleted),snapshot_json=VALUES(snapshot_json)',[$p['household_id'],$op['item_id'],$revision,$cursor,(int)($op['kind']==='delete'),$json]);
                Db::insert('home_changes',['household_id'=>$p['household_id'],'seq'=>$cursor,'event_id'=>$op['event_id'],'request_sha256'=>$sha,'item_id'=>$op['item_id'],'server_revision'=>$revision,'before_json'=>$current['snapshot_json']??null,'after_json'=>$json,'account_id'=>$p['account_id'],'device_id'=>$p['device_id'],'created_at'=>Db::now()]);$accepted[]=['event_id'=>$op['event_id'],'item_id'=>$op['item_id'],'server_revision'=>$revision,'seq'=>$cursor];
            }
            foreach($historyPending as[$e,$json,$sha]){++$cursor;Db::insert('home_history',['household_id'=>$p['household_id'],'id'=>$e['id'],'seq'=>$cursor,'sha256'=>$sha,'event_json'=>$json,'created_at'=>Db::now()]);$historyAccepted[]=$e['id'];}
            Db::run('UPDATE home_households SET change_seq=? WHERE id=?',[$cursor,$p['household_id']]);return ['accepted'=>$accepted,'history_accepted'=>$historyAccepted,'conflicts'=>[],'cursor'=>$cursor,'atomic'=>true];
        });
    }
    public static function changes(array $p,int $after=0,int $limit=100): array {
        if($after<0||$limit<1||$limit>100)throw new Problem(422,'home_cursor','Cursor incorect.');
        return Db::atomic(function()use($p,$after,$limit){
            // Same lock as writes: a cursor never advances past a concurrent change omitted from this page.
            $h=Db::one('SELECT change_seq FROM home_households WHERE id=? FOR UPDATE',[$p['household_id']]);if(!$h)throw new Problem(404,'home_household','Familia nu a fost găsită.');self::member($p);$head=(int)$h['change_seq'];if($after>$head)throw new Problem(409,'home_cursor','Cursorul nu aparține acestui inventar.');
            $records=Db::all("SELECT last_seq AS seq,'item' AS kind,id,snapshot_json,server_revision,deleted FROM home_items WHERE household_id=? AND last_seq>? UNION ALL SELECT seq,'history' AS kind,id,event_json AS snapshot_json,0 AS server_revision,0 AS deleted FROM home_history WHERE household_id=? AND seq>? ORDER BY seq LIMIT ".($limit+1),[$p['household_id'],$after,$p['household_id'],$after]);$more=count($records)>$limit;if($more)array_pop($records);$out=[];$cursor=$after;
            foreach($records as$r){$cursor=(int)$r['seq'];if($r['kind']==='item'){$r['last_seq']=$r['seq'];$value=self::row($r);}else $value=json_decode($r['snapshot_json'],true,64,JSON_THROW_ON_ERROR);$out[]=['kind'=>$r['kind'],'seq'=>$cursor,'value'=>$value];}
            if(!$more)$cursor=max($cursor,$head);return ['household_id'=>$p['household_id'],'changes'=>$out,'cursor'=>$cursor,'has_more'=>$more];
        });
    }
}
