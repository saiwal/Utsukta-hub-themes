<?php
/**
 * Handler: Wiki
 * Route map key: 'wiki'
 *
 * Endpoints
 * ─────────
 * GET  /api/wiki/:nick                         → list wikis for channel
 * GET  /api/wiki/:nick/:wikiName               → list pages in a wiki
 * GET  /api/wiki/:nick/:wikiName/:pageName     → fetch rendered + raw page content
 * POST /api/wiki/:nick                         → create a new wiki
 * POST /api/wiki/:nick/:wikiName/:pageName     → save (upsert) page content
 * DELETE /api/wiki/:nick/:wikiName/:pageName   → delete a page
 *
 * Route registration (Router.php $map):
 *   'wiki' => Handlers\Wiki::class,
 *
 * Dependencies: NativeWiki, NativeWikiPage (addon/wiki/Lib/*)
 */

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Apps;
use NativeWiki;
use NativeWikiPage;
use Michelf\MarkdownExtra;
use Zotlabs\Lib\MarkdownSoap;

require_once 'addon/wiki/Lib/NativeWiki.php';
require_once 'addon/wiki/Lib/NativeWikiPage.php';
require_once 'include/bbcode.php';

class Wiki
{
    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve channel owner row from URL segment argv(2).
     * Errors out with 404 on failure.
     */
    private function resolveOwner(): array
    {
        $nick = \App::$argv[2] ?? '';
        if (!$nick) {
            Response::error(400, 'Channel nick required');
        }
        $owner = channelx_by_nick($nick);
        if (!$owner) {
            Response::error(404, 'Channel not found');
        }
        return $owner;
    }

    /**
     * Resolve wiki resource row.  $wikiName is the human-readable wiki name
     * (URL-decoded by the caller).
     */
    private function resolveWiki(array $owner, string $wikiName): array
    {
        $w = NativeWiki::exists_by_name($owner['channel_id'], $wikiName);
        if (!$w || !$w['resource_id']) {
            Response::error(404, 'Wiki not found');
        }
        return $w;
    }

    /**
     * Check that the addon is installed for this channel.
     */
    private function requireAddon(int $uid): void
    {
        if (!Apps::addon_app_installed($uid, 'wiki')) {
            Response::error(403, 'Wiki addon not installed for this channel');
        }
    }

    /**
     * Render raw wiki page content → HTML string.
     */
    private function renderContent(string $content, string $mimeType, string $wikiPath): string
    {
        if ($mimeType === 'text/bbcode') {
            $html = zidify_links(smilies(bbcode($content, ['tryoembed' => false])));
            return NativeWikiPage::convert_links($html, $wikiPath);
        }
        if ($mimeType === 'text/plain') {
            return str_replace(
                ["\n", ' ', "\t"],
                [EOL, '&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'],
                htmlentities($content, ENT_COMPAT, 'UTF-8', false)
            );
        }
        // text/markdown (default)
        $unescaped = MarkdownSoap::unescape($content);
        $linked    = NativeWikiPage::convert_links($unescaped, $wikiPath);
        $bb        = NativeWikiPage::bbcode($linked);
        $md        = MarkdownExtra::defaultTransform($bb);
        return NativeWikiPage::generate_toc(zidify_text($md));
    }

