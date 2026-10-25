<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Api, ApiInput, Auth, Db, Http, Problem, Session};
use App\Domain\{ApiKeys, Marketplace, Mutations, Passkeys, Printing};

final class MarketplaceController
{
    public static function matches(string $path): bool
    {
        return in_array($path, ['/marketplace', '/developers/marketplace', '/admin/marketplace', '/manage/printing'], true)
            || preg_match('~\A/marketplace/[a-z0-9-]{3,100}\z~D', $path) === 1
            || preg_match('~\A/developers/marketplace/[a-f0-9]{32}\z~D', $path) === 1;
    }

    private static function user(): array
    {
        Auth::browserOnly();
        $user = Auth::user();
        if (!$user) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $_SESSION['application_return'] = is_string($path) && preg_match('~\A/(?:developers/marketplace(?:/[a-f0-9]{32})?|manage/printing)\z~D', $path) ? $path : '/developers/marketplace';
            throw new Problem(401, 'authentication', 'Conectează-te la cont.');
        }
        Passkeys::full($user);
        return $user;
    }

    public static function api(string $method, array $route): never
    {
        Marketplace::installed();
        $handler = substr($route['handler'], 12);
        $id = $route['resource_id'];
        $data = in_array($method, ['POST', 'PUT'], true) ? Http::input(150000, ['application/json']) : [];
        ApiInput::validate($data, $route['body']);
        if ($route['auth'] === 'public') {
            Api::limit('catalog');
            if ($handler === 'catalog') {
                Http::json(Marketplace::catalog());
            }
            if ($handler === 'download') {
                $release = Db::one("SELECT r.manifest_json,a.approved_json FROM marketplace_releases r JOIN marketplace_apps a ON a.id=r.app_id JOIN users u ON u.id=a.owner_user_id AND u.active=1 AND u.email_verified=1 WHERE r.id=? AND r.state='approved' AND a.state='published'", [$id])
                    ?? throw new Problem(404, 'marketplace_release', 'Versiunea nu este disponibilă.');
                if (json_decode($release['manifest_json'], true)['pricing'] !== 'free') {
                    throw new Problem(409, 'marketplace_commerce_disabled', 'Achizițiile nu sunt disponibile în această etapă.');
                }
                Api::limit('download');
                header('Referrer-Policy: no-referrer');
                Http::redirect(json_decode($release['manifest_json'], true)['artifact_url']);
            }
            $app = Db::one("SELECT slug FROM marketplace_apps WHERE id=? AND state='published'", [$id])
                ?? throw new Problem(404, 'marketplace_app', 'Integrarea nu a fost găsită.');
            Http::json(Marketplace::catalog($app['slug'])['items'][0]);
        }
        if ($route['auth'] === 'print-agent') {
            $device = Printing::agent(Api::bearer() ?? '');
            Http::json($handler === 'claim' ? Printing::claim($device) : Printing::acknowledge($device, $id, $data));
        }
        if (in_array($route['auth'], ['session', 'admin-session'], true)) {
            $user = $route['auth'] === 'admin-session' ? Auth::admin() : self::user();
            Api::limit($method === 'GET' || $method === 'HEAD' ? 'private-read' : 'private-write', 1, $user['id']);
            if ($method !== 'GET' && $method !== 'HEAD') {
                Http::sameOrigin();
                Session::verify();
            }
            Http::json(match ($handler) {
                'own' => Marketplace::dashboard($user),
                'queue' => Marketplace::dashboard($user, true),
                'save' => Marketplace::save($user, $id ?: null, $data),
                'submit' => Marketplace::submit($user, $id),
                'release' => Marketplace::release($user, $id, $data),
                'releases' => Marketplace::releases($user, $id),
                'withdraw' => Marketplace::withdraw($user, $id, $data),
                'review' => Marketplace::review($user, $id, $data),
                default => throw new Problem(404, 'route', 'Operațiune incorectă.'),
            });
        }
        $p = Auth::principal($route['scope']);
        if ($route['auth'] === 'owner-session') {
            Auth::browserOnly();
            ApiKeys::owner($p);
        }
        Api::limit('private-read', 1, $p['token'] ?? $p['user']);
        if ($method !== 'GET' && $method !== 'HEAD') {
            Api::write($p);
        }
        if (in_array($handler, ['device', 'revoke'], true)) {
            Api::limit('private-write', 1, $p['user']);
            Http::json($handler === 'device' ? Printing::device($p, $id ?: null, $data) : Printing::revoke($p, $id));
        }
        $read = match ($handler) {
            'units' => Printing::units($p), 'selections' => Marketplace::selections($p),
            'devices' => Printing::devices($p), 'jobs' => Printing::jobs($p), 'job' => Printing::job($p, $id), default => null,
        };
        if ($read !== null) {
            Http::json($read);
        }
        Api::mutation($p, $data, static fn() => match ($handler) {
            'select' => Marketplace::select($p, $id, $data['active'] ?? true),
            'createJob' => Printing::create($p, $data),
            'cancel' => Printing::cancel($p, $id, $data),
            'reprint' => Printing::reprint($p, $id, $data),
            default => throw new Problem(404, 'route', 'Operațiune incorectă.'),
        }, 200);
    }

    private static function formData(array $data): array
    {
        foreach (['tags', 'capabilities', 'platforms', 'compatibility'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = array_values(array_filter(array_map('trim', explode(',', $data[$key])), static fn($v) => $v !== ''));
            }
        }
        foreach (['expected_revision', 'quantity', 'size_bytes'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && ctype_digit($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }
        foreach (['artifact_verified', 'same_serials_confirmed'] as $key) {
            $data[$key] = isset($data[$key]);
        }
        return $data;
    }

    public static function web(string $method, string $path): void
    {
        Api::method($method, ['GET', 'HEAD', 'POST']);
        Marketplace::installed();
        if ($path === '/marketplace' || str_starts_with($path, '/marketplace/')) {
            Api::method($method, ['GET', 'HEAD']);
            Api::limit('catalog');
            $slug = $path === '/marketplace' ? null : basename($path);
            $catalog = Marketplace::catalog($slug);
            Http::view('marketplace', ['title' => $slug !== null ? $catalog['items'][0]['name'] : 'Integrări și instrumente', 'description' => $slug !== null ? $catalog['items'][0]['summary'] : null, 'catalog' => $catalog, 'detail' => $slug !== null]);
            return;
        }
        $user = $path === '/admin/marketplace' ? Auth::admin() : self::user();
        $secret = null;
        $selected = preg_match('~/([a-f0-9]{32})\z~D', $path, $match) ? $match[1] : null;
        if ($method === 'POST') {
            Http::sameOrigin();
            Session::verify();
            Api::limit('private-write', 1, $user['id']);
            $data = self::formData(Http::input(150000));
            $action = $data['action'] ?? '';
            if ($path === '/admin/marketplace') {
                Marketplace::review($user, Api::id($data['id'] ?? ''), $data);
                Session::flash('Decizia a fost înregistrată.');
            } elseif ($path === '/manage/printing') {
                $p = Auth::browser();
                $id = empty($data['id']) ? null : Api::id($data['id']);
                if (in_array($action, ['device', 'rotate'], true)) {
                    $secret = Printing::device($p, $action === 'rotate' ? $id : null, $data)['token'];
                } elseif ($action === 'revoke') {
                    Printing::revoke($p, $id ?? '');
                } elseif ($action === 'cancel') {
                    Printing::cancel($p, $id ?? '', $data);
                } elseif ($action === 'reprint') {
                    Printing::reprint($p, $id ?? '', $data);
                } elseif ($action === 'job') {
                    // A browser double-submit cannot reserve another set of serials.
                    Mutations::idempotent($p, $data['request_key'] ?? '', hash('sha256', Db::json($data)), static fn() => Printing::create($p, $data));
                } else {
                    throw new Problem(422, 'action', 'Operațiune incorectă.');
                }
                Session::flash('Operațiunea a fost înregistrată.');
            } elseif ($action === 'save') {
                $saved = Marketplace::save($user, $selected, $data);
                Session::flash('Ciorna a fost salvată.');
                Http::redirect('/developers/marketplace/' . $saved['id']);
            } elseif ($selected !== null && $action === 'submit') {
                Marketplace::submit($user, $selected);
                Session::flash('Oferta a fost trimisă pentru verificare.');
            } elseif ($selected !== null && $action === 'withdraw') {
                Marketplace::withdraw($user, $selected, $data);
                Session::flash('Retragerea a fost înregistrată.');
            } elseif ($selected !== null && $action === 'release') {
                Marketplace::release($user, $selected, $data);
                Session::flash('Versiunea a fost trimisă pentru verificare.');
            } else {
                throw new Problem(422, 'action', 'Operațiune incorectă.');
            }
            if ($secret === null) {
                Http::redirect($path);
            }
        }
        if ($path === '/manage/printing') {
            $p = Auth::browser();
            $job = empty($_GET['job']) ? null : Printing::job($p, Api::id($_GET['job']));
            Http::view('printing', ['title' => 'Etichete și echipamente', 'devices' => Printing::devices($p)['items'],
                'jobs' => Printing::jobs($p)['items'], 'job' => $job, 'secret' => $secret, 'p' => $p,
                'batches' => Db::all("SELECT b.id,b.lot_code,t.name FROM batches b JOIN trade_items t ON t.id=b.trade_item_id WHERE b.tenant_id=? AND b.visibility='public' AND t.gtin_status='verified' ORDER BY b.created_at DESC LIMIT 200", [$p['tenant']])]);
            return;
        }
        $dashboard = Marketplace::dashboard($user, $path === '/admin/marketplace');
        $app = null;
        if ($selected !== null) {
            Marketplace::owned($user['id'], $selected);
            foreach ($dashboard['items'] as $candidate) {
                if ($candidate['id'] === $selected) {
                    $app = $candidate;
                }
            }
        }
        Http::view('marketplace-manage', ['title' => 'Publică o integrare', 'dashboard' => $dashboard,
            'app' => $app, 'admin' => $path === '/admin/marketplace']);
    }
}
