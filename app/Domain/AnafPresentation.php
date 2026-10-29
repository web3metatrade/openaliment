<?php
declare(strict_types=1);

namespace App\Domain;

/** All known ANAF fields stay visible; future fields retain their source names. */
final class AnafPresentation
{
    private static ?array $definition = null;

    private static function definition(): array
    {
        if (self::$definition === null) {
            $source = json_decode(file_get_contents(ROOT . '/contracts/anaf-fields.json'), true, 24, JSON_THROW_ON_ERROR);
            $fields = array_fill_keys(array_keys($source['groups']), []);
            foreach ($source['fields'] as $field) {
                $parts = explode('.', $field['path']);
                $fields[$parts[0]][end($parts)] = $field['label'];
            }
            self::$definition = ['groups' => $source['groups'], 'fields' => $fields];
        }
        return self::$definition;
    }

    public static function value(mixed $value): string
    {
        if ($value === null || $value === '') return 'Nefurnizat de ANAF';
        if (is_bool($value)) return $value ? 'Da' : 'Nu';
        if (is_array($value)) return \App\Core\Db::json($value);
        return (string) $value;
    }

    public static function address(array $address, string $prefix): string
    {
        $parts = [];
        foreach (['denumire_Strada', 'numar_Strada', 'detalii_Adresa', 'denumire_Localitate', 'denumire_Judet', 'cod_Postal', 'tara'] as $key) {
            if (isset($address[$prefix . $key]) && is_scalar($address[$prefix . $key]) && trim((string) $address[$prefix . $key]) !== '') $parts[] = (string) $address[$prefix . $key];
        }
        return $parts ? implode(', ', $parts) : 'Nefurnizat de ANAF';
    }

    public static function groups(array $record): array
    {
        $groups = [];
        $definition = self::definition();
        foreach ($definition['groups'] as $key => $title) {
            $data = $record[$key] ?? [];
            $labels = $definition['fields'][$key];
            $fields = [];
            foreach ($labels as $field => $label) {
                if ($key === 'inregistrare_scop_Tva' && $field !== 'scpTVA') continue;
                $fields[] = ['label' => $label, 'value' => $data[$field] ?? null];
            }
            if ($key === 'inregistrare_scop_Tva') {
                $periods = $data['perioade_TVA'] ?? [];
                if (is_array($periods) && !array_is_list($periods)) $periods = [$periods];
                if (!$periods) $fields[] = ['label' => 'Perioade TVA', 'value' => null];
                else foreach ($periods as $number => $period) {
                    if (!is_array($period)) { $fields[] = ['label' => 'Perioada ' . ($number + 1), 'value' => $period]; continue; }
                    foreach ($labels as $field => $label) if ($field !== 'scpTVA') $fields[] = ['label' => 'Perioada ' . ($number + 1) . ' · ' . $label, 'value' => $period[$field] ?? null];
                    $fields = array_merge($fields, self::extra($period, $labels, 'Perioada ' . ($number + 1) . ' · '));
                }
                $labels['perioade_TVA'] = 'Perioade TVA';
            }
            $fields = array_merge($fields, self::extra($data, $labels));
            $groups[] = ['title' => $title, 'fields' => $fields];
        }
        $extra = self::extra($record, $definition['groups']);
        if ($extra) $groups[] = ['title' => 'Alte date furnizate de ANAF', 'fields' => $extra];
        return $groups;
    }

    private static function extra(array $data, array $known, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $known)) continue;
            if (is_array($value) && $value) $out = array_merge($out, self::extra($value, [], $prefix . $key . ' · '));
            else $out[] = ['label' => $prefix . $key, 'value' => $value === [] ? null : $value];
        }
        return $out;
    }
}
