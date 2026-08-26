<?php
/**
 * Utsukta\SpaCore\Api\Handlers\ChannelActivities
 *
 * GET /spa/channel-activities → the owner's recently created/edited content,
 * grouped into sections.
 *
 * Port of Zotlabs\Widget\Channel_activities minus its channels section (that
 * one lives in the notifications panel) and its admin system-status section.
 * The queries and the mimetype table below are core's, verbatim, and the
 * `channel_activities_widget` hook is fired exactly as core fires it — so the
 * articles, cards and wiki sections come from the same addon code that fills
 * the classic widget, and any future addon that hooks in gets a tab for free.
 *
 * Owner-scoped by design, so it skips the perm_is_allowed()/observer plumbing
 * the per-nick content handlers carry.
 */

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

class ChannelActivities
{
    private const LIMIT = 5;
    private const PHOTO_LIMIT = 9;

    private int $uid;
    private string $nick;
    private array $activities = [];

    public function get(): void
    {
        $this->uid = Auth::requireLocalGet();
        $channel = \App::get_channel();
        $this->nick = $channel['channel_address'];

        require_once 'include/bbcode.php';

        $this->photos();
        $this->files('uploads');
        $this->files('documents');
        $this->files('audio');
        $this->files('video');
        $this->webpages();

        // Core parity: articles, cards and wiki are addon-contributed sections.
        // call_hooks() takes its payload by reference, so it has to be a
        // variable — passing the literal is a PHP fatal.
        $hookdata = [
            'channel' => $channel,
            'activities' => $this->activities,
            'limit' => self::LIMIT,
        ];
        call_hooks('channel_activities_widget', $hookdata);
        $activities = $hookdata['activities'] ?? [];

        // Core sorts sections by their newest item, so the tab the user most
        // recently touched leads.
        uasort($activities, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

        $sections = [];
        foreach ($activities as $id => $section) {
            if (empty($section['items']))
                continue;
            $sections[] = [
                'id' => $id,
                'label' => $section['label'] ?? $id,
                'url' => $this->relative($section['url'] ?? ''),
                'items' => array_map(
                    fn($i) => $this->formatItem($i, $id),
                    array_values($section['items'])
                ),
            ];
        }

        Response::send([
            'channel' => $this->nick,
            'sections' => $sections,
        ]);
    }

    /**
     * Hook-contributed items carry absolute URLs and htmlentities-escaped
     * summaries built for a Smarty template; the SPA renders text nodes and
     * routes relative paths, so undo both.
     */
    private function formatItem(array $i, string $sectionId): array
    {
        return [
            'url' => $this->itemUrl($i['url'] ?? '', $sectionId),
            'title' => html_entity_decode($i['title'] ?: ($i['alt'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'summary' => trim(strip_tags(html_entity_decode($i['summary'] ?? '', ENT_QUOTES, 'UTF-8'))),
            'src' => $i['src'] ?? null,
            'edited' => $i['footer'] ?? '',
        ];
    }

    /**
     * The articles and cards addons hand out the raw item plink
     * (/item/<uuid>), which is the ActivityStreams fetch endpoint — it answers
     * "permission denied" to a browser. Both have a real SPA view keyed by the
     * same uuid, so point at that instead.
     */
    private function itemUrl(string $url, string $sectionId): string
    {
        $path = $this->relative($url);

        if (($sectionId === 'articles' || $sectionId === 'cards')
            && preg_match('~^/item/([^/?\#]+)~', $path, $m)) {
            return '/' . $sectionId . '/' . $this->nick . '/' . $m[1];
        }

        // The wiki addon builds its links from the raw wiki name, spaces and
        // all; /wiki routes expect NativeWiki::name_encode() form.
        if ($sectionId === 'wiki'
            && preg_match('~^/wiki/[^/]+/(.+)$~', $path, $m)
            && class_exists('NativeWiki')) {
            return '/wiki/' . $this->nick . '/' . \NativeWiki::name_encode($m[1]);
        }

        return $path;
    }

    private function relative(string $url): string
    {
        $root = z_root();
        return str_starts_with($url, $root) ? (substr($url, strlen($root)) ?: '/') : $url;
    }

    // ── Sections (ported from Zotlabs\Widget\Channel_activities) ──────────────

    private function photos(): void
    {
        $r = q("SELECT edited, imgscale, description, filename, resource_id FROM photo
                WHERE uid = %d AND photo_usage = 0 AND is_nsfw = 0 AND imgscale = 3
                ORDER BY edited DESC LIMIT %d",
            intval($this->uid),
            intval(self::PHOTO_LIMIT));

        if (!$r)
            return;

        $i = [];
        foreach ($r as $rr) {
            $i[] = [
                'url' => z_root() . '/photos/' . $this->nick . '/image/' . $rr['resource_id'],
                'title' => '',
                'alt' => $rr['description'] ?: $rr['filename'],
                'src' => z_root() . '/photo/' . $rr['resource_id'] . '-' . $rr['imgscale'],
                'footer' => datetime_convert('UTC', date_default_timezone_get(), $rr['edited']),
            ];
        }

        $this->activities['photos'] = [
            'label' => t('Photos'),
            'url' => z_root() . '/photos/' . $this->nick,
            'date' => $r[0]['edited'],
            'items' => $i,
        ];
    }

    private function files(string $category): void
    {
        // Core's category keys; the SPA's section ids differ for the two it
        // labels rather than names ("uncategorized" → uploads, "document" → documents).
        $coreCategory = match ($category) {
            'uploads' => 'uncategorized',
            'documents' => 'document',
            default => $category,
        };

        $not = ($coreCategory === 'uncategorized') ? 'NOT' : '';
        $mime_types = stringify_array($this->mimeTypes($coreCategory));

        $r = q("SELECT * FROM attach WHERE uid = %d
                AND is_dir = 0 AND is_photo = 0 AND filetype $not IN ($mime_types)
                ORDER BY edited DESC LIMIT %d",
            intval($this->uid),
            intval(self::LIMIT));

        if (!$r)
            return;

        $label = match ($category) {
            'audio' => t('Audios'),
            'video' => t('Videos'),
            'documents' => t('Documents'),
            default => t('Uploads'),
        };

        $i = [];
        foreach ($r as $rr) {
            // dirname(), not core's rtrim($display_path, $filename) — that is a
            // charlist trim and eats trailing characters off the folder name.
            $dir = dirname($rr['display_path']);
            $dir = ($dir === '.' || $dir === '/') ? '' : $dir . '/';
            $i[] = [
                'url' => z_root() . '/cloud/' . $this->nick . '/' . $dir . '#' . $rr['id'],
                'title' => $rr['filename'],
                'summary' => '',
                'footer' => datetime_convert('UTC', date_default_timezone_get(), $rr['edited']),
            ];
        }

        $this->activities[$category] = [
            'label' => $label,
            'url' => z_root() . '/cloud/' . $this->nick,
            'date' => $r[0]['edited'],
            'items' => $i,
        ];
    }

    private function webpages(): void
    {
        $r = q("SELECT * FROM iconfig LEFT JOIN item ON iconfig.iid = item.id
                WHERE item.uid = %d AND iconfig.cat = 'system'
                  AND iconfig.k = 'WEBPAGE' AND item_type = %d
                ORDER BY item.edited DESC LIMIT %d",
            intval($this->uid),
            intval(ITEM_TYPE_WEBPAGE),
            intval(self::LIMIT));

        if (!$r)
            return;

        $i = [];
        foreach ($r as $rr) {
            $summary = html2plain(purify_html(bbcode($rr['body'], ['drop_media' => true, 'tryoembed' => false])), 85, true);
            if ($summary)
                $summary = substr_words($summary, 85);

            $i[] = [
                'url' => z_root() . '/page/' . $this->nick . '/' . $rr['v'],
                'title' => $rr['title'],
                'summary' => $summary,
                'footer' => datetime_convert('UTC', date_default_timezone_get(), $rr['edited']),
            ];
        }

        $this->activities['webpages'] = [
            'label' => t('Webpages'),
            'url' => z_root() . '/webpages/' . $this->nick,
            'date' => $r[0]['edited'],
            'items' => $i,
        ];
    }

    /** Core's mimetype table, verbatim — see Channel_activities::get_mime_types_by_category(). */
    private function mimeTypes(string $category): array
    {
		$mime_types = [
			'document' => [
				'application/vnd.ms-powerpoint',
				'application/vnd.ms-excel',
				'application/vnd.sun.xml.writer',
				'application/vnd.oasis.opendocument.text',
				'application/vnd.oasis.opendocument.text-flat-xml',
				'application/vnd.sun.xml.calc',
				'application/vnd.oasis.opendocument.spreadsheet',
				'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
				'application/vnd.sun.xml.impress',
				'application/vnd.oasis.opendocument.presentation',
				'application/vnd.oasis.opendocument.presentation-flat-xml',
				'application/vnd.sun.xml.draw',
				'application/vnd.oasis.opendocument.graphics',
				'application/vnd.oasis.opendocument.graphics-flat-xml',
				'application/vnd.oasis.opendocument.chart',
				'application/vnd.sun.xml.writer.global',
				'application/vnd.oasis.opendocument.text-master',
				'application/vnd.sun.xml.writer.template',
				'application/vnd.oasis.opendocument.text-template',
				'application/vnd.oasis.opendocument.text-master-template',
				'application/vnd.sun.xml.calc.template',
				'application/vnd.oasis.opendocument.spreadsheet-template',
				'application/vnd.sun.xml.impress.template',
				'application/vnd.oasis.opendocument.presentation-template',
				'application/vnd.sun.xml.draw.template',
				'application/vnd.oasis.opendocument.graphics-template',
				'application/msword',
				'application/msword',
				'application/vnd.ms-excel',
				'application/vnd.ms-powerpoint',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/vnd.ms-word.document.macroEnabled.12',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
				'application/vnd.ms-word.template.macroEnabled.12',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
				'application/vnd.ms-excel.template.macroEnabled.12',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
				'application/vnd.ms-excel.sheet.macroEnabled.12',
				'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
				'application/vnd.openxmlformats-officedocument.presentationml.template',
				'application/vnd.ms-powerpoint.template.macroEnabled.12',
				'application/vnd.wordperfect',
				'application/x-aportisdoc',
				'application/x-hwp',
				'application/vnd.ms-works',
				'application/vnd.ms-office',
				'application/x-mswrite',
				'application/x-dif-document',
				'text/spreadsheet',
				'application/x-dbase',
				'application/vnd.lotus-1-2-3',
				'application/coreldraw',
				'application/vnd.visio2013',
				'application/vnd.visio',
				'application/vnd.ms-visio.drawing',
				'application/x-mspublisher',
				'application/x-sony-bbeb',
				'application/x-gnumeric',
				'application/macwriteii',
				'application/x-iwork-numbers-sffnumbers',
				'application/vnd.oasis.opendocument.text-web',
				'application/x-pagemaker',
				'text/rtf',
				'text/plain',
				'application/x-fictionbook+xml',
				'application/clarisworks',
				'application/x-iwork-pages-sffpages',
				'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
				'application/x-iwork-keynote-sffkey',
				'application/x-abiword',
				'application/vnd.sun.xml.chart',
				'application/x-t602',
				'application/pdf',
			],

			'audio' => [
				'audio/mpeg',        // MP3
				'audio/mp3',
				'audio/wav',         // WAV
				'audio/x-wav',
				'audio/webm',        // WebM audio
				'audio/ogg',         // OGG
				'audio/aac',         // AAC
				'audio/flac',        // FLAC
				'audio/x-flac',
				'audio/mp4',         // M4A / MP4 audio
				'audio/x-m4a',
				'audio/3gpp',        // 3GP audio
				'audio/3gpp2',
				'audio/amr',         // AMR
				'audio/x-ms-wma',    // Windows Media Audio
				'audio/basic',       // µ-law / basic audio
			],

			'video' => [
				'video/mp4',          // MP4
				'video/x-msvideo',    // AVI
				'video/x-ms-wmv',     // WMV
				'video/mpeg',         // MPEG
				'video/ogg',          // OGG/Theora
				'video/webm',         // WebM
				'video/3gpp',         // 3GP
				'video/3gpp2',
				'video/quicktime',    // MOV
				'video/x-flv',        // Flash Video
				'video/x-matroska',   // MKV
				'video/mp2t',         // MPEG-TS (.ts)
			]
		];

        if ($category === 'uncategorized')
            return array_merge(...array_values($mime_types));

        return $mime_types[$category];
    }
}
