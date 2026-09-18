<?php

namespace Utsukta\SpaCore\Api\Concerns;

/**
 * Admin-defined profile fields (`profdef`) and their per-profile values
 * (`profext`, keyed by profile_guid) — core's Zotlabs\Module\Profiles and
 * profile_load() equivalent, resolved from (uid, profile_guid) rather than
 * App::$profile, since Profile.php may have swapped in an abook-assigned
 * profile whose extra_fields were never loaded.
 *
 * A profdef row only counts once its field_name also appears in the admin's
 * basic/advanced field list — same two-step rule as core.
 */
trait CustomProfileFields
{
    /** field_name => profdef row, filtered by the admin's allowed field list. */
    private function allowedProfdefs(bool $advanced): array
    {
        require_once('include/channel.php');

        $allowed = $advanced ? get_profile_fields_advanced() : get_profile_fields_basic();
        if (!$allowed) {
            $allowed = $advanced ? get_profile_fields_advanced(1) : get_profile_fields_basic(1);
        }

        $out = [];
        foreach (q("SELECT field_name, field_type, field_desc, field_help FROM profdef ORDER BY id") ?: [] as $d) {
            if (isset($allowed[$d['field_name']])) {
                $out[$d['field_name']] = $d;
            }
        }
        return $out;
    }

    /** @return array<int,array{name:string,label:string,help:string,type:string,value:string}> */
    protected function customProfileFields(int $uid, string $guid, bool $advanced): array
    {
        $defs = $this->allowedProfdefs($advanced);
        if (!$defs || !$guid) return [];

        $values = [];
        $rows = q("SELECT k, v FROM profext WHERE channel_id = %d AND hash = '%s'",
            intval($uid), dbesc($guid));
        foreach ($rows ?: [] as $r) {
            $values[$r['k']] = $r['v'];
        }

        $out = [];
        foreach ($defs as $name => $d) {
            $out[] = [
                'name'  => $name,
                'label' => $d['field_desc'] ?: $name,
                'help'  => $d['field_help'] ?? '',
                'type'  => $d['field_type'] ?: 'text',
                'value' => $values[$name] ?? '',
            ];
        }
        return $out;
    }

    /** Upsert the custom-field values present in $data; unknown keys are ignored. */
    protected function saveCustomProfileFields(int $uid, string $guid, array $data, bool $advanced): void
    {
        $defs = $this->allowedProfdefs($advanced);
        if (!$defs || !$guid) return;

        $existing = [];
        $rows = q("SELECT id, k FROM profext WHERE channel_id = %d AND hash = '%s'",
            intval($uid), dbesc($guid));
        foreach ($rows ?: [] as $r) {
            $existing[$r['k']] = intval($r['id']);
        }

        foreach ($defs as $name => $d) {
            if (!array_key_exists($name, $data)) continue;
            $v = escape_tags(trim((string) $data[$name]));

            if (isset($existing[$name])) {
                q("UPDATE profext SET v = '%s' WHERE id = %d", dbesc($v), $existing[$name]);
            } else {
                q("INSERT INTO profext (channel_id, hash, k, v) VALUES (%d, '%s', '%s', '%s')",
                    intval($uid), dbesc($guid), dbesc($name), dbesc($v));
            }
        }
    }
}
