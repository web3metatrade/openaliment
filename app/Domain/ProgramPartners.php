<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem, RateLimit};

/** Company authorization is separate from personal qualification, producer rights and GS1 accreditation. */
final class ProgramPartners
{
    /** Registration has just confirmed ANAF and ownership in the same browser flow. */
    public static function fromRegisteredTenant(array $user, string $tenant): array
    {
        return Db::atomic(static function () use ($user, $tenant): array {
            Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE', [$tenant]);
            $legal = Db::one("SELECT p.current_version_id FROM organization_legal_profiles p JOIN memberships m ON m.tenant_id=p.tenant_id AND m.user_id=? AND m.role='owner' WHERE p.tenant_id=?", [$user['id'], $tenant]);
            if (!$legal) { throw new Problem(403, 'company_confirmation', 'Firma confirmată nu este asociată contului.'); }
            $old = Db::one("SELECT id FROM partner_applications WHERE user_id=? AND tenant_id=? AND state='draft' LIMIT 1", [$user['id'], $tenant]);
            if ($old) { return self::application($user, $old['id']); }
            $id = Db::id();
            Db::insert('partner_applications', ['id' => $id, 'user_id' => $user['id'], 'tenant_id' => $tenant, 'legal_version_id' => $legal['current_version_id'],
                'draft_json' => Db::json(['description' => '', 'services' => [], 'regions' => [], 'languages' => ['ro'], 'website' => null, 'asset_id' => null]), 'created_at' => Db::now()]);
            ProgramAccess::event($user, 'partner_application', $id, 'created', ['source' => 'confirmed_registration']);
            return self::application($user, $id);
        });
    }

    public static function check(array $user, array $data): array
    {
        RateLimit::check('program-anaf|' . $user['id'], 6, 900);
        $cui = AnafClient::cui($data['cui'] ?? null);
        // Invalidate the previous selection before a network request can complete.
        Db::run("UPDATE program_verifications SET state='superseded' WHERE user_id=? AND purpose='company_check' AND state IN ('pending','active')", [$user['id']]);
        $id = Db::id();
        Db::insert('program_verifications', ['id' => $id, 'user_id' => $user['id'], 'purpose' => 'company_check', 'value_text' => $cui,
            'evidence_json' => '{}', 'expires_at' => gmdate('Y-m-d H:i:s', time() + 1200), 'created_at' => Db::now()]);
        $observation = OrganizationRegistry::observe($cui);
        if (Db::run("UPDATE program_verifications SET state='active',observation_id=? WHERE id=? AND state='pending'", [$observation['id'], $id])->rowCount() !== 1) {
            throw new Problem(409, 'company_changed', 'CUI-ul a fost modificat. Folosește ultima verificare.');
        }
        return self::checkResult($user, $id);
    }

    public static function checkResult(array $user, string $id): array
    {
        $r = Db::one("SELECT * FROM program_verifications WHERE id=? AND user_id=? AND purpose='company_check' AND state='active' AND expires_at>?", [Api::id($id), $user['id'], Db::now()])
            ?? throw new Problem(409, 'company_check', 'Reverifică CUI-ul firmei.');
        return OrganizationRegistry::projectObservation(Db::one('SELECT * FROM anaf_observations WHERE id=?', [$r['observation_id']])) + ['id' => $id, 'expires_at' => $r['expires_at']];
    }

