<?php
namespace Utsukta\SpaCore\Api;

/**
 * SPA-only feature toggles, listed under core's groups in Settings → Features and stored like core's own (pconfig cat 'feature').
 *
 * Merged into get_features() at the three places that read it — the
 * Features GET, its POST validation, and /spa/pconfig (which feeds the
 * client's isFeatureEnabled()) — rather than via core's get_features hook,
 * which would also list them in classic Hubzilla's settings, where they mean
 * nothing, and need a theme re-enable to register.
 *
 * Read through enabled(), not feature_enabled(): core resolves an unset
 * feature's default from get_features() *without* these, so every default
 * here would come back false.
 */
final class SpaFeatures
{
    /** group => [name => [label, description, default]] */
    private static function groups(): array
    {
        return [
            'editor' => [
                'spa_latex'    => [t('LaTeX equations'), t('Editor toolbar button for inserting LaTeX math.'), true],
                'spa_diagrams' => [t('Diagrams'), t('Editor toolbar button for inserting Mermaid diagrams (flowcharts, sequence, gantt, pie…).'), false],
            ],
            'network' => [
                'spa_advanced_sort' => [t('Advanced Sorting'), t('Ranked sort orders in the stream: Top, Hot, Most discussed and Controversial.'), false],
            ],
        ];
    }

    /** name => [label, description, default], across all groups. */
    private static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /** get_features() output with the SPA toggles appended to their groups. */
    public static function merge(array $features): array
    {
        $labels = ['editor' => t('Editor'), 'network' => t('Network')];
        foreach (self::groups() as $group => $items) {
            $features[$group] ??= [$labels[$group]];
            foreach ($items as $name => [$label, $desc, $default]) {
                $features[$group][] = [$name, $label, $desc, $default];
            }
        }
        return $features;
    }

    public static function enabled(int $uid, string $name): bool
    {
        $spa = self::all();
        if (!isset($spa[$name])) return (bool) feature_enabled($uid, $name);
        $v = get_pconfig($uid, 'feature', $name, null);
        return $v === null ? $spa[$name][2] : (bool) intval($v);
    }
}
