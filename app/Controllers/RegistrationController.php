<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Api, ApiInput, Auth, Config, Db, Http, Migrations, Problem, Session};
use App\Domain\{Accounts, OrganizationRegistry, RegistrationSettings};

final class RegistrationController
{
    public static function page(string $method): void
    {
        Api::method($method, ['GET', 'HEAD', 'POST']);
        Api::limit('auth');
        Session::start();
        if ($method === 'GET' && ($_GET['context'] ?? '') === 'partner' && Migrations::program()) { $_SESSION['registration_context'] = 'partner'; }
        $partnerContext = ($_SESSION['registration_context'] ?? '') === 'partner' && Migrations::program();
        if ($user = Auth::user()) {
            Http::redirect(\App\Domain\Passkeys::required($user) && empty($_SESSION['mfa_verified_at']) ? '/account/verify' : ($partnerContext ? '/parteneri/inscriere' : (Migrations::program() ? \App\Domain\ProgramAccess::home($user) : '/manage')));
        }
        if (!Migrations::anaf()) {
            if ($method === 'POST') {
                Http::sameOrigin(); Session::verify();
                Auth::rate('register|' . Api::clientIp(), 5, 3600);
                $account = Accounts::register(Http::input(12000));
                Session::login($account['id']); Http::redirect('/manage');
            }
            Http::view('auth', ['mode' => 'register', 'title' => 'Înregistrează producătorul']);
            return;
        }
        $data = []; $result = null; $error = null; $status = 200;
        if ($method === 'POST') {
            Http::sameOrigin(); Session::verify(); $data = Http::input(12000);
            try {
                if (($data['intent'] ?? '') === 'lookup') {
                    $result = OrganizationRegistry::lookup($data);
                } elseif (($data['intent'] ?? '') === 'create') {
                    if (empty($data['organization_lookup_id'])) throw new Problem(422, 'cui_confirmation', 'Verifică mai întâi CUI-ul firmei.');
                    Auth::rate('register|' . Api::clientIp(), 5, 3600);
                    $account = Accounts::register($data);
                    Session::login($account['id']);
                    if ($partnerContext) {
                        $application = \App\Domain\ProgramPartners::fromRegisteredTenant(Auth::user(), $account['tenant_id']);
                        Http::redirect('/parteneri/cereri/' . $application['id']);
                    }
                    Http::redirect('/manage/organization');
                } else {
                    throw new Problem(422, 'action', 'Alege verificarea CUI-ului sau crearea contului.');
                }
            } catch (Problem $failure) {
                $error = $failure->getMessage(); $status = $failure->status;
                if (($data['intent'] ?? '') === 'create' && !in_array($failure->errorCode, ['cui_confirmation', 'anaf_check_expired', 'anaf_check_state', 'cui'], true)) {
                    try { $result = OrganizationRegistry::registration($data); } catch (Problem) {}
                }
            }
        }
        Http::view('register', ['title' => $partnerContext ? 'Înscrie firma ca partener' : 'Înregistrează organizația', 'partnerRegistration' => $partnerContext, 'registrationEnabled' => RegistrationSettings::enabled(), 'values' => $data, 'anafResult' => $result, 'registrationError' => $error], $status);
    }

    public static function api(string $method, array $route): never
    {
        header('Cache-Control: no-store');
        $handler = $route['handler']; $p = null;
        if ($handler === 'anaf.admin-profile') {
            Auth::admin();
            $tenant = Api::id($route['resource_id']);
            if (!Db::one('SELECT id FROM organizations WHERE id=?', [$tenant])) throw new Problem(404, 'not_found', 'Organizația nu a fost găsită.');
            Http::json(OrganizationRegistry::profile(['tenant' => $tenant]));
        }
        if ($route['auth'] === 'producer') {
            $p = Auth::principal($route['scope']);
            Api::limit('private-read', 1, $p['token'] ?? $p['user']);
        } else {
            if (Api::bearer() !== null) throw new Problem(403, 'browser_session', 'Verificarea pentru înscriere necesită sesiunea proprie.');
            Session::start();
        }
        $data = $method === 'POST' ? Http::input(12000, ['application/json']) : [];
        if ($method === 'POST') {
            ApiInput::validate($data, $route['body']);
            if ($p === null) { Http::sameOrigin(); Session::verify(); }
            else Api::write($p);
        }
        if ($handler === 'anaf.lookup' || $handler === 'anaf.check') Http::json(OrganizationRegistry::lookup($data, $p));
        if ($handler === 'anaf.result') Http::json(OrganizationRegistry::check($route['resource_id']));
        if ($handler === 'anaf.profile') Http::json(OrganizationRegistry::profile($p));
        if ($handler === 'anaf.history') Http::json(OrganizationRegistry::history($p));
        if ($handler === 'anaf.confirm') {
            // Authenticate before replay and keep passwords out of fast idempotency hashes.
            OrganizationRegistry::authorizeConfirmation($p, $data);
            unset($data['current_password']);
            Api::mutation($p, $data, static fn(): array => OrganizationRegistry::confirm($p, $data), 200);
        }
        throw new Problem(404, 'route', 'Operațiunea ANAF nu a fost găsită.');
    }

    public static function profilePage(string $method): void
    {
        Api::method($method, ['GET', 'HEAD', 'POST']);
        $p = Auth::browser(); OrganizationRegistry::required();
        $result = null; $error = null; $status = 200;
        if ($method === 'POST') {
            Http::sameOrigin(); Session::verify(); $data = Http::input(12000);
            try {
                if (($data['intent'] ?? '') === 'check') $result = OrganizationRegistry::lookup($data, $p);
                elseif (($data['intent'] ?? '') === 'confirm') {
                    $data['expected_version_id'] = ($data['expected_version_id'] ?? '') === '' ? null : $data['expected_version_id'];
                    OrganizationRegistry::confirm($p, $data);
                    Session::flash('Profilul fiscal a fost salvat. Istoricul produselor și loturilor este păstrat.');
                    Http::redirect('/manage/organization');
                } else throw new Problem(422, 'action', 'Operațiune incorectă.');
            } catch (Problem $failure) {
                $error = $failure->getMessage(); $status = $failure->status;
                if (($data['intent'] ?? '') === 'confirm') {
                    try { $result = OrganizationRegistry::check((string) ($data['organization_lookup_id'] ?? ''), $p); } catch (Problem) {}
                }
            }
        }
        $organization = Db::one('SELECT display_name,identity_status FROM organizations WHERE id=?', [$p['tenant']]);
        Http::view('organization-profile', ['title' => 'Profilul organizației', 'p' => $p, 'organization' => $organization, 'legalProfile' => OrganizationRegistry::profile($p)['profile'], 'anafResult' => $result, 'profileError' => $error, 'history' => OrganizationRegistry::history($p, 5)], $status);
    }
}
