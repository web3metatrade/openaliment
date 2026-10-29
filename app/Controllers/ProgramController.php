<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Api, ApiInput, Auth, Db, Http, Problem, Session};
use App\Domain\{ProgramAccess, ProgramService};

final class ProgramController
{
    public static function api(string $method, array $route): never
    {
        ProgramAccess::installed();
        $user = null; $action = substr($route['handler'], 8);
        $private = $route['auth'] !== 'public';
        if ($route['auth'] === 'session-optional') {
            if (Api::bearer() !== null) { throw new Problem(403, 'browser_session', 'Folosește sesiunea proprie în browser.'); }
            Session::start(); $user = Auth::user();
        } elseif ($private) {
            $user = ProgramAccess::actor($route['scope'], $route['auth'] !== 'program');
        }
        if ($action === 'register' && $user) { throw new Problem(409, 'already_authenticated', 'Ai deja un cont conectat. Ieși din cont înainte de o înscriere nouă.'); }
        Api::limit($private ? 'private-read' : 'public', 1, $user['id'] ?? null);
        header('Cache-Control: no-store');
        $data = in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true) ? Http::input($action === 'asset_upload' ? 2900000 : 1500000, ['application/json']) : [];
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            ApiInput::validate($data, $route['body']);
            if (!isset($user['program_token'])) { Http::sameOrigin(); Session::verify(); }
            Api::limit('private-write', 1, $user['id'] ?? null);
        }
        $run = static fn(): array => ProgramService::run($action, $user, $route['resource_id'], $data, $route['parameters']);
        $external = ['register', 'verification_preview', 'verification_confirm', 'verification_request', 'asset_upload', 'company_check', 'partner_refresh', 'admin_worker', 'password_change', 'access_revoke'];
        if (!in_array($method, ['GET', 'HEAD'], true) && $user && !in_array($action, $external, true)) {
            // Re-check role before idempotent replay; delegated privileges may have been withdrawn.
            self::authorizeReplay($action, $user, $data);
            self::authorizeResource($action, $user, $route['resource_id']);
            Http::json(ProgramAccess::mutation($user, $data, $run));
        }
        Http::json($run());
    }

    public static function authorizeReplay(string $action, array $user, array $data = []): void
    {
        if (!str_starts_with($action, 'admin_')) { return; }
        if ($action === 'admin_events') { \App\Domain\ProgramAdministration::authorizeEvents($user, $data['object_kind'] ?? ''); return; }
        $role = match (true) {
            str_contains($action, 'contributor') => 'community_editor',
            str_contains($action, 'course_publish'), str_contains($action, 'course_retire') => 'course_publisher',
            str_contains($action, 'course'), str_contains($action, 'bank_create'), str_contains($action, 'bank_save') => 'course_editor',
            str_contains($action, 'partner'), str_contains($action, 'authorization'), str_contains($action, 'contact') => 'partner_reviewer',
            str_contains($action, 'staff'), $action === 'admin_worker' => '',
            default => 'exam_reviewer',
        };
        if ($role === '') {
            if (!$user['is_admin']) { throw new Problem(403, 'admin', 'Operațiune rezervată administratorului.'); }
        } else { ProgramAccess::staff($user, $role); }
    }

    public static function authorizeResource(string $action, array $user, string $id): void
    {
        if (in_array($action, ['application_save', 'contact_save', 'application_submit', 'application_withdraw'], true)) {
            \App\Domain\ProgramPartners::authorizeChange($user, $id);
        }
    }
}
