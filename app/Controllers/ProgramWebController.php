<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Api, ApiInput, ApiRoutes, Auth, Db, Http, Problem, Session};
use App\Domain\{Contributors, ProgramAccess, ProgramAdministration, ProgramAssessments, ProgramLearning, ProgramLabs, ProgramMedia, ProgramPartners, ProgramQueries, ProgramService};

final class ProgramWebController
{
    public static function matches(string $path): bool
    {
        return preg_match('~\A/(?:contribuitori|parteneri|invatare|admin/program)(?:/|\z)|\A/program/(?:confirmare\z|imagini/[a-f0-9]{32}\z)~D', $path) === 1;
    }

    public static function handle(string $method, string $path): void
    {
        ProgramAccess::installed(); Api::method($method, ['GET', 'HEAD', 'POST']);
        $user = Auth::user(); $error = null; $check = null; $values = [];
        if (preg_match('~/program/imagini/([a-f0-9]{32})\z~D', $path, $m)) {
            Api::method($method, ['GET', 'HEAD']);
            if ($user) { \App\Domain\Passkeys::full($user); }
            $asset = ProgramMedia::read($user, $m[1]);
            header('Content-Type: image/png'); header('Cache-Control: no-store'); header('Content-Length: ' . filesize($asset['path']));
            if ($method !== 'HEAD') { readfile($asset['path']); } return;
        }
        if ($method === 'POST') {
            Http::sameOrigin(); Session::verify();
            try {
                if (isset($_POST['_exam_submit'])) {
                    self::examSubmit($path);
                }
                [$route, $data] = self::form(); $values = $data; $action = substr($route['handler'], 8);
                $user = $route['auth'] === 'session-optional' ? Auth::user() : ProgramAccess::actor($route['scope'], true);
                if ($route['auth'] !== 'session-optional' && !$user) { throw new Problem(401, 'authentication', 'Conectează-te la cont.'); }
                ApiInput::validate($data, $route['body']);
                ProgramController::authorizeReplay($action, $user ?? [], $data);
                if ($user) { ProgramController::authorizeResource($action, $user, $route['resource_id']); }
                $run = static fn(): array => ProgramService::run($action, $user, $route['resource_id'], $data, $route['parameters']);
                $external = ['register', 'company_check', 'verification_request', 'asset_upload', 'partner_refresh', 'admin_worker', 'password_change', 'access_revoke'];
                $result = $user && !in_array($action, $external, true) ? ProgramAccess::mutation($user, ['operation' => $action, 'input' => $data], $run) : $run();
                if ($action === 'company_check') { $check = $result; }
                else {
                    Session::flash(match ($action) { 'verification_request' => 'Am trimis mesajul de confirmare. Verifică și dosarul Spam.', 'practice' => $result['feedback'], 'application_submit' => 'Cererea a fost trimisă pentru verificare.', default => 'Modificarea a fost salvată.' });
                    $next = match ($action) {
                        'register' => $result['next'], 'enroll' => '/invatare/inscrieri/' . $result['id'], 'attempt_start' => '/invatare/examene/' . $result['id'],
                        'attempt_submit' => '/invatare/rezultate/' . $result['id'], 'lab_create' => '/invatare/probe/' . $result['id'],
                        'application_create' => '/parteneri/cereri/' . $result['id'], 'contributor_create' => '/contribuitori/cont/' . $result['id'],
                        'admin_course_create' => '/admin/program/courses/' . $result['id'], 'admin_bank_create' => '/admin/program/banks/' . $result['id'],
                        default => $_SERVER['REQUEST_URI'],
                    };
                    Http::redirect($next);
                }
            } catch (Problem $failure) { $error = $failure->getMessage(); http_response_code($failure->status); }
        }
        $screen = ''; $model = []; $title = '';
        if ($path === '/contribuitori') { $screen = 'contributors'; $title = 'Oamenii din spatele OpenAliment'; $model = Contributors::catalog(); }
        elseif ($path === '/parteneri') { $screen = 'partners'; $title = 'Parteneri autorizați OpenAliment'; $model = ProgramPartners::catalog(); }
        elseif ($path === '/invatare') { $screen = 'courses'; $title = 'Învață să integrezi OpenAliment'; $model = ProgramLearning::catalog(); }
        elseif ($path === '/program/confirmare') { $screen = 'confirm'; $title = 'Confirmă solicitarea'; Session::start(); $_SESSION['application_return'] = '/program/confirmare'; }
        elseif ($path === '/invatare/inscriere') { $screen = 'registration'; $title = 'Creează contul tău'; Session::start(); if (($_GET['confirmare'] ?? '') === '1') { $_SESSION['application_return'] = '/program/confirmare'; } if ($user) { Http::redirect('/invatare/cont'); } }
        elseif (preg_match('~\A/(contribuitori|parteneri)/([a-f0-9]{32})\z~D', $path, $m)) {
            $screen = $m[1] === 'contribuitori' ? 'contributor-public' : 'partner-public';
            $model = $m[1] === 'contribuitori' ? Contributors::profile($m[2]) : ProgramPartners::profile($m[2]);
            $title = $model['profile']['name'] ?? $model['name'];
        } elseif (preg_match('~\A/invatare/(calificari|cursuri)/([a-f0-9]{32})\z~D', $path, $m)) {
            $screen = $m[1] === 'calificari' ? 'credential' : 'course';
            $model = $screen === 'credential' ? ProgramQueries::credential($m[2]) : ProgramLearning::course($m[2]);
            $title = $screen === 'credential' ? 'Verificarea calificării' : $model['manifest']['title'];
        } elseif (preg_match('~\A/parteneri/autorizari/([a-f0-9]{32})\z~D', $path, $m)) {
            $screen = 'authorization'; $title = 'Verificarea autorizării'; $model = ProgramQueries::authorization($m[1]);
        } elseif (preg_match('~\A/invatare/editii/([a-f0-9]{32})/lectii/([a-f0-9]{32})\z~D', $path, $m)) {
            $screen = 'lesson'; $model = ProgramLearning::lesson($m[1], $m[2]); $title = $model['title'];
            if (isset($_GET['inscriere'])) {
                $user = self::user('/invatare/cont');
                $enrollment = ProgramLearning::enrollment($user, Api::id((string) $_GET['inscriere']));
                if ($enrollment['release_id'] !== $m[1] || !in_array($m[2], array_column($enrollment['lessons'], 'id'), true)) { throw new Problem(404, 'lesson', 'Lecția nu aparține înscrierii.'); }
                $model['enrollment'] = $enrollment;
            }
        } elseif (preg_match('~\A/(contribuitori|parteneri|invatare)/cont(?:/([a-f0-9]{32}))?\z~D', $path, $m)) {
            $user = self::user('/' . $m[1] . '/cont');
            if ($m[1] === 'contribuitori') {
                $screen = 'contributor-own'; $title = 'Profilul meu de contribuitor';
                $model = isset($m[2]) ? Contributors::projection(ProgramAccess::owned('contributor_profiles', $m[2], $user), true) : ['items' => Contributors::own($user)['items']];
            } elseif ($m[1] === 'parteneri') { $screen = 'partner-hub'; $title = 'Participarea firmei tale'; $model = ProgramPartners::own($user); }
            else { $screen = 'learning-hub'; $title = 'Învățarea mea'; $model = ProgramQueries::me($user) + ['enrollments' => ProgramQueries::enrollments($user), 'credentials' => ProgramQueries::credentials($user), 'results' => ProgramQueries::results($user), 'labs' => ProgramQueries::labs($user)]; }
        } elseif ($path === '/invatare/aplicatii') {
            $user = self::user('/invatare/cont'); $screen = 'applications'; $title = 'Aplicațiile și accesurile mele';
            $model = ['clients' => \App\Domain\ProgramApplications::clients($user), 'grants' => \App\Domain\ProgramApplications::grants($user)];
        } elseif ($path === '/parteneri/inscriere') {
            $user = self::user('/parteneri/cont'); $screen = 'partner-register'; $title = 'Înscrie firma în program'; $model = ['check' => $check];
        } elseif (preg_match('~\A/parteneri/cereri/([a-f0-9]{32})\z~D', $path, $m)) {
            $user = self::user('/parteneri/cont'); $screen = 'partner-application'; $title = 'Cererea firmei'; $model = ProgramPartners::application($user, $m[1]);
        } elseif (preg_match('~\A/invatare/(inscrieri|examene|rezultate|probe)/([a-f0-9]{32})\z~D', $path, $m)) {
            $user = self::user('/invatare/cont');
            [$screen, $title, $model] = match ($m[1]) {
                'inscrieri' => ['enrollment', 'Continuă cursul', ProgramLearning::enrollment($user, $m[2])],
                'examene' => ['exam', 'Evaluare teoretică', ProgramAssessments::attempt($user, $m[2])],
                'rezultate' => ['result', 'Rezultatul evaluării', ProgramAssessments::result($user, $m[2])],
                'probe' => ['lab', 'Proba practică', ProgramLabs::view($user, $m[2])],
            };
        } elseif (preg_match('~\A/admin/program(?:/(contributors|partners|courses|banks|labs|results|credentials|enrollments|staff))?(?:/([a-f0-9]{32}))?\z~D', $path, $m)) {
            $user = self::user('/invatare/cont'); $screen = 'admin'; $title = 'Programul OpenAliment';
            $kind = $m[1] ?? ''; $id = $m[2] ?? '';
            if ($kind === '') {
                $allowed = []; foreach (ProgramAccess::ROLES as $role) { try { ProgramAccess::staff($user, $role); $allowed[] = $role; } catch (Problem) {} }
                if (!$allowed) { throw new Problem(403, 'program_role', 'Nu ai un rol de administrare a programului.'); }
                $model = ['kind' => '', 'roles' => $allowed];
            } else {
                $action = $id ? ['contributors' => 'admin_contributors', 'partners' => 'admin_partner_get', 'courses' => 'admin_course_get', 'banks' => 'admin_bank_get', 'labs' => 'admin_lab_get', 'results' => 'admin_result_get', 'credentials' => 'admin_credentials', 'staff' => 'admin_staff'][$kind] : 'admin_' . $kind;
                $model = ['kind' => $kind, 'resource_id' => $id, 'resource' => ProgramService::run($action, $user, $id)];
                if ($kind === 'contributors' && $id) {
                    ProgramAccess::staff($user, 'community_editor');
                    $r = Db::one('SELECT * FROM contributor_profiles WHERE id=?', [$id]) ?? throw new Problem(404, 'contributor', 'Profilul nu a fost găsit.');
                    $model['resource'] = Contributors::projection($r, true);
                }
            }
        } else { throw new Problem(404, 'program_page', 'Pagina nu a fost găsită.'); }
        Http::view('program/index', ['title' => $title, 'programPage' => true, 'screen' => $screen, 'model' => $model, 'programUser' => $user,
            'programError' => $error, 'values' => $values], $error ? http_response_code() : 200);
    }