    public static function create(array $user, array $data): array
    {
        $checkId = Api::id(ProgramAccess::text($data, 'company_check_id', 32));
        $check = self::checkResult($user, $checkId);
        if ($check['state'] !== 'found' || $check['cui'] !== AnafClient::cui($data['cui'] ?? null) || ($data['company_confirmed'] ?? false) !== true) {
            throw new Problem(422, 'company_confirmation', 'Confirmă firma afișată după verificarea CUI-ului.');
        }
        if ((int) Db::one('SELECT COUNT(*) AS n FROM partner_applications WHERE user_id=?', [$user['id']])['n'] >= 10) {
            throw new Problem(422, 'partner_quota', 'Limita de cereri a fost atinsă.');
        }
        $previous = $data['previous_profile_id'] ?? null;
        if ($previous !== null) {
            self::manage($user, Api::id($previous));
            if (Db::one('SELECT cui FROM partner_profiles WHERE id=?', [$previous])['cui'] === $check['cui']) {
                throw new Problem(422, 'company_unchanged', 'CUI-ul este același. Actualizează prezentarea firmei existente.');
            }
        }
        $verification = Db::one('SELECT * FROM program_verifications WHERE id=? FOR UPDATE', [$checkId]);
        $existing = Db::one('SELECT * FROM partner_profiles WHERE cui=?', [$check['cui']]);
        // A private duplicate claim does not reserve or grant access to the verified company.
        $legal = Db::one("SELECT p.* FROM organization_legal_profiles p JOIN memberships m ON m.tenant_id=p.tenant_id AND m.role='owner' WHERE p.cui=? AND m.user_id=? LIMIT 1", [$check['cui'], $user['id']]);
        $tenant = $legal['tenant_id'] ?? Db::id();
        if (!$legal) {
            Db::insert('organizations', ['id' => $tenant, 'display_name' => mb_substr($check['record']['date_generale']['denumire'], 0, 180), 'created_at' => Db::now()]);
            Db::insert('memberships', ['tenant_id' => $tenant, 'user_id' => $user['id'], 'role' => 'owner']);
        }
        OrganizationRegistry::attachObservation($tenant, $user['id'], $verification['observation_id']);
        $version = Db::one('SELECT current_version_id FROM organization_legal_profiles WHERE tenant_id=?', [$tenant])['current_version_id'];
        $id = Db::id();
        Db::insert('partner_applications', ['id' => $id, 'user_id' => $user['id'], 'tenant_id' => $tenant, 'legal_version_id' => $version,
            'previous_profile_id' => $previous, 'draft_json' => Db::json(['description' => '', 'services' => [], 'regions' => [], 'languages' => ['ro'], 'website' => null, 'asset_id' => null]), 'created_at' => Db::now()]);
        Db::run("UPDATE program_verifications SET state='consumed',consumed_at=? WHERE id=?", [Db::now(), $checkId]);
        ProgramAccess::event($user, 'partner_application', $id, 'created', ['cui' => $check['cui'], 'previous_profile_id' => $previous]);
        return self::application($user, $id);
    }

    public static function application(array $user, string $id, bool $review = false): array
    {
        if ($review) {
            ProgramAccess::staff($user, 'partner_reviewer');
            $r = Db::one('SELECT * FROM partner_applications WHERE id=?', [Api::id($id)]);
        } else {
            $r = ProgramAccess::owned('partner_applications', $id, $user);
        }
        if (!$r) {
            throw new Problem(404, 'partner_application', 'Cererea nu a fost găsită.');
        }
        $v = Db::one('SELECT * FROM organization_legal_profile_versions WHERE id=?', [$r['legal_version_id']]);
        $observation = Db::one('SELECT * FROM anaf_observations WHERE id=?', [$v['observation_id']]);
        $source = OrganizationRegistry::projectObservation($observation);
        $out = ['id' => $id, 'tenant_id' => $r['tenant_id'], 'profile_id' => $r['profile_id'], 'previous_profile_id' => $r['previous_profile_id'],
            'revision' => (int) $r['revision'], 'state' => $r['state'], 'identity_verified' => (bool) $r['identity_verified'],
            'presentation' => json_decode($r['draft_json'], true), 'company' => $source, 'contacts' => self::contacts($id),
            'decision' => $r['decision_json'] ? json_decode($r['decision_json'], true) : null,
            'requirements' => $r['profile_id'] ? self::requirements($r['profile_id']) : ['approved_profile_required']];
        if ($review) {
            $applicant = Db::one('SELECT id,display_name,email,email_verified FROM users WHERE id=?', [$r['user_id']]);
            $out['applicant'] = ['id' => $applicant['id'], 'name' => $applicant['display_name'], 'email' => $applicant['email'], 'email_verified' => (bool) $applicant['email_verified']];
            $existing = Db::one('SELECT id,cui,public_name FROM partner_profiles WHERE cui=?', [$source['cui']]);
            $out['existing_profile'] = $existing ? ['id' => $existing['id'], 'cui' => $existing['cui'], 'name' => $existing['public_name'] ?? ''] : null;
        }
        return $out;
    }

