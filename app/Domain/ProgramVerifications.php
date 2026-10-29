<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Config, Db, Mailer, Passwords, Problem, RateLimit, Session};

/** One-use, revision-bound proofs. Links show a preview; only a deliberate POST consumes them. */
final class ProgramVerifications
{
    public static function register(array $data): array
    {
        if (!RegistrationSettings::enabled()) {
            throw new Problem(403, 'registration', 'Înscrierile sunt disponibile prin administrator.');
        }
        RateLimit::check('program-registration|' . Api::clientIp(), 5, 3600);
        \App\Core\SecurityBudget::check('registrations_hour', 100);
        $email = self::email($data['email'] ?? null);
        $password = Passwords::hash(Passwords::validate($data['password'] ?? null));
        $name = ProgramAccess::text($data, 'display_name', 120);
        if (($data['policy_accepted'] ?? false) !== true || Db::one('SELECT id FROM users WHERE email=?', [$email])) {
            throw new Problem(409, 'program_registration', 'Contul nu poate fi creat cu aceste date. Dacă ai un cont, conectează-te.');
        }
        $id = Db::id();
        Db::insert('users', ['id' => $id, 'email' => $email, 'display_name' => $name, 'password_hash' => $password, 'created_at' => Db::now()]);
        ProgramAccess::event(['id' => $id], 'account', $id, 'registered', ['policy_version' => ProgramLearning::POLICY]);
        $return = $_SESSION['oauth_return'] ?? $_SESSION['application_return'] ?? '';
        $next = in_array($return, ['/program/confirmare', '/contribuitori/cont', '/parteneri/cont', '/invatare/cont'], true)
            || is_string($return) && preg_match('~\A/oauth/authorize\?request=[a-f0-9]{32}\z~D', $return) ? $return : '/invatare/cont';
        Session::login($id);
        return ['id' => $id, 'email_verified' => false, 'next' => $next];
    }