    private static function user(string $return): array
    {
        Session::start(); $_SESSION['application_return'] = $return;
        return ProgramAccess::user();
    }

    private static function form(): array
    {
        $post = Http::input(2900000); $verb = $post['_method'] ?? 'POST'; $endpoint = $post['_endpoint'] ?? '';
        if (!is_string($verb) || !in_array($verb, ['POST', 'PUT', 'DELETE'], true) || !is_string($endpoint)) { throw new Problem(422, 'form', 'Formular incorect.'); }
        $route = ApiRoutes::find($verb, $endpoint);
        if (!$route || !str_starts_with($route['handler'], 'program.') || $route['auth'] === 'public') { throw new Problem(422, 'form', 'Acțiune necunoscută.'); }
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = is_string($post['_key'] ?? null) ? $post['_key'] : '';
        unset($post['_method'], $post['_endpoint'], $post['_key'], $post['_csrf']);
        if ($route['handler'] === 'program.company_check') { $post = array_intersect_key($post, ['cui' => true]); }
        foreach (['expected_revision', 'assessment_seconds', 'identity_points', 'history_points', 'security_points', 'explanation_points'] as $key) {
            if (isset($post[$key]) && is_string($post[$key]) && preg_match('/\A[0-9]{1,6}\z/D', $post[$key])) { $post[$key] = (int) $post[$key]; }
        }
        foreach (['consent', 'completed', 'policy_accepted', 'rights_confirmed', 'reviewed', 'representation_verified', 'end_previous_affiliation', 'mandatory_checks', 'physical_proof_reviewed', 'public_name', 'public_consent', 'company_confirmed', 'accept_existing_company'] as $key) {
            if (isset($post[$key])) { $post[$key] = $post[$key] === '1'; }
        }
        foreach (['website', 'asset_id', 'previous_profile_id', 'evidence_asset_id'] as $key) { if (($post[$key] ?? null) === '') { $post[$key] = null; } }
        foreach (['manifest', 'bank', 'payload', 'package'] as $key) {
            if (isset($post[$key]) && is_string($post[$key])) {
                try { $post[$key] = json_decode($post[$key], true, 40, JSON_THROW_ON_ERROR); } catch (\JsonException) { throw new Problem(422, 'program_json', 'Documentul JSON este incorect.'); }
            }
        }
        foreach (['services', 'regions', 'languages', 'links', 'invalid_question_ids', 'redirect_uris'] as $key) {
            if (isset($post[$key]) && is_string($post[$key])) { $post[$key] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $post[$key])))); }
        }
        if ($route['body'] === 'ProgramPractice' && isset($post['answer']) && ctype_digit((string) $post['answer'])) { $post['answer'] = (int) $post['answer']; }
        return [$route, $post];
    }

    private static function examSubmit(string $path): never
    {
        if (!preg_match('~\A/invatare/examene/([a-f0-9]{32})\z~D', $path, $m)) { throw new Problem(422, 'exam', 'Formular de examen incorect.'); }
        $user = ProgramAccess::actor('learning:write', true); $data = Http::input(100000);
        $result = Db::atomic(static function () use ($user, $m, $data): array {
            Db::one('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]);
            $attempt = ProgramAssessments::attempt($user, $m[1]);
            if ($attempt['state'] === 'in_progress' && $attempt['due_at'] > Db::now()) {
                foreach ($attempt['items'] as $item) {
                    $answer = $data['answers'][$item['id']] ?? null;
                    if ($answer === null) { continue; }
                    $expected = $data['revisions'][$item['id']] ?? null;
                    if (!is_string($expected) || !ctype_digit($expected)) { throw new Problem(422, 'exam_revision', 'Revizie de răspuns incorectă.'); }
                    ProgramAssessments::answer($user, $m[1], $item['id'], ['expected_revision' => (int) $expected, 'answer' => $answer]);
                }
            }
            return ProgramAssessments::submit($user, $m[1]);
        });
        Http::redirect('/invatare/rezultate/' . $result['id']);
    }
}