    /**
     * Format a wiki row for the list endpoint.
     */
    private function formatWiki(array $w): array
    {
        return [
            'resource_id' => $w['resource_id'] ?? '',
            'name'        => NativeWiki::name_decode($w['urlName'] ?? ''),
            'url_name'    => $w['urlName']    ?? '',
            'html_name'   => $w['htmlName']   ?? '',
            'mime_type'   => $w['mimeType']   ?? 'text/markdown',
            'type_lock'   => (bool) ($w['typeLock'] ?? false),
        ];
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function get(): void
    {
        $owner    = $this->resolveOwner();
        $uid      = $owner['channel_id'];
        $obs_hash = get_observer_hash();

        $this->requireAddon($uid);

        // Permission: view_wiki
        if (!perm_is_allowed($uid, $obs_hash, 'view_wiki')) {
            Response::error(403, 'Permission denied');
        }

        $argc = count(\App::$argv);

        // ── GET /api/wiki/:nick  →  list wikis ────────────────────────────────
        if ($argc === 3) {
            $result = NativeWiki::listwikis($owner, $obs_hash);
            $wikis  = [];
            if ($result && !empty($result['wikis'])) {
                foreach ($result['wikis'] as $w) {
                    $wikis[] = $this->formatWiki($w);
                }
            }
            $is_owner = (local_channel() && local_channel() === intval($uid));
            Response::send([
                'wikis'    => $wikis,
                'is_owner' => $is_owner,
                'can_create' => perm_is_allowed($uid, $obs_hash, 'write_wiki'),
            ]);
        }

        $wikiName = NativeWiki::name_decode(\App::$argv[3] ?? '');
        $w        = $this->resolveWiki($owner, $wikiName);
        $rid      = $w['resource_id'];
        $perms    = NativeWiki::get_permissions($rid, $uid, $obs_hash);

        if (!$perms['read']) {
            Response::error(403, 'Permission denied');
        }

        // ── GET /api/wiki/:nick/:wikiName  →  page list ───────────────────────
        if ($argc === 4) {
            $pages = NativeWikiPage::get_page_list(['resource_id' => $rid, 'channel_id' => $uid]);
            $list  = [];
            if ($pages && !empty($pages['pages'])) {
                foreach ($pages['pages'] as $p) {
                    $list[] = [
                        'name'     => NativeWiki::name_decode($p['urlName'] ?? ''),
                        'url_name' => $p['urlName'] ?? '',
                    ];
                }
            }
            Response::send([
                'wiki'      => $this->formatWiki($w),
                'pages'     => $list,
                'can_write' => (bool) $perms['write'],
            ]);
        }

        // ── GET /api/wiki/:nick/:wikiName/:pageName  →  page content ──────────
        // Build page name from all remaining argv segments (supports sub-paths)
        $pageParts = array_slice(\App::$argv, 4);
        $pageUrlName = NativeWiki::name_decode(implode('/', $pageParts));
        $wikiPath    = 'wiki/' . $owner['channel_address'] . '/' . NativeWiki::name_encode($wikiName);

        $p = NativeWikiPage::get_page_content([
            'channel_id'   => $uid,
            'observer_hash' => $obs_hash,
            'resource_id'  => $rid,
            'pageUrlName'  => $pageUrlName,
        ]);

        if (!$p || !$p['success']) {
            // Page missing — return a 404 with enough info for the frontend to offer creation
            Response::error(404, 'Page not found');
        }

        $mimeType = $p['pageMimeType'] ?? 'text/markdown';
        $raw      = $p['content'] ?? '';

        $hookinfo = ['content' => $raw, 'mimetype' => $mimeType];
        call_hooks('wiki_preprocess', $hookinfo);
        $raw = $hookinfo['content'];

        $rendered = $this->renderContent($raw, $mimeType, $wikiPath);

        Response::send([
            'wiki'         => $this->formatWiki($w),
            'page'         => [
                'name'      => $pageUrlName,
                'url_name'  => NativeWiki::name_encode($pageUrlName),
                'mime_type' => $mimeType,
            ],
            'raw'          => $raw,
            'html'         => $rendered,
            'can_write'    => (bool) $perms['write'],
            'commit'       => $p['commit'] ?? 'HEAD',
        ]);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function post(): void
    {
        $uid   = Auth::requireLocalJson();
        $owner = $this->resolveOwner();

        // Must be channel owner for write ops
        if (intval($owner['channel_id']) !== $uid) {
            Response::error(403, 'Only the channel owner can write to wikis');
        }

        $this->requireAddon($uid);
        $obs_hash = get_observer_hash();
        $data     = Auth::$parsedBody;
        $argc     = count(\App::$argv);

        // ── POST /api/wiki/:nick  →  create wiki ──────────────────────────────
        if ($argc === 3) {
            $wiki_name = trim($data['name'] ?? '');
            $mime_type = $data['mime_type'] ?? 'text/markdown';
            $type_lock = (bool) ($data['type_lock'] ?? false);

            if (!$wiki_name) {
                Response::error(400, 'Wiki name required');
            }

            $allow_cid = $data['allow_cid'] ?? $owner['channel_allow_cid'];
            $allow_gid = $data['allow_gid'] ?? $owner['channel_allow_gid'];
            $deny_cid  = $data['deny_cid']  ?? $owner['channel_deny_cid'];
            $deny_gid  = $data['deny_gid']  ?? $owner['channel_deny_gid'];

            $created = NativeWiki::create_wiki([
                'channel_id'    => $uid,
                'observer_hash' => $obs_hash,
                'wiki_name'     => $wiki_name,
                'mimeType'      => $mime_type,
                'typeLock'      => $type_lock ? '1' : '0',
                'allow_cid'     => $allow_cid,
                'allow_gid'     => $allow_gid,
                'deny_cid'      => $deny_cid,
                'deny_gid'      => $deny_gid,
            ]);

            if (!$created['success']) {
                Response::error(500, $created['message'] ?? 'Error creating wiki');
            }

            Response::send([
                'success'     => true,
                'resource_id' => $created['resource_id'] ?? '',
                'url_name'    => NativeWiki::name_encode($wiki_name),
            ], [], 201);
        }

        // ── POST /api/wiki/:nick/:wikiName/:pageName  →  save page ────────────
        $wikiName    = NativeWiki::name_decode(\App::$argv[3] ?? '');
        $w           = $this->resolveWiki($owner, $wikiName);
        $rid         = $w['resource_id'];
        $perms       = NativeWiki::get_permissions($rid, $uid, $obs_hash);

        if (!$perms['write']) {
            Response::error(403, 'Permission denied');
        }

        $pageParts   = array_slice(\App::$argv, 4);
        $pageUrlName = NativeWiki::name_decode(implode('/', $pageParts));
        $content     = $data['content']    ?? '';
        $commit_msg  = $data['commit_msg'] ?? '';
        $mime_type   = $data['mime_type']  ?? ($w['mimeType'] ?? 'text/markdown');

        // Save
        $saved = NativeWikiPage::save_page([
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'resource_id'   => $rid,
            'pageUrlName'   => $pageUrlName,
            'content'       => $content,
            'mimeType'      => $mime_type,
        ]);

        if (!$saved['success']) {
            Response::error(500, $saved['message'] ?? 'Error saving page');
        }

        // Commit
        $commit = NativeWikiPage::commit([
            'commit_msg'    => $commit_msg ?: 'Page updated',
            'pageUrlName'   => $pageUrlName,
            'resource_id'   => $rid,
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'revision'      => -1,
        ]);

        if ($commit['success']) {
            NativeWiki::sync_a_wiki_item($uid, $commit['item_id'], $rid);
        }

        Response::send([
            'success'    => true,
            'commit'     => $commit['success'] ? 'HEAD' : null,
        ]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function delete(): void
    {
        $uid   = Auth::requireLocalJson();
        $owner = $this->resolveOwner();

        if (intval($owner['channel_id']) !== $uid) {
            Response::error(403, 'Only the channel owner can delete pages');
        }

        $this->requireAddon($uid);
        $obs_hash    = get_observer_hash();
        $wikiName    = NativeWiki::name_decode(\App::$argv[3] ?? '');
        $w           = $this->resolveWiki($owner, $wikiName);
        $rid         = $w['resource_id'];
        $perms       = NativeWiki::get_permissions($rid, $uid, $obs_hash);

        if (!$perms['write']) {
            Response::error(403, 'Permission denied');
        }

        $pageParts   = array_slice(\App::$argv, 4);
        $pageUrlName = NativeWiki::name_decode(implode('/', $pageParts));

        if ($pageUrlName === 'Home') {
            Response::error(400, 'Cannot delete the Home page');
        }

        $deleted = NativeWikiPage::delete_page([
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'resource_id'   => $rid,
            'pageUrlName'   => $pageUrlName,
        ]);

        if (!$deleted['success']) {
            Response::error(500, $deleted['message'] ?? 'Error deleting page');
        }

        NativeWiki::sync_a_wiki_item($uid, 0, $rid);
        Response::send(['success' => true]);
    }
}