    public static function email(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 190 || !filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $value)) {
            throw new Problem(422, 'email', 'Adresa de email este incorectă.');
        }
        return strtolower(trim($value));
    }

    public static function request(array $user, array $data): array
    {
        $purpose = ProgramAccess::text($data, 'purpose', 40);
        $object = $data['object_id'] ?? $user['id'];
        $evidence = [];
        if ($purpose === 'personal_email') {
            $object = $user['id']; $email = $user['email'];
            $evidence['return_to'] = in_array($_SESSION['application_return'] ?? '', ['/contribuitori/cont', '/parteneri/cont', '/invatare/cont'], true) ? $_SESSION['application_return'] : '/invatare/cont';
        } elseif ($purpose === 'partner_contact') {
            $contact = Db::one('SELECT c.*,a.user_id,a.profile_id FROM partner_contacts c JOIN partner_applications a ON a.id=c.application_id WHERE c.id=? AND a.user_id=? AND c.withdrawn_at IS NULL', [Api::id($object), $user['id']]);
            if (!$contact || $contact['kind'] !== 'email') {
                throw new Problem(404, 'partner_contact', 'Contactul nu a fost găsit.');
            }
            if ($contact['profile_id']) { ProgramPartners::manage($user, $contact['profile_id']); }
            $email = $contact['value_text']; $evidence['revision'] = (int) $contact['revision'];
        } elseif ($purpose === 'contributor_consent') {
            ProgramAccess::staff($user, 'community_editor');
            $profile = Db::one('SELECT * FROM contributor_profiles WHERE id=? AND user_id IS NULL', [Api::id($object)])
                ?? throw new Problem(404, 'contributor', 'Profilul nu a fost găsit.');
            $email = self::email($data['email'] ?? null); $evidence['revision'] = (int) $profile['revision'];
        } elseif ($purpose === 'partner_invite') {
            ProgramPartners::manage($user, Api::id($object));
            $email = self::email($data['email'] ?? null);
        } else {
            throw new Problem(422, 'verification_purpose', 'Verificare necunoscută.');
        }
        RateLimit::check('program-mail-user|' . $user['id'], 6, 3600);
        RateLimit::check('program-mail-address|' . hash('sha256', $email), 6, 3600);
        if (!Mailer::ready()) {
            throw new Problem(503, 'program_mail', 'Trimiterea emailurilor nu este disponibilă momentan.');
        }
        $id = Db::id(); $token = bin2hex(random_bytes(32));
        Db::atomic(static function () use ($id, $user, $purpose, $object, $email, $evidence, $token): void {
            Db::run("UPDATE program_verifications SET state='superseded' WHERE user_id=? AND purpose=? AND object_id=? AND state='pending'", [$user['id'], $purpose, $object]);
            Db::insert('program_verifications', ['id' => $id, 'user_id' => $user['id'], 'purpose' => $purpose, 'object_id' => $object,
                'value_text' => $email, 'token_hash' => hash('sha256', $token), 'evidence_json' => Db::json($evidence),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800), 'created_at' => Db::now()]);
        });
        try {
            Mailer::securityMessage($email, 'Confirmare · OpenAliment', "Ai primit o solicitare de confirmare pentru programul OpenAliment.\nDeschide pagina pentru a vedea solicitarea și a decide dacă o accepți:\n\n"
                . rtrim((string) Config::get('url'), '/') . '/program/confirmare#token=' . $token
                . "\n\nLegătura expiră în 30 de minute. Deschiderea ei nu confirmă automat nimic. Dacă nu recunoști solicitarea, ignoră mesajul.\n");
        } catch (\Throwable $error) {
            Db::run("UPDATE program_verifications SET state='failed' WHERE id=?", [$id]);
            throw $error;
        }
        return ['id' => $id, 'state' => 'sent', 'expires_in' => 1800];
    }

    private static function pending(array $data): array
    {
        $token = $data['token'] ?? '';
        if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            throw new Problem(422, 'verification_token', 'Legătură de confirmare incorectă.');
        }
        return Db::one("SELECT * FROM program_verifications WHERE token_hash=? AND state='pending' AND expires_at>?", [hash('sha256', $token), Db::now()])
            ?? throw new Problem(410, 'verification_expired', 'Legătura a expirat sau a fost folosită. Cere o confirmare nouă.');
    }

    public static function preview(array $data): array
    {
        $r = self::pending($data); $details = null;
        if ($r['purpose'] === 'contributor_consent') {
            $profile = Db::one('SELECT * FROM contributor_profiles WHERE id=?', [$r['object_id']]);
            $details = $profile ? json_decode($profile['draft_json'], true) : null;
        } elseif ($r['purpose'] === 'partner_invite') {
            $p = Db::one('SELECT v.snapshot_json FROM partner_profiles p JOIN partner_profile_versions v ON v.id=p.current_version_id WHERE p.id=?', [$r['object_id']]);
            $details = $p ? ['company' => json_decode($p['snapshot_json'], true)['legal_name']] : null;
        }
        return ['purpose' => $r['purpose'], 'expires_at' => $r['expires_at'], 'details' => $details];
    }

    public static function confirm(?array $user, array $data): array
    {
        if (($data['accepted'] ?? false) !== true) {
            throw new Problem(422, 'confirmation', 'Confirmă explicit solicitarea.');
        }
        return Db::atomic(static function () use ($user, $data): array {
            $r = self::pending($data);
            Db::one('SELECT id FROM program_verifications WHERE id=? FOR UPDATE', [$r['id']]);
            $r = self::pending($data);
            $proof = json_decode($r['evidence_json'], true);
            $next = '/invatare/cont';
            if (in_array($r['purpose'], ['personal_email', 'partner_contact'], true) && (!$user || $user['id'] !== $r['user_id'])) {
                throw new Problem(403, 'verification_account', 'Conectează-te la contul care a cerut verificarea.');
            }
            if ($r['purpose'] === 'personal_email') {
                if (!hash_equals($user['email'], $r['value_text'])) {
                    throw new Problem(409, 'verification_changed', 'Adresa contului s-a modificat.');
                }
                Db::run('UPDATE users SET email_verified=1 WHERE id=?', [$user['id']]);
                $next = $proof['return_to'] ?? '/invatare/cont';
            } elseif ($r['purpose'] === 'partner_contact') {
                $application = Db::one('SELECT a.id,a.profile_id FROM partner_applications a JOIN partner_contacts c ON c.application_id=a.id WHERE c.id=? AND a.user_id=?', [$r['object_id'], $user['id']])
                    ?? throw new Problem(404, 'partner_contact', 'Contactul nu a fost găsit.');
                if ($application['profile_id']) { ProgramPartners::manage($user, $application['profile_id']); }
                $changed = Db::run('UPDATE partner_contacts SET verified_at=?,expires_at=? WHERE id=? AND revision=? AND value_text=? AND withdrawn_at IS NULL',
                    [Db::now(), gmdate('Y-m-d H:i:s', time() + 365 * 86400), $r['object_id'], $proof['revision'], $r['value_text']])->rowCount();
                if (!$changed) {
                    throw new Problem(409, 'verification_changed', 'Contactul s-a modificat. Cere o confirmare nouă.');
                }
                ProgramPartners::reconcileFor($user['id']);
                if (hash_equals($user['email'], $r['value_text'])) { Db::run('UPDATE users SET email_verified=1 WHERE id=?', [$user['id']]); }
                $next = '/parteneri/cereri/' . $application['id'];
            } elseif ($r['purpose'] === 'contributor_consent') {
                $changed = Db::run("UPDATE contributor_profiles SET consent_at=?,consent_version=revision,submitted_revision=revision,state='submitted' WHERE id=? AND revision=? AND user_id IS NULL AND state<>'suspended'",
                    [Db::now(), $r['object_id'], $proof['revision']])->rowCount();
                if (!$changed) {
                    throw new Problem(409, 'verification_changed', 'Profilul s-a modificat. Cere o confirmare nouă.');
                }
                $next = '/contribuitori';
            } elseif ($r['purpose'] === 'partner_invite') {
                if (!$user || $user['email'] !== $r['value_text']) {
                    throw new Problem(403, 'invitation_account', 'Conectează-te cu adresa care a primit invitația.');
                }
                ProgramPartners::manage(['id' => $r['user_id']], $r['object_id']);
                ProgramPartners::affiliate($r['user_id'], $r['object_id'], $user['id']);
                Db::run('UPDATE users SET email_verified=1 WHERE id=?', [$user['id']]);
                $next = '/parteneri/cont';
            } else {
                throw new Problem(422, 'verification_purpose', 'Această verificare nu se confirmă prin email.');
            }
            Db::run("UPDATE program_verifications SET state='confirmed',consumed_at=? WHERE id=?", [Db::now(), $r['id']]);
            return ['id' => $r['id'], 'purpose' => $r['purpose'], 'state' => 'confirmed', 'next' => $next];
        });
    }
}
