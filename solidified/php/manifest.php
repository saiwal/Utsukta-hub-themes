<?php

/**
 * Resolve the hashed SPA asset filenames from Vite's build manifest.
 *
 * Returns ['js' => '/view/.../app-<hash>.js', 'css' => ['/view/.../app-<hash>.css', ...]].
 * Falls back to the unhashed names if the manifest is missing (older build).
 */
function solidified_assets(): array
{
    static $assets = null;
    if ($assets !== null) {
        return $assets;
    }

    $base = '/view/theme/solidified/assets';
    $assets = [
        'js'  => $base . '/app.js',
        'css' => [$base . '/app.css'],
    ];

    $manifest_path = PROJECT_BASE . '/view/theme/solidified/assets/.vite/manifest.json';
    if (!is_readable($manifest_path)) {
        return $assets;
    }

    $manifest = json_decode(file_get_contents($manifest_path), true);
    if (!is_array($manifest)) {
        return $assets;
    }

    $css = [];
    foreach ($manifest as $entry) {
        if (empty($entry['file'])) {
            continue;
        }
        if (!empty($entry['isEntry'])) {
            $assets['js'] = $base . '/' . $entry['file'];
            // css bundled per-entry (cssCodeSplit: true builds)
            foreach ($entry['css'] ?? [] as $f) {
                $css[] = $base . '/' . $f;
            }
        } elseif (str_ends_with($entry['file'], '.css')) {
            // single stylesheet emitted as its own asset (cssCodeSplit: false)
            $css[] = $base . '/' . $entry['file'];
        }
    }
    if ($css) {
        $assets['css'] = array_values(array_unique($css));
    }

    return $assets;
}
