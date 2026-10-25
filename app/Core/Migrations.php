<?php
declare(strict_types=1);
namespace App\Core;

final class Migrations {
    private static ?bool $marketplace=null;
    public static function marketplace(): bool {return self::$marketplace??=Db::one("SELECT version FROM schema_migrations WHERE version='008_marketplace'")!==null;}
    public static function upgradeMarketplace(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeMarketplace());return;}
        self::upgradeReadiness();if(self::marketplace())return;
        if(!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'])throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try {
            $sql=file_get_contents(ROOT.'/database/migrations/008_marketplace.sql');if($sql===false)throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');
            foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);
            Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['008_marketplace',Db::now()]);self::$marketplace=true;
        }finally{Db::run("SELECT RELEASE_LOCK('openaliment_schema')");}
    }
    private static ?bool $readiness=null;
    public static function readiness(): bool {return self::$readiness??=Db::one("SELECT version FROM schema_migrations WHERE version='007_readiness'")!==null;}
    public static function upgradeReadiness(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeReadiness());return;}
        self::upgradeResolver();self::upgradeSecurity();if(self::readiness())return;
        if(!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'])throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try {
            $sql=file_get_contents(ROOT.'/database/migrations/007_readiness.sql');if($sql===false)throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');
            foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);
            Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['007_readiness',Db::now()]);self::$readiness=true;
        }finally{Db::run("SELECT RELEASE_LOCK('openaliment_schema')");}
    }
    private static ?bool $security=null;
    public static function security(): bool {return self::$security??=Db::one("SELECT version FROM schema_migrations WHERE version='006_security'")!==null;}
    public static function upgradeSecurity(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeSecurity());return;}
        self::upgradeApi();if(self::security())return;
        if(!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'])throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try {
            $sql=file_get_contents(ROOT.'/database/migrations/006_security.sql');if($sql===false)throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');
            foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);
            // Portable MySQL/MariaDB index installation; identifiers are fixed constants.
            foreach(['idempotency_keys'=>['security_created','created_at'],'home_invites'=>['security_expiry','expires_at'],'api_oauth_grants'=>['security_expiry','expires_at'],'api_tokens'=>['security_expiry','expires_at']]as$table=>[$name,$column]){
                if(!Db::one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name]))Db::run('CREATE INDEX '.$name.' ON '.$table.' ('.$column.')');
            }
            Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['006_security',Db::now()]);self::$security=true;
        }finally{Db::run("SELECT RELEASE_LOCK('openaliment_schema')");}
    }
    private static ?bool $api = null;
    public static function api(): bool {return self::$api??=Db::one("SELECT version FROM schema_migrations WHERE version='005_api'")!==null;}
    public static function upgradeApi(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeApi());return;}
        self::upgradeIntegrations();self::upgradeHome();
        if(self::api())return;
        if(!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'])throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try {
            $sql=file_get_contents(ROOT.'/database/migrations/005_api.sql');
            if($sql===false)throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');
            foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);
            \App\Domain\ApiPublicIndex::refresh(null,true);
            Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['005_api',Db::now()]);self::$api=true;
        } finally {Db::run("SELECT RELEASE_LOCK('openaliment_schema')");}
    }
    private static ?bool $resolver = null;
    private static ?bool $integrations = null;
    private static ?bool $home = null;
    public static function home(): bool {return self::$home??=Db::one("SELECT version FROM schema_migrations WHERE version='004_home'")!==null;}
    public static function upgradeHome(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeHome());return;}
        if(self::home())return;
        if(!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'])throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try{$sql=file_get_contents(ROOT.'/database/migrations/004_home.sql');if($sql===false)throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['004_home',Db::now()]);self::$home=true;}finally{Db::run("SELECT RELEASE_LOCK('openaliment_schema')");}
    }
    public static function integrations(): bool {return self::$integrations??=Db::one("SELECT version FROM schema_migrations WHERE version='003_integrations'")!==null;}
    public static function upgradeIntegrations(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeIntegrations());return;}
        if(self::integrations())return;
        if(!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'])throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try{
            $sql=file_get_contents(ROOT.'/database/migrations/003_integrations.sql');if($sql===false)throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');
            foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')Db::pdo()->exec($statement);
            Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['003_integrations',Db::now()]);self::$integrations=true;
        }finally{Db::run("SELECT RELEASE_LOCK('openaliment_schema')");}
    }
    public static function resolver(): bool {
        if (self::$resolver === null) self::$resolver = Db::one("SELECT version FROM schema_migrations WHERE version='002_resolver'") !== null;
        return self::$resolver;
    }
    public static function upgradeResolver(): void {
        if(!Db::migrationActive()){Db::migration(static fn()=>self::upgradeResolver());return;}
        if (self::resolver()) return;
        if (!Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired']) throw new Problem(409,'migration_busy','O actualizare este deja în curs.');
        try {
            $sql = file_get_contents(ROOT.'/database/migrations/002_resolver.sql');
            if ($sql === false) throw new Problem(503,'migration_missing','Fișierul actualizării nu este disponibil.');
            foreach (explode(';',$sql) as $statement) if (trim($statement) !== '') Db::pdo()->exec($statement);
            Db::run('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)',['002_resolver',Db::now()]);
            self::$resolver = true;
        } finally { Db::run("SELECT RELEASE_LOCK('openaliment_schema')"); }
    }
}
