<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Migrations};

/** Searchable scalar projection of approved label fields, never internal product metadata. */
final class ApiPublicIndex {
    public static function refresh(?string $item=null,bool $install=false): void {
        if(!$install&&!Migrations::api())return;
        Db::run("INSERT INTO api_public_items (trade_item_id,product_id,tenant_id,product_info_version_id,name,category)
            SELECT t.id,t.product_id,t.tenant_id,v.id,JSON_UNQUOTE(JSON_EXTRACT(v.snapshot_json,'$.name')),JSON_UNQUOTE(JSON_EXTRACT(v.snapshot_json,'$.category'))
            FROM trade_items t JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id
            WHERE v.state='published'".($item!==null?' AND t.id=?':'').'
            ON DUPLICATE KEY UPDATE product_info_version_id=VALUES(product_info_version_id),name=VALUES(name),category=VALUES(category)', $item!==null?[$item]:[]);
    }
}