    public static function own(array $user): array
    {
        return ['items' => array_map(static fn(array $r): array => self::application($user, $r['id']), Db::all('SELECT id FROM partner_applications WHERE user_id=? ORDER BY created_at DESC LIMIT 25', [$user['id']]))];
    }

    public static function save(array $user, string $id, array $data): array
    {
        $row = ProgramAccess::owned('partner_applications', $id, $user);
        self::changeAllowed($user, $row);
        ProgramAccess::revision($row, $data);
        if (in_array($row['state'], ['withdrawn', 'suspended'], true)) {
            throw new Problem(409, 'partner_state', 'Cererea necesită intervenția administratorului.');
        }
        $profile = ['description' => ProgramAccess::text($data, 'description', 4000), 'services' => $data['services'] ?? [],
            'regions' => $data['regions'] ?? [], 'languages' => $data['languages'] ?? ['ro'], 'website' => $data['website'] ?? null, 'asset_id' => $data['asset_id'] ?? null];
        foreach (['services', 'regions', 'languages'] as $key) {
            if (!is_array($profile[$key]) || !array_is_list($profile[$key]) || count($profile[$key]) > 20) {
                throw new Problem(422, 'partner_presentation', 'Lista serviciilor, regiunilor sau limbilor este incorectă.');
            }
            foreach ($profile[$key] as $item) {
                if (!is_string($item) || trim($item) === '' || mb_strlen($item) > 120) {
                    throw new Problem(422, 'partner_presentation', 'Completează denumiri de cel mult 120 de caractere.');
                }
            }
        }
        if (!$profile['services']) {
            throw new Problem(422, 'partner_services', 'Adaugă cel puțin un serviciu.');
        }
        if ($profile['website'] !== null) {
            $profile['website'] = Marketplace::webUrl($profile['website']);
        }
        if ($profile['asset_id'] !== null) {
            ProgramMedia::owned($user, Api::id($profile['asset_id']), 'logo');
        }
        Db::run("UPDATE partner_applications SET draft_json=?,revision=revision+1,state='draft' WHERE id=?", [Db::json($profile), $id]);
        ProgramAccess::event($user, 'partner_application', $id, 'saved', ['revision' => (int) $row['revision'] + 1, 'presentation' => $profile]);
        return self::application($user, $id);
    }

    public static function contact(array $user, string $id, array $data): array
    {
        $app = ProgramAccess::owned('partner_applications', $id, $user);
        self::changeAllowed($user, $app);
        ProgramAccess::revision($app, $data);
        $kind = ProgramAccess::text($data, 'kind', 20);
        $value = $kind === 'email' ? ProgramVerifications::email($data['value'] ?? null) : ProgramAccess::text($data, 'value', 40);
        if (!in_array($kind, ['email', 'phone'], true) || ($kind === 'phone' && !preg_match('/\A\+?[0-9 ()-]{7,30}\z/D', $value))) {
            throw new Problem(422, 'partner_contact', 'Folosește o adresă email sau un număr de telefon valid.');
        }
        if (($data['public_consent'] ?? false) !== true) {
            throw new Problem(422, 'partner_contact_consent', 'Confirmă publicarea contactului companiei.');
        }
        Db::run('UPDATE partner_contacts SET withdrawn_at=?,revision=revision+1 WHERE application_id=? AND kind=? AND withdrawn_at IS NULL', [Db::now(), $id, $kind]);
        Db::insert('partner_contacts', ['id' => Db::id(), 'application_id' => $id, 'kind' => $kind, 'value_text' => $value,
            'public_consent' => 1, 'created_at' => Db::now()]);
        Db::run('UPDATE partner_applications SET revision=revision+1 WHERE id=?', [$id]);
        ProgramAccess::event($user, 'partner_application', $id, 'contact_changed', ['kind' => $kind]);
        return self::application($user, $id);
    }

