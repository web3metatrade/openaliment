<?php
declare(strict_types=1);
namespace App\Core;

/** New passwords use Argon2id where available; legacy hashes remain verifiable. */
final class Passwords {
    // Public dummy hash, bcrypt cost 12. Never used for a real account.
    private const DUMMY='$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    public static function argon(): bool {return defined('PASSWORD_ARGON2ID')&&in_array('argon2id',password_algos(),true);}
    public static function maxBytes(): int {return self::argon()?200:72;}
    private static function options(): array {$options=['memory_cost'=>19456,'time_cost'=>2];if(!defined('PASSWORD_ARGON2_PROVIDER')||PASSWORD_ARGON2_PROVIDER!=='sodium')$options['threads']=1;return $options;}
    public static function validate(mixed $password): string {
        if(!is_string($password)||str_contains($password,"\0")||!mb_check_encoding($password,'UTF-8')||strlen($password)<12||strlen($password)>self::maxBytes())throw new Problem(422,'password','Parola trebuie să aibă între 12 și '.self::maxBytes().' de octeți. Diacriticele pot ocupa mai mulți octeți.');
        return $password;
    }
    public static function hash(string $password): string {
        self::validate($password);
        return self::argon()?password_hash($password,PASSWORD_ARGON2ID,self::options()):password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
    }
    public static function verify(mixed $password,?string $hash): bool {
        $valid=is_string($password)&&strlen($password)<=200&&!str_contains($password,"\0");
        // The same bounded work is performed for unknown users and malformed inputs.
        $candidate=$valid?$password:'invalid-password-value';
        if($hash===null){
            if(self::argon())password_verify($candidate,'$argon2id$v=19$m=19456,t=2,p=1$c3ludGhldGljLWR1bW15LXNhbHQ$KROJlKpS5GPzJ6rrEK0EZV6GaGTXXS4Jz8huMZpi8ZU');
            else password_verify($candidate,self::DUMMY);
            return false;
        }
        return password_verify($candidate,$hash)&&$valid;
    }
    public static function needsUpgrade(string $hash,string $password): bool {
        // A bcrypt hash cannot authenticate the suffix after byte 72. Do not silently
        // turn an unverified legacy suffix into a new Argon2 password.
        if(str_starts_with($hash,'$2')&&strlen($password)>72)return false;
        return self::argon()?password_needs_rehash($hash,PASSWORD_ARGON2ID,self::options()):password_needs_rehash($hash,PASSWORD_BCRYPT,['cost'=>12]);
    }
}
