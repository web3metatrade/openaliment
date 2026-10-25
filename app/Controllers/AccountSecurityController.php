<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,Auth,Http,Problem,Session};
use App\Domain\{Accounts,Passkeys,ProducerSessions};

final class AccountSecurityController {
    public static function handle(string $method): void {
        Auth::browserOnly();$user=Auth::user();if(!$user)throw new Problem(401,'authentication','Conectează-te la cont.');Api::method($method,['GET','HEAD','POST']);
        if(Passkeys::count($user['id'])>0)Passkeys::full($user);
        if($method==='POST'){
            Http::sameOrigin();Session::verify();if(Passkeys::required($user))Passkeys::recent($user);Accounts::secure(Auth::browser(),Http::input(4096));
            Session::flash('Modificarea a fost salvată. Celelalte sesiuni și accesurile aplicațiilor au fost închise.');Http::redirect('/manage/security');
        }
        Http::view('account-security',['title'=>'Securitatea contului','passwordUpdateRecommended'=>($_SESSION['password_update_recommended']??false)===true,'passkeys'=>Passkeys::list($user['id']),'sessions'=>ProducerSessions::list($user['id']),'currentSession'=>$_SESSION['producer_session_id']??null]);
    }
}