    public static function contacts(string $id): array
    {
        return array_map(static fn(array $r): array => ['id' => $r['id'], 'kind' => $r['kind'], 'value' => $r['value_text'],
            'revision' => (int) $r['revision'], 'source' => $r['source_kind'], 'verified' => $r['verified_at'] !== null && $r['expires_at'] > Db::now(),
            'public_consent' => (bool) $r['public_consent']], Db::all('SELECT * FROM partner_contacts WHERE application_id=? AND withdrawn_at IS NULL ORDER BY kind', [$id]));
    }

    public static function submit(array $user, string $id, array $data): array
    {
        $r = ProgramAccess::owned('partner_applications', $id, $user);
        self::changeAllowed($user, $r);
        ProgramAccess::revision($r, $data);
        $profile = json_decode($r['draft_json'], true);
        if (!$profile['description'] || !$profile['services'] || ($data['policy_accepted'] ?? false) !== true || !self::contacts($id)) {
            throw new Problem(422, 'partner_submission', 'Completează prezentarea și contactele, apoi acceptă condițiile programului.');
        }
        if (in_array($r['state'], ['withdrawn', 'suspended'], true)) {
            throw new Problem(409, 'partner_state', 'Cererea nu poate fi retrimisă în această stare.');
        }
        Db::run("UPDATE partner_applications SET state='submitted' WHERE id=?", [$id]);
        ProgramAccess::event($user, 'partner_application', $id, 'submitted');
        return self::application($user, $id);
    }

