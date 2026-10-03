<?php
// Api/ColorSchemes.php
namespace Utsukta\SpaCore\Api;

// The one server-side list of color schemes. It used to be copied into
// Settings (twice), NewChannel and Pconfig; Pconfig's copy fell behind, so a
// high-contrast channel showed visitors the light theme. TypeScript twin:
// THEMES in packages/spa-core/src/types/theme.types.ts — keep them in step.
final class ColorSchemes
{
    public const ALL = [
        'light', 'pastel-soft', 'warm-paper', 'mint', 'sakura', 'latte-cream',
        'dark', 'nord', 'dracula', 'monokai', 'one-dark', 'cyberpunk',
        'rose-pine', 'gruvbox-dark', 'gruvbox-light', 'catppuccin-latte',
        'catppuccin-mocha', 'solarized-light', 'solarized-dark', 'tokyo-night', 'matrix',
        'high-contrast', 'high-contrast-light', 'custom',
    ];

    public static function valid($scheme): bool
    {
        return in_array($scheme, self::ALL, true);
    }
}
