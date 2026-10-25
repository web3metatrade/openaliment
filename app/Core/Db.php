<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class Db {
    private static array $rollbackCallbacks=[];
    public static function onRollback(callable $callback): void {
        if(!self::pdo()->inTransaction())throw new \LogicException('Rollback cleanup requires an active transaction.');
        self::$rollbackCallbacks[]=$callback;
    }
    private static ?PDO $pdo = null;
    private static bool $migrationActive=false;
    public static function migrationActive(): bool {return self::$migrationActive;}
    public static function migrationsConfigured(): bool {
        return is_array(Config::get('db_migrations'))||(!str_contains((string)(Config::get('db',[])['dsn']??''),'dbname=openaliment_tests;')&&is_file(ROOT.'/config/migrations.local.php'));
    }
    /** DDL is isolated to explicit installation/update calls, never a normal QR request. */
    public static function migration(callable $run): mixed {
        if(self::$migrationActive)return $run();$runtime=self::pdo();if($runtime->inTransaction())throw new \LogicException('Schema updates require a separate transaction boundary.');
        $options=Config::get('db_migrations');if($options===null&&self::migrationsConfigured())$options=require ROOT.'/config/migrations.local.php';
        $connection=$runtime;
        if($options!==null){
            if(!is_array($options)||!is_string($options['dsn']??null)||!is_string($options['user']??null)||!is_string($options['password']??null))throw new \RuntimeException('Invalid private migration configuration.');
            $connection=new PDO($options['dsn'],$options['user'],$options['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
            $identity='SELECT DATABASE() AS db,@@hostname AS host,@@port AS port';if($connection->query($identity)->fetch()!==$runtime->query($identity)->fetch())throw new \RuntimeException('Migration connection must use the same database and server.');$connection->exec("SET time_zone = '+00:00'");
        }
        self::$migrationActive=true;self::$pdo=$connection;
        try{return $run();}finally{self::$pdo=$runtime;self::$migrationActive=false;}
    }
    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $c = Config::get('db');
            self::$pdo = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }
    public static function run(string $sql, array $params = []): \PDOStatement { $s=self::pdo()->prepare($sql); $s->execute($params); return $s; }
    public static function one(string $sql, array $params = []): ?array { return self::run($sql,$params)->fetch() ?: null; }
    public static function all(string $sql, array $params = []): array { return self::run($sql,$params)->fetchAll(); }
    public static function insert(string $table, array $data): string {
        // Table/column identifiers are exclusively internal constants in callers.
        $cols=array_keys($data); self::run('INSERT INTO '.$table.' (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($data));
        return $data['id'] ?? '';
    }
    public static function atomic(callable $fn): mixed {
        $p=self::pdo(); if($p->inTransaction()) return $fn();
        self::$rollbackCallbacks=[];$p->beginTransaction();
        try { $out=$fn();$p->commit();self::$rollbackCallbacks=[];return $out; }
        catch(\Throwable $e){if($p->inTransaction())$p->rollBack();foreach(array_reverse(self::$rollbackCallbacks)as$callback){try{$callback();}catch(\Throwable){error_log('OpenAliment rollback cleanup failed.');}}self::$rollbackCallbacks=[];throw $e;}
    }
    public static function id(): string { return bin2hex(random_bytes(16)); }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function json(mixed $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
}
