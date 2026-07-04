<?php
// extend/theme/utsukta-themes/solidified/Api/Handlers/Help.php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Michelf\MarkdownExtra;

class Help {

    // Absolute path to src/docs/ inside the theme
    private function docsBase(): string {
        // Relative to CWD (/var/www/html/core) — same convention as core Help module
        return 'view/theme/solidified/docs';
    }

    // ── dispatch ──────────────────────────────────────────────────────────────

    public function get(): void {
        $action = \App::$argv[2] ?? null;

        match ($action) {
            'nav'   => $this->handleNav(),
            'topic' => $this->handleTopic(),
            default => Response::error(400, 'Missing action: /api/help/nav or /api/help/topic'),
        };
    }

    // ── nav tree ──────────────────────────────────────────────────────────────
    // GET /api/help/nav?section=user&lang=en

    private function handleNav(): void {
        $section = $this->slugParam('section', 'user');
        $lang    = $this->slugParam('lang',    'en');

        $base = $this->docsBase() . '/' . $section . '/' . $lang;

        if (!is_dir($base)) {
            // Try to find any available lang for this section
            $base = $this->findAnyLangBase($section);
            if (!$base) {
                Response::error(404, "No docs found for section '{$section}'");
            }
        }

        $tree  = $this->buildTree($base, $base, $section, $lang);
        $langs = $this->availableLangs($section);

        Response::send([
            'tree'    => $tree,
            'langs'   => $langs,
            'section' => $section,
            'lang'    => $lang,
        ]);
    }

    // ── topic content ─────────────────────────────────────────────────────────
    // GET /api/help/topic?section=user&lang=en&topic=network

    private function handleTopic(): void {
        $section = $this->slugParam('section', 'user');
        $lang    = $this->slugParam('lang',    'en');
        $topic   = trim($this->param('topic', ''), '/');

        // Sanitise topic path — only safe chars
        $topic = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $topic);

        $base = $this->docsBase() . '/' . $section . '/' . $lang;

        // Fallback lang if requested one doesn't exist
        if (!is_dir($base)) {
            $fallbackBase = $this->findAnyLangBase($section);
            if (!$fallbackBase) {
                Response::error(404, "No docs found for section '{$section}'");
            }
            $base = $fallbackBase;
        }

        // Resolve file: prefer {topic}/index.txt, then {topic}.txt
        $file = null;
        $candidates = [
            $base . '/' . $topic . '/index.txt',
            $base . '/' . $topic . '.txt',
            $base . '/index.txt',   // root of section
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) { $file = $c; break; }
        }

        if (!$file) {
            Response::error(404, "Topic not found: {$topic}");
        }

        $raw  = file_get_contents($file);
        $html = MarkdownExtra::defaultTransform($raw);

        // Rewrite relative image srcs to absolute docs asset URLs.
        // The topic dir is the directory containing the resolved file.
        // e.g. file = view/theme/solidified/docs/user/en/hq/widgets/post-composer/index.txt
        //   → topicDir = hq/widgets/post-composer
        //   → assetBase = /view/theme/solidified/docs/user/en/hq/widgets/post-composer/
        $fileDir    = dirname($file);                     // absolute-ish (relative to CWD)
        $docsRoot   = $this->docsBase() . '/' . $section . '/' . $lang;
        $topicDir   = ltrim(substr($fileDir, strlen($docsRoot)), '/');
        $assetBase  = '/view/theme/solidified/docs/' . $section . '/' . $lang
                      . ($topicDir ? '/' . $topicDir : '') . '/';

        // Replace src="relative/path" that don't start with http/https/data/or /
        $html = preg_replace_callback(
            '/(<img\s[^>]*src=")(?!https?:\/\/|data:|\/)(.*?)(")/i',
            fn($m) => $m[1] . $assetBase . $m[2] . $m[3],
            $html
        );

        // Extract plain-text title from first # heading
        $title = $this->extractTitle($raw);

        $langs = $this->availableLangs($section);

        Response::send([
            'html'    => $html,
            'title'   => $title,
            'topic'   => $topic,
            'section' => $section,
            'lang'    => $lang,
            'langs'   => $langs,
        ]);
    }

    // ── tree builder ──────────────────────────────────────────────────────────

    /**
     * Recursively scan $dir and return a tree of nav nodes.
     * Each node: { slug, label, path, hasContent, children[] }
     * `path` is the full topic slug relative to lang root, e.g. "network/advanced"
     */
    private function buildTree(string $dir, string $base, string $section, string $lang): array {
        $nodes = [];

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $full    = $dir . '/' . $entry;
            $relPath = ltrim(substr($full, strlen($base)), '/');

            if (is_dir($full)) {
                $hasContent = file_exists($full . '/index.txt');
                $children   = $this->buildTree($full, $base, $section, $lang);

                // Only include dirs that have content somewhere in the subtree
                if (!$hasContent && empty($children)) continue;

                $nodes[] = [
                    'slug'       => $entry,
                    'label'      => $this->slugToLabel($entry),
                    'path'       => $relPath,
                    'hasContent' => $hasContent,
                    'children'   => $children,
                ];
            } elseif ($entry !== 'index.txt' && str_ends_with($entry, '.txt')) {
                // Standalone .txt file (not index)
                $slug    = pathinfo($entry, PATHINFO_FILENAME);
                $raw     = file_get_contents($full);
                $label   = $this->extractTitle($raw) ?: $this->slugToLabel($slug);
                $nodes[] = [
                    'slug'       => $slug,
                    'label'      => $label,
                    'path'       => pathinfo($relPath, PATHINFO_DIRNAME) . '/' . $slug,
                    'hasContent' => true,
                    'children'   => [],
                ];
            }
        }

        // Sort: dirs first, then files, both alphabetically
        usort($nodes, fn($a, $b) =>
            (!empty($a['children']) <=> !empty($b['children'])) * -1
            ?: strcmp($a['slug'], $b['slug'])
        );

        return $nodes;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function param(string $key, string $default = ''): string {
        return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

    // Section/lang become filesystem path segments, so restrict them to a safe
    // slug charset. This blocks path traversal (e.g. section=../../..) — unlike
    // `topic`, these were previously used raw. Empty/invalid input falls back.
    private function slugParam(string $key, string $default): string {
        $val = $this->param($key, $default);
        $val = preg_replace('/[^a-zA-Z0-9_\-]/', '', $val);
        return $val !== '' ? $val : $default;
    }

    private function availableLangs(string $section): array {
        $base = $this->docsBase() . '/' . $section;
        if (!is_dir($base)) return [];
        $langs = [];
        foreach (scandir($base) as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($base . '/' . $entry)) {
                $langs[] = $entry;
            }
        }
        return $langs;
    }

    private function findAnyLangBase(string $section): ?string {
        $langs = $this->availableLangs($section);
        if (empty($langs)) return null;
        // Prefer 'en', then first available
        $lang = in_array('en', $langs) ? 'en' : $langs[0];
        return $this->docsBase() . '/' . $section . '/' . $lang;
    }

    private function slugToLabel(string $slug): string {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    private function extractTitle(string $markdown): string {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
