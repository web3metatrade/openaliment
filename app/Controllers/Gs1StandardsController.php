<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Api, ApiInput, Auth, Http, Session};
use App\Domain\Gs1Standards;

final class Gs1StandardsController
{
    public static function api(string $method, array $route): never
    {
        if ($route['handler'] === 'standards.catalog') {
            Api::limit('docs');
            Http::json(Gs1Standards::catalog());
        }
        $user = Auth::admin();
        if ($route['handler'] === 'standards.status') {
            Http::json(Gs1Standards::status());
        }
        Http::sameOrigin();
        Session::verify();
        Auth::rate('gs1-preserve|' . $user['id'], 12, 3600);
        $data = Http::input(4096, ['application/json']);
        ApiInput::validate($data, 'Gs1Preserve');
        Http::json(Gs1Standards::preserve($data['document_id']));
    }

    public static function web(string $method, string $path): void
    {
        Api::method($method, $path === '/admin/standards' ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD']);
        $admin = $path === '/admin/standards';
        if ($admin) {
            $user = Auth::admin();
            if ($method === 'POST') {
                Http::sameOrigin();
                Session::verify();
                Auth::rate('gs1-preserve|' . $user['id'], 12, 3600);
                Gs1Standards::preserve(is_string($_POST['document_id'] ?? null) ? $_POST['document_id'] : '');
                Session::flash('Ediția oficială a fost salvată și verificată prin SHA-256.');
                Http::redirect('/admin/standards');
            }
        } else {
            Api::limit('docs');
        }
        $demo = \App\Domain\Printing::digitalLink(\App\Domain\DemoBread::GTIN14, \App\Domain\DemoBread::LOT, 'DEMO000001', ['production_date' => '2026-10-25', 'best_before' => '2026-10-30']);
        Http::view('gs1-standards', ['title' => 'Standarde GS1', 'description' => 'Edițiile oficiale GS1 folosite de OpenAliment pentru Digital Link, resolver și tipărirea codurilor QR.', 'demoUri' => $demo,
            'catalog' => Gs1Standards::catalog(), 'admin' => $admin, 'copies' => $admin ? Gs1Standards::status()['items'] : []]);
    }
}