    public static function review(array $reviewer, string $id, array $data): array
    {
        ProgramAccess::staff($reviewer, 'partner_reviewer');
        $app = Db::one('SELECT * FROM partner_applications WHERE id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'partner_application', 'Cererea nu a fost găsită.');
        ProgramAccess::revision($app, $data);
        if ($app['user_id'] === $reviewer['id'] || Db::one('SELECT role FROM memberships WHERE user_id=? AND tenant_id=?', [$reviewer['id'], $app['tenant_id']])) {
            throw new Problem(403, 'independent_review', 'Reprezentarea companiei se verifică de o altă persoană, fără afiliere la aceasta.');
        }
        $action = ProgramAccess::text($data, 'action', 24);
        $reason = ProgramAccess::text($data, 'reason', 4000);
        if ($action === 'approve') {
            if ($app['state'] !== 'submitted' || ($data['representation_verified'] ?? false) !== true) {
                throw new Problem(422, 'partner_review', 'Verifică mandatul persoanei și versiunea trimisă înainte de aprobare.');
            }
            $legal = Db::one('SELECT v.*,p.cui FROM organization_legal_profile_versions v JOIN organization_legal_profiles p ON p.tenant_id=v.tenant_id WHERE v.id=?', [$app['legal_version_id']]);
            $observation = Db::one('SELECT * FROM anaf_observations WHERE id=?', [$legal['observation_id']]);
            $profile = $app['profile_id'] ? Db::one('SELECT * FROM partner_profiles WHERE id=? FOR UPDATE', [$app['profile_id']]) : null;
            if (!$profile) {
                $existing = Db::one('SELECT * FROM partner_profiles WHERE cui=? FOR UPDATE', [$legal['cui']]);
                if ($existing) {
                    if (($data['accept_existing_company'] ?? false) !== true) { throw new Problem(409, 'partner_duplicate', 'Firma are deja un profil. Confirmă explicit asocierea reprezentantului după verificarea mandatului.'); }
                    $profile = $existing;
                } else {
                    $pid = Db::id();
                    Db::insert('partner_profiles', ['id' => $pid, 'tenant_id' => $app['tenant_id'], 'cui' => $legal['cui'], 'slug' => 'ro-' . $legal['cui'],
                        'observation_id' => $observation['id'], 'fiscal_checked_at' => $observation['fetched_at'], 'fiscal_state' => self::fiscal($observation), 'created_at' => Db::now()]);
                    $profile = Db::one('SELECT * FROM partner_profiles WHERE id=?', [$pid]);
                }
            }
            if (Db::one('SELECT role FROM memberships WHERE user_id=? AND tenant_id=?', [$reviewer['id'], $profile['tenant_id']])
                || Db::one("SELECT id FROM partner_affiliations WHERE user_id=? AND profile_id=? AND state='active'", [$reviewer['id'], $profile['id']])) {
                throw new Problem(403, 'independent_review', 'Compania afiliată se verifică de alt evaluator.');
            }
            $presentation = json_decode($app['draft_json'], true);
            $snapshot = $presentation + ['legal_name' => $legal['legal_name'], 'cui' => $legal['cui'], 'country' => 'RO',
                'contact_ids' => array_column(self::contacts($id), 'id'), 'application_id' => $id];
            if ($presentation['asset_id']) {
                ProgramMedia::approve($presentation['asset_id']);
            }
            $versionId = Db::id();
            Db::insert('partner_profile_versions', ['id' => $versionId, 'profile_id' => $profile['id'], 'revision' => (int) $profile['revision'] + 1,
                'legal_version_id' => $legal['id'], 'snapshot_json' => Db::json($snapshot), 'state' => 'approved', 'reviewed_by' => $reviewer['id'], 'created_at' => Db::now()]);
            Db::run("UPDATE partner_profiles SET current_version_id=?,public_name=?,observation_id=?,fiscal_checked_at=?,fiscal_state=?,state='active',revision=revision+1 WHERE id=?", [$versionId, mb_substr($legal['legal_name'], 0, 190), $observation['id'], $observation['fetched_at'], self::fiscal($observation), $profile['id']]);
            Db::run("UPDATE partner_applications SET state='approved',identity_verified=1,reviewed_by=?,profile_id=?,decision_json=? WHERE id=?", [$reviewer['id'], $profile['id'], Db::json(['action' => $action, 'reason' => $reason, 'at' => Db::now()]), $id]);
            self::affiliate($reviewer['id'], $profile['id'], $app['user_id'], 'manager');
            if ($app['previous_profile_id'] && ($data['end_previous_affiliation'] ?? false) === true) {
                Db::run("UPDATE partner_affiliations SET state='ended',ended_at=? WHERE profile_id=? AND user_id=? AND state='active'", [Db::now(), $app['previous_profile_id'], $app['user_id']]);
            }
            self::reconcileFor($app['user_id']);
        } elseif (in_array($action, ['needs_changes', 'reject', 'suspend', 'withdraw'], true)) {
            $state = ['needs_changes' => 'needs_changes', 'reject' => 'rejected', 'suspend' => 'suspended', 'withdraw' => 'withdrawn'][$action];
            Db::run('UPDATE partner_applications SET state=?,decision_json=? WHERE id=?', [$state, Db::json(['action' => $action, 'reason' => $reason, 'at' => Db::now()]), $id]);
            if ($app['profile_id'] && in_array($action, ['suspend', 'withdraw'], true)) {
                Db::run('UPDATE partner_profiles SET state=? WHERE id=?', [$state, $app['profile_id']]);
            }
        } else {
            throw new Problem(422, 'partner_decision', 'Decizie necunoscută.');
        }
        ProgramAccess::event($reviewer, 'partner_application', $id, $action, ['reason' => $reason]);
        ProgramJobs::notice($app['user_id'], 'partner', $id, 'O decizie privind cererea companiei tale este disponibilă în programul OpenAliment.');
        return self::application($reviewer, $id, true);
    }

    public static function affiliate(string $verifier, string $profile, string $user, string $role = 'member'): void
    {
        $old = Db::one("SELECT id FROM partner_affiliations WHERE profile_id=? AND user_id=? AND state='active'", [$profile, $user]);
        if (!$old) {
            Db::insert('partner_affiliations', ['id' => Db::id(), 'profile_id' => $profile, 'user_id' => $user, 'role' => $role,
                'verified_by' => $verifier, 'started_at' => Db::now()]);
        }
        self::reconcileFor($user);
    }

    public static function manage(array $user, string $profile): array
    {
        return Db::one("SELECT * FROM partner_affiliations WHERE profile_id=? AND user_id=? AND role='manager' AND state='active'" . (Db::pdo()->inTransaction() ? ' FOR UPDATE' : ''), [Api::id($profile), $user['id']])
            ?? throw new Problem(403, 'partner_manager', 'Administrarea partenerului necesită un reprezentant verificat.');
    }

    public static function endAffiliation(array $user, string $id): array
    {
        $r = Db::one("SELECT * FROM partner_affiliations WHERE id=? AND state='active'", [Api::id($id)])
            ?? throw new Problem(404, 'affiliation', 'Afilierea nu a fost găsită.');
        if ($r['user_id'] !== $user['id']) {
            self::manage($user, $r['profile_id']);
        }
        Db::run("UPDATE partner_affiliations SET state='ended',ended_at=? WHERE id=?", [Db::now(), $id]);
        ProgramAccess::event($user, 'affiliation', $id, 'ended');
        return ['id' => $id, 'state' => 'ended'];
    }

    public static function fiscal(array $observation): string
    {
        $p = OrganizationRegistry::projectObservation($observation);
        if ($p['state'] !== 'found') {
            return 'unknown';
        }
        return (($p['record']['stare_inactiv']['statusInactivi'] ?? null) === true || !empty($p['record']['stare_inactiv']['dataRadiere'])) ? 'inactive' : 'active';
    }

    public static function requirements(string $id): array
    {
        $p = Db::one('SELECT * FROM partner_profiles WHERE id=?', [$id]);
        if (!$p || !$p['current_version_id']) {
            return ['approved_profile_required'];
        }
        $missing = [];
        if ($p['state'] !== 'active') { $missing[] = 'profile_inactive'; }
        if ($p['fiscal_state'] !== 'active') { $missing[] = 'company_inactive'; }
        if ($p['fiscal_checked_at'] < gmdate('Y-m-d H:i:s', time() - 30 * 86400)) { $missing[] = 'fiscal_refresh_required'; }
        $v = Db::one('SELECT * FROM partner_profile_versions WHERE id=?', [$p['current_version_id']]);
        $s = json_decode($v['snapshot_json'], true);
        $verifiedEmail = false;
        foreach ($s['contact_ids'] as $contact) {
            $c = Db::one('SELECT * FROM partner_contacts WHERE id=? AND withdrawn_at IS NULL AND verified_at IS NOT NULL AND expires_at>? AND public_consent=1', [$contact, Db::now()]);
            if ($c && $c['kind'] === 'email') { $verifiedEmail = true; }
        }
        if (!$verifiedEmail) { $missing[] = 'verified_company_email_required'; }
        if (!Db::one("SELECT c.id FROM learning_credentials c JOIN users u ON u.id=c.user_id AND u.active=1 JOIN partner_affiliations a ON a.user_id=c.user_id AND a.profile_id=? AND a.state='active' WHERE c.state='active' AND c.expires_at>? LIMIT 1", [$id, Db::now()])) {
            $missing[] = 'qualified_affiliate_required';
        }
        return $missing;
    }

    public static function reconcileFor(string $user): void
    {
        if (!Db::pdo()->inTransaction()) { Db::atomic(static function () use ($user): void { self::reconcileFor($user); }); return; }
        foreach (Db::all("SELECT DISTINCT profile_id FROM partner_affiliations WHERE user_id=? AND state='active'", [$user]) as $r) {
            $profile = $r['profile_id'];
            Db::one('SELECT id FROM partner_profiles WHERE id=? FOR UPDATE', [$profile]);
            if (self::requirements($profile)) { continue; }
            $reviewer = Db::one('SELECT v.reviewed_by FROM partner_profiles p JOIN partner_profile_versions v ON v.id=p.current_version_id WHERE p.id=?', [$profile])['reviewed_by'];
            foreach (Db::all("SELECT c.* FROM learning_credentials c JOIN users u ON u.id=c.user_id AND u.active=1 JOIN partner_affiliations a ON a.user_id=c.user_id AND a.profile_id=? AND a.state='active' WHERE c.state='active' AND c.expires_at>?", [$profile, Db::now()]) as $c) {
                if (Db::one("SELECT id FROM partner_authorizations WHERE profile_id=? AND specialization=? AND credential_id=?", [$profile, $c['specialization'], $c['id']])) { continue; }
                Db::insert('partner_authorizations', ['id' => Db::id(), 'public_id' => Db::id(), 'profile_id' => $profile,
                    'specialization' => $c['specialization'], 'credential_id' => $c['id'], 'policy_version' => ProgramLearning::POLICY,
                    'issued_at' => Db::now(), 'expires_at' => min($c['expires_at'], gmdate('Y-m-d H:i:s', time() + 365 * 86400)), 'reviewed_by' => $reviewer]);
            }
        }
    }

    public static function authorization(array $r): array
    {
        $qualified = Db::one("SELECT c.id FROM learning_credentials c JOIN users u ON u.id=c.user_id AND u.active=1 JOIN partner_affiliations a ON a.user_id=c.user_id AND a.profile_id=? AND a.state='active' WHERE c.id=? AND c.state='active' AND c.expires_at>?", [$r['profile_id'], $r['credential_id'], Db::now()]);
        $active = $r['state'] === 'active' && $r['expires_at'] > Db::now() && $qualified && !self::requirements($r['profile_id']);
        $company = Db::one('SELECT p.cui,v.snapshot_json FROM partner_profiles p JOIN partner_profile_versions v ON v.id=p.current_version_id WHERE p.id=?', [$r['profile_id']]);
        return ['id' => $r['public_id'], 'profile_id' => $r['profile_id'], 'specialization' => $r['specialization'],
            'state' => $active ? 'active' : ($r['state'] === 'active' ? 'unavailable' : $r['state']), 'issuer' => 'OpenAliment',
            'policy_version' => $r['policy_version'], 'issued_at' => $r['issued_at'], 'expires_at' => $r['expires_at'], 'gs1_accreditation' => false,
            'company' => ['cui' => $company['cui'], 'name' => json_decode($company['snapshot_json'], true)['legal_name']],
            'verification_url' => rtrim((string) \App\Core\Config::get('url'), '/') . '/parteneri/autorizari/' . $r['public_id']];
    }

    public static function profile(string $id): array
    {
        $r = Db::one('SELECT * FROM partner_profiles WHERE id=?', [Api::id($id)]);
        if (!$r || self::requirements($id)) {
            throw new Problem(404, 'partner', 'Partenerul nu are o autorizare publică activă.');
        }
        $authorizations = array_values(array_filter(array_map(self::authorization(...), Db::all('SELECT * FROM partner_authorizations WHERE profile_id=? ORDER BY issued_at DESC', [$id])), static fn(array $a): bool => $a['state'] === 'active'));
        if (!$authorizations) {
            throw new Problem(404, 'partner', 'Partenerul nu are o autorizare publică activă.');
        }
        $version = Db::one('SELECT * FROM partner_profile_versions WHERE id=?', [$r['current_version_id']]);
        $s = json_decode($version['snapshot_json'], true); $contacts = [];
        foreach ($s['contact_ids'] as $contact) {
            $c = Db::one('SELECT kind,value_text,source_kind FROM partner_contacts WHERE id=? AND withdrawn_at IS NULL AND verified_at IS NOT NULL AND expires_at>? AND public_consent=1', [$contact, Db::now()]);
            if ($c) { $contacts[] = ['kind' => $c['kind'], 'value' => $c['value_text'], 'source' => $c['source_kind'], 'verified' => true]; }
        }
        return ['id' => $id, 'slug' => $r['slug'], 'name' => $s['legal_name'], 'cui' => $r['cui'], 'country' => 'RO', 'description' => $s['description'],
            'services' => $s['services'], 'regions' => $s['regions'], 'languages' => $s['languages'], 'website' => $s['website'], 'asset_id' => $s['asset_id'],
            'contacts' => $contacts, 'authorizations' => $authorizations, 'fiscal_checked_at' => $r['fiscal_checked_at']];
    }

    public static function catalog(): array
    {
        [$limit, $cursor] = Api::page(); $items = [];
        $after = $cursor === '' ? '' : (Db::one('SELECT public_name FROM partner_profiles WHERE id=?', [$cursor])['public_name'] ?? '');
        $q = mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 100);
        $prefix = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $q) . '%';
        $track = is_string($_GET['specialization'] ?? null) ? mb_substr($_GET['specialization'], 0, 40) : '';
        $region = is_string($_GET['region'] ?? null) ? mb_substr($_GET['region'], 0, 120) : '';
        $rows = Db::all("SELECT p.id FROM partner_profiles p JOIN partner_profile_versions v ON v.id=p.current_version_id WHERE p.state='active' AND (p.public_name>? OR (p.public_name=? AND p.id>?)) AND p.public_name LIKE ? ESCAPE '=' AND (?='' OR JSON_CONTAINS(v.snapshot_json,?,'$.regions')) ORDER BY p.public_name,p.id LIMIT 501", [$after, $after, $cursor, $prefix, $region, Db::json($region)]);
        $next = null; $more = false; $overflow = false;
        foreach ($rows as $r) {
            $next = $r['id'];
            try {
                $item = self::profile($r['id']);
                if ($track === '' || in_array($track, array_column($item['authorizations'], 'specialization'), true)) { $items[] = $item; }
            } catch (Problem $e) { if ($e->status !== 404) { throw $e; } }
            if (count($items) > $limit) { $more = true; $overflow = true; array_pop($items); break; }
        }
        if (count($rows) === 501 && !$more) { $more = true; }
        return ['items' => $items, 'next_cursor' => $more ? ($overflow ? end($items)['id'] : $next) : null, 'has_more' => $more, 'limit' => $limit];
    }

    public static function assetPublic(string $asset): bool
    {
        foreach (Db::all("SELECT p.id FROM partner_profiles p JOIN partner_profile_versions v ON v.id=p.current_version_id WHERE p.state='active' AND JSON_UNQUOTE(JSON_EXTRACT(v.snapshot_json,'$.asset_id'))=? LIMIT 50", [$asset]) as $r) {
            try { self::profile($r['id']); return true; } catch (Problem $e) { if ($e->status !== 404) { throw $e; } }
        }
        return false;
    }

    public static function withdraw(array $user, string $id, array $data): array
    {
        $r = ProgramAccess::owned('partner_applications', $id, $user);
        self::changeAllowed($user, $r);
        ProgramAccess::revision($r, $data);
        Db::run("UPDATE partner_applications SET state='withdrawn' WHERE id=?", [$id]);
        if ($r['profile_id']) { Db::run("UPDATE partner_profiles SET state='withdrawn' WHERE id=?", [$r['profile_id']]); }
        ProgramAccess::event($user, 'partner_application', $id, 'withdrawn');
        return self::application($user, $id);
    }

    private static function changeAllowed(array $user, array $application): void
    {
        if ($application['profile_id'] !== null) { self::manage($user, $application['profile_id']); }
    }

    public static function authorizeChange(array $user, string $id): void
    {
        self::changeAllowed($user, ProgramAccess::owned('partner_applications', $id, $user));
    }
}
