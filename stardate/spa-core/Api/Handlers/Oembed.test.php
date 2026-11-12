<?php
/**
 * Self-check for Handlers/Oembed's pure helpers (discovery + iframe src).
 * No Hubzilla, no network:
 *
 *   php spa-core/Api/Handlers/Oembed.test.php
 */

namespace Zotlabs\Module {
    if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
    if (!class_exists(Linkinfo::class, false)) {
        class Linkinfo {   // stub: only absolute hrefs + one relative case are exercised
            public static function completeurl($url, $base) {
                return parse_url($url, PHP_URL_SCHEME) ? $url
                    : rtrim(preg_replace('#^(https?://[^/]+).*#', '$1', $base), '/') . $url;
            }
        }
    }
}

namespace {
    require_once __DIR__ . '/Oembed.php';
    use Utsukta\SpaCore\Api\Handlers\Oembed;

    $fail = 0;
    function check(string $label, $got, $want): void {
        global $fail;
        if ($got === $want) { echo "ok    $label\n"; return; }
        $fail++;
        echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
    }

    $pt = '<html><head><link rel="alternate" type="application/json+oembed" '
        . 'href="https://tube.example/services/oembed?url=https%3A%2F%2Ftube.example%2Fw%2Fabc&amp;format=json"></head></html>';
    check('peertube discovery', Oembed::discoverHref($pt, 'https://tube.example/w/abc'),
        'https://tube.example/services/oembed?url=https%3A%2F%2Ftube.example%2Fw%2Fabc&format=json');
    check('text/json+oembed accepted',
        Oembed::discoverHref('<link type="text/json+oembed" href="https://sc.example/oembed?u=1">', 'https://sc.example/x'),
        'https://sc.example/oembed?u=1');
    check('relative href resolved',
        Oembed::discoverHref('<link type="application/json+oembed" href="/oembed?u=1">', 'https://a.example/v/1'),
        'https://a.example/oembed?u=1');
    check('no discovery link', Oembed::discoverHref('<title>x</title>', 'https://a.example/'), '');

    check('iframe src', Oembed::iframeSrc('<iframe width="560" src="https://tube.example/videos/embed/abc" allowfullscreen></iframe>'),
        'https://tube.example/videos/embed/abc');
    check('protocol-relative upgraded', Oembed::iframeSrc('<iframe src="//w.example/player/1"></iframe>'), 'https://w.example/player/1');
    check('http refused', Oembed::iframeSrc('<iframe src="http://w.example/p"></iframe>'), null);
    check('javascript refused', Oembed::iframeSrc('<iframe src="javascript:alert(1)"></iframe>'), null);
    check('no iframe (script-only html)', Oembed::iframeSrc('<blockquote>x</blockquote><script src="https://x/w.js"></script>'), null);

    echo $fail ? "\n$fail FAILED\n" : "\nall ok\n";
    exit($fail ? 1 : 0);
}
