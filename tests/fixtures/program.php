<?php
declare(strict_types=1);

/** PUBLIC synthetic test material. Runtime rejects it outside environment=test. */
function oa_program_test_bank(): array
{
    $questions = [];
    foreach (['identity', 'uri', 'resolver', 'data', 'api', 'operations'] as $domain) {
        for ($i = 0; $i < 18; $i++) {
            $type = ['single', 'multiple', 'matching', 'uri'][$i % 4];
            $answer = match ($type) { 'single' => 'a', 'multiple' => ['a', 'b'], default => ['a' => 'valoare-a', 'b' => 'valoare-b'] };
            $questions[] = ['key' => 'SYNTHETIC-' . $domain . '-' . $i, 'domain' => $domain, 'type' => $type, 'critical' => $i < 3,
                'stem' => 'Întrebare sintetică pentru testarea motorului: ' . $domain . ' / ' . $i . '. Nu este examen real.',
                'options' => [['key' => 'a', 'text' => 'Componentă sintetică A'], ['key' => 'b', 'text' => 'Componentă sintetică B']],
                'answer' => $answer, 'explanation' => 'Explicație sintetică, exclusiv pentru verificări automate.',
                'reference' => 'https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/'];
        }
    }
    return ['title' => 'Bancă SINTETICĂ · exclusiv teste', 'version' => 'test-1', 'synthetic' => true, 'questions' => $questions];
}

function oa_program_test_labs(): array
{
    $event = ['event_key' => 'ERP-1', 'resource' => 'ERP-IAURT', 'tenant' => 'A', 'scope' => 'write', 'expected_revision' => 0];
    return [
        'P01' => ['product' => 'https://example.invalid/01/09520000000011', 'batch' => 'https://example.invalid/01/09520000000011/10/LOT%2FA%2BB%251',
            'unit' => 'https://example.invalid/01/09520000000011/10/LOT%2FA%2BB%251/21/S001', 'explanation' => 'Explicație sintetică verificabilă, pentru testarea rubricii.'],
        'P02' => ['events' => [['operation' => 'product', 'category' => 'lactate'], ['operation' => 'label', 'version' => 'V1', 'name' => 'Iaurt de test', 'ingredients' => 'Lapte'],
            ['operation' => 'publish_label', 'version' => 'V1'], ['operation' => 'batch', 'lot' => 'LOT-1', 'label_version' => 'V1'], ['operation' => 'release_batch'],
            ['operation' => 'label', 'version' => 'V2', 'name' => 'Iaurt cu fructe de test', 'ingredients' => 'Lapte, fructe'], ['operation' => 'alert', 'message' => 'Alertă sintetică curentă.']],
            'ai_enabled' => false, 'open_badge' => false, 'explanation' => 'V1 este păstrată pe lot, alerta este separată.'],
        'P03' => ['events' => [$event, $event, array_replace($event, ['event_key' => 'ERP-2', 'expected_revision' => 0]), array_replace($event, ['event_key' => 'ERP-3', 'tenant' => 'B']), array_replace($event, ['event_key' => 'ERP-4', 'scope' => 'read'])],
            'export_revision' => 1, 'explanation' => 'Retry identic, conflict, două refuzuri. Fără SQL sau rețea.'],
        'P04' => ['gtin' => '09520000000011', 'lot' => 'LOT-TEST', 'serials' => array_map(static fn(int $i): string => 'S' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), range(1, 10)),
            'format' => 'zpl', 'reprint_serial' => 'S001', 'reprint_reason' => 'Reimprimare sintetică justificată pentru o etichetă deteriorată.', 'ack_is_physical_proof' => false],
        'P05' => ['descriptor' => ['primary_keys' => ['01']], 'simple_redirect_is_full_resolver' => false, 'backup_snapshot' => ['batch' => 'L1', 'label' => 'V1'],
            'restored_snapshot' => ['batch' => 'L1', 'label' => 'V1'], 'restore_environment' => 'isolated', 'incident_actions' => ['restrict_affected_batch', 'revoke_compromised_key', 'verify_history']],
        'P06' => ['external_source' => 'openfoodfacts', 'external_is_producer_approved' => false, 'public_fields' => ['name', 'ingredients', 'allergens', 'net_quantity'],
            'old_company_preserved' => true, 'old_data_transferred_automatically' => false, 'new_mandate_required' => true, 'pilot_evidence_state' => 'experimental'],
    ];
}
