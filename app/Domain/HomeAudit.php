<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\Db;
final class HomeAudit {
    public static function record(array $p,string $action,string $object,array $details=[]): void {Db::insert('home_access_events',['id'=>Db::id(),'account_id'=>$p['account_id'],'household_id'=>$p['household_id'],'action'=>$action,'object_id'=>$object,'details_json'=>Db::json($details),'created_at'=>Db::now()]);}
}
