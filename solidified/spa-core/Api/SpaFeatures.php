<?php
namespace Utsukta\SpaCore\Api;

/**
 * SPA-only feature toggles, listed under core's "Editor" group in
 * Settings → Features and stored like core's own (pconfig cat 'feature').
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
    /** name => [label, description, default] */
    private static function editor(): array
    {
        return [
            'spa_latex'    => [t('LaTeX equations'), t('Editor toolbar button for inserting LaTeX math.'), true],
            'spa_diagrams' => [t('Diagrams'), t('Editor toolbar button for inserting Mermaid diagrams (flowcharts, sequence, gantt, pie…).'), false],
        ];
    }

    /** get_features() output with the SPA toggles appended to the editor group. */
    public static function merge(array $features): array
    {
        $features['editor'] ??= [t('Editor')];
        foreach (self::editor() as $name => [$label, $desc, $default]) {
            $features['editor'][] = [$name, $label, $desc, $default];
        }
        return $features;
    }

    public static function enabled(int $uid, string $name): bool
    {
        $spa = self::editor();
        if (!isset($spa[$name])) return (bool) feature_enabled($uid, $name);
        $v = get_pconfig($uid, 'feature', $name, null);
        return $v === null ? $spa[$name][2] : (bool) intval($v);
    }
}
