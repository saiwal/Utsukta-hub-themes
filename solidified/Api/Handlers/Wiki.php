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
        $w = \NativeWiki::exists_by_name($owner['channel_id'], $wikiName);
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
            return \NativeWikiPage::convert_links($html, $wikiPath);
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
        $linked    = \NativeWikiPage::convert_links($unescaped, $wikiPath);
        $bb        = \NativeWikiPage::bbcode($linked);
        $md        = MarkdownExtra::defaultTransform($bb);
        return \NativeWikiPage::generate_toc(zidify_text($md));
    }

    /**
     * Format a wiki row for the list endpoint.
     */
    private function formatWiki(array $w): array
    {
        return [
            'resource_id' => $w['resource_id'] ?? '',
            'name'        => \NativeWiki::name_decode($w['urlName'] ?? ''),
            'url_name'    => $w['urlName']    ?? '',
            'html_name'   => $w['htmlName']   ?? '',
            'mime_type'   => $w['mimeType']   ?? 'text/bbcode',
            'type_lock'   => (bool) ($w['typeLock'] ?? false),
        ];
    }

    private function parseAclField(string $field): array
    {
        if (!$field) return [];
        preg_match_all('/<([^>]+)>/', $field, $m);
        return $m[1] ?? [];
    }

    private function buildAclField(array $ids): string
    {
        $ids = array_filter(array_map('strval', $ids));
        return $ids ? '<' . implode('><', $ids) . '>' : '';
    }

    private function getAcl(array $owner, string $rid): void
    {
        $uid = intval($owner['channel_id']);
        $r = q("SELECT allow_cid, allow_gid, deny_cid, deny_gid FROM item
                WHERE resource_type = 'nwiki' AND resource_id = '%s' AND uid = %d LIMIT 1",
               dbesc($rid), $uid);
        if (!$r) {
            Response::error(404, 'Wiki item not found');
        }
        $row = $r[0];
        Response::send([
            'allow_cid' => $this->parseAclField($row['allow_cid']),
            'allow_gid' => $this->parseAclField($row['allow_gid']),
            'deny_cid'  => $this->parseAclField($row['deny_cid']),
            'deny_gid'  => $this->parseAclField($row['deny_gid']),
        ]);
    }

    private function postAcl(array $owner, string $rid): void
    {
        $uid  = intval($owner['channel_id']);
        $body = Auth::$parsedBody;

        $allow_cid  = $this->buildAclField($body['allow_cid'] ?? []);
        $allow_gid  = $this->buildAclField($body['allow_gid'] ?? []);
        $deny_cid   = $this->buildAclField($body['deny_cid']  ?? []);
        $deny_gid   = $this->buildAclField($body['deny_gid']  ?? []);
        $is_private = ($allow_cid || $allow_gid || $deny_cid || $deny_gid) ? 1 : 0;

        q("UPDATE item SET allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s', item_private = %d
           WHERE resource_type = 'nwiki' AND resource_id = '%s' AND uid = %d",
           dbesc($allow_cid), dbesc($allow_gid), dbesc($deny_cid), dbesc($deny_gid),
           $is_private, dbesc($rid), $uid);

        q("UPDATE item SET allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s', item_private = %d
           WHERE resource_type = 'nwikipage' AND resource_id = '%s' AND uid = %d",
           dbesc($allow_cid), dbesc($allow_gid), dbesc($deny_cid), dbesc($deny_gid),
           $is_private, dbesc($rid), $uid);

        Response::send(['ok' => true]);
    }

    /**
     * True when the last URL segment matches $keyword (used to detect
     * sub-actions like …/history, …/revert, …/rename).
     */
    private function lastArgIs(string $keyword): bool
    {
        $argc = count(\App::$argv);
        return $argc >= 6 && (\App::$argv[$argc - 1] === $keyword);
    }

    /**
     * Return the page URL-name parts excluding a trailing action keyword.
     * e.g. for argv = [..., 'MyPage', 'history']  →  ['MyPage']
     */
    private function pagePartsWithout(string $keyword): array
    {
        $argc  = count(\App::$argv);
        $parts = array_slice(\App::$argv, 4, $argc - 5);
        return $parts;
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
            $result = \NativeWiki::listwikis($owner, $obs_hash);
            $wikis  = [];
            if ($result && !empty($result['wikis'])) {
                foreach ($result['wikis'] as $w) {
                    $wikis[] = $this->formatWiki($w);
                }
            }

            // Attach is_private flag from the item table in one query
            if (!empty($wikis)) {
                $privRows = q("SELECT resource_id, item_private FROM item
                               WHERE resource_type = 'nwiki' AND uid = %d",
                               intval($uid));
                $privMap = [];
                foreach ($privRows as $row) {
                    $privMap[$row['resource_id']] = (bool) $row['item_private'];
                }
                foreach ($wikis as &$w) {
                    $w['is_private'] = $privMap[$w['resource_id']] ?? false;
                }
                unset($w);
            }

            $is_owner = (local_channel() && local_channel() === intval($uid));
            Response::send([
                'wikis'      => $wikis,
                'is_owner'   => $is_owner,
                'can_create' => perm_is_allowed($uid, $obs_hash, 'write_wiki'),
            ]);
        }

        $wikiName = \NativeWiki::name_decode(\App::$argv[3] ?? '');
        $w        = $this->resolveWiki($owner, $wikiName);
        $rid      = $w['resource_id'];
        $perms    = \NativeWiki::get_permissions($rid, $uid, $obs_hash);

        if (!$perms['read']) {
            Response::error(403, 'Permission denied');
        }

        // ── GET /api/wiki/:nick/:wikiName  →  page list ───────────────────────
        if ($argc === 4) {
            // Signature: page_list($channel_id, $observer_hash, $resource_id)
            $result = \NativeWikiPage::page_list($uid, $obs_hash, $rid);
            $list   = [];
            foreach (($result['pages'] ?? []) as $p) {
                $list[] = [
                    'name'     => escape_tags($p['title'] ?? ''),
                    'url_name' => $p['url']   ?? '',
                ];
            }
            Response::send([
                'wiki'      => $this->formatWiki($w),
                'pages'     => $list,
                'can_write' => (bool) $perms['write'],
            ]);
        }

        // ── GET /api/wiki/:nick/:wikiName/acl  →  wiki ACL ───────────────────
        if ($argc === 5 && \App::$argv[4] === 'acl') {
            if (!local_channel() || local_channel() !== intval($uid)) {
                Response::error(403, 'Owner access required');
            }
            $this->getAcl($owner, $rid);
            return;
        }

        // ── GET /api/wiki/:nick/:wikiName/:pageName/history  →  page history ───
        if ($this->lastArgIs('history')) {
            $pageParts   = $this->pagePartsWithout('history');
            $pageUrlName = \NativeWiki::name_decode(implode('/', $pageParts));

            $result = \NativeWikiPage::page_history([
                'channel_id'    => $uid,
                'observer_hash' => $obs_hash,
                'resource_id'   => $rid,
                'pageUrlName'   => $pageUrlName,
            ]);

            if (!$result['success']) {
                Response::send(['history' => []]);
            }

            Response::send(['history' => $result['history'] ?? []]);
        }

        // ── GET /api/wiki/:nick/:wikiName/:pageName  →  page content ──────────
        // Optional ?revision=N returns a specific historical revision.
        $pageParts   = array_slice(\App::$argv, 4);
        $pageUrlName = \NativeWiki::name_decode(implode('/', $pageParts));
        $wikiPath    = 'wiki/' . $owner['channel_address'] . '/' . \NativeWiki::name_encode($wikiName);

        $revisionParam = isset($_GET['revision']) ? intval($_GET['revision']) : null;

        $pageArgs = [
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'resource_id'   => $rid,
            'pageUrlName'   => $pageUrlName,
        ];
        if ($revisionParam !== null) {
            $pageArgs['revision'] = $revisionParam;
        }

        $p = \NativeWikiPage::get_page_content($pageArgs);

        if (!$p || !$p['success']) {
            // Page missing — return a 404 with enough info for the frontend to offer creation
            Response::error(404, 'Page not found');
        }

        // The SPA has no per-page format picker — every page in a wiki is
        // authored in the wiki's configured mimetype. Trust that over the
        // item's own stored mimetype, which can be stale (e.g. left over
        // from before a wiki's format was finalized) and would otherwise run
        // bbcode content through the markdown pipeline.
        $mimeType = $p['mimeType'] ?? 'text/bbcode';
        $raw      = $p['content'] ?? '';

        $hookinfo = ['content' => $raw, 'mimetype' => $mimeType];
        call_hooks('wiki_preprocess', $hookinfo);
        $raw = $hookinfo['content'];

        $rendered = $this->renderContent($raw, $mimeType, $wikiPath);

        Response::send([
            'wiki'         => $this->formatWiki($w),
            'page'         => [
                'name'      => $pageUrlName,
                'url_name'  => \NativeWiki::name_encode($pageUrlName),
                'mime_type' => $mimeType,
            ],
            'raw'          => $raw,
            'html'         => $rendered,
            'can_write'    => (bool) $perms['write'],
            'commit'       => $p['commit'] ?? 'HEAD',
            'revision'     => $revisionParam,
        ]);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function post(): void
    {
        $obs_hash = Auth::requireLoggedInJson();
        $owner    = $this->resolveOwner();
        $uid      = intval($owner['channel_id']);

        $this->requireAddon($uid);
        $data = Auth::$parsedBody;
        $argc = count(\App::$argv);

        // ── POST /api/wiki/:nick  →  create wiki ──────────────────────────────
        // No wiki resource exists yet to carry a per-resource ACL, so creation
        // stays owner-only.
        if ($argc === 3) {
            if (!local_channel() || local_channel() !== $uid) {
                Response::error(403, 'Only the channel owner can create wikis');
            }

            $wiki_name = trim($data['name'] ?? '');
            $mime_type = 'text/bbcode';
            $type_lock = (bool) ($data['type_lock'] ?? false);

            if (!$wiki_name) {
                Response::error(400, 'Wiki name required');
            }

            $acl = new \Zotlabs\Access\AccessList($owner);
            $allow_cid_raw = $this->buildAclField($data['allow_cid'] ?? []);
            $allow_gid_raw = $this->buildAclField($data['allow_gid'] ?? []);
            $deny_cid_raw  = $this->buildAclField($data['deny_cid']  ?? []);
            $deny_gid_raw  = $this->buildAclField($data['deny_gid']  ?? []);
            if ($allow_cid_raw || $allow_gid_raw || $deny_cid_raw || $deny_gid_raw) {
                $acl->set([
                    'allow_cid' => $allow_cid_raw,
                    'allow_gid' => $allow_gid_raw,
                    'deny_cid'  => $deny_cid_raw,
                    'deny_gid'  => $deny_gid_raw,
                ]);
            }

            $created = \NativeWiki::create_wiki(
                $owner,
                $obs_hash,
                [
                    'rawName'      => $wiki_name,
                    'htmlName'     => escape_tags($wiki_name),
                    'urlName'      => \NativeWiki::name_encode($wiki_name),
                    'mimeType'     => $mime_type,
                    'typelock'     => $type_lock ? '1' : '0',
                    'postVisible'  => 1,
                ],
                $acl
            );

            if (!$created['success']) {
                Response::error(500, $created['message'] ?? 'Error creating wiki');
            }

            $rid = $created['item']['resource_id'] ?? null;
            if ($rid) {
                \NativeWikiPage::create_page($owner, $obs_hash, 'Home', $rid, $mime_type);
            }

            Response::send([
                'success'     => true,
                'resource_id' => $rid ?? '',
                'url_name'    => \NativeWiki::name_encode($wiki_name),
            ], [], 201);
        }

        // ── POST /api/wiki/:nick/:wikiName/:pageName/revert  →  revert page ────
        // ── POST /api/wiki/:nick/:wikiName/:pageName/rename  →  rename page ───
        // ── POST /api/wiki/:nick/:wikiName/:pageName         →  save page  ────
        $wikiName    = \NativeWiki::name_decode(\App::$argv[3] ?? '');
        $w           = $this->resolveWiki($owner, $wikiName);
        $rid         = $w['resource_id'];
        $perms       = \NativeWiki::get_permissions($rid, $uid, $obs_hash);

        if (!$perms['write']) {
            Response::error(403, 'Permission denied');
        }

        // ── POST /api/wiki/:nick/:wikiName/acl  →  save wiki ACL ─────────────
        // Changing the ACL itself stays owner-only — write_wiki access to the
        // resource shouldn't let a visitor grant themselves broader access.
        if ($argc === 5 && \App::$argv[4] === 'acl') {
            if (!local_channel() || local_channel() !== $uid) {
                Response::error(403, 'Owner access required');
            }
            $this->postAcl($owner, $rid);
            return;
        }

        // Revert
        if ($this->lastArgIs('revert')) {
            $pageParts   = $this->pagePartsWithout('revert');
            $pageUrlName = \NativeWiki::name_decode(implode('/', $pageParts));
            $revision    = intval($data['revision'] ?? 0);

            if ($revision <= 0) {
                Response::error(400, 'revision required');
            }

            $reverted = \NativeWikiPage::revert_page([
                'channel_id'    => $uid,
                'observer_hash' => $obs_hash,
                'resource_id'   => $rid,
                'pageUrlName'   => $pageUrlName,
                'commitHash'    => $revision,
            ]);

            if (!$reverted['success']) {
                Response::error(500, $reverted['message'] ?? 'Error reverting page');
            }

            $saved = \NativeWikiPage::save_page([
                'channel_id'    => $uid,
                'observer_hash' => $obs_hash,
                'resource_id'   => $rid,
                'pageUrlName'   => $pageUrlName,
                'content'       => $reverted['content'],
                'mimeType'      => $w['mimeType'] ?? 'text/bbcode',
            ]);

            if (!$saved['success']) {
                Response::error(500, $saved['message'] ?? 'Error saving reverted content');
            }

            $commit = \NativeWikiPage::commit([
                'commit_msg'    => 'Reverted to revision ' . $revision,
                'pageUrlName'   => $pageUrlName,
                'resource_id'   => $rid,
                'channel_id'    => $uid,
                'observer_hash' => $obs_hash,
                'revision'      => -1,
            ]);

            if ($commit['success']) {
                \NativeWiki::sync_a_wiki_item($uid, $commit['item_id'], $rid);
            }

            Response::send(['success' => true]);
        }

        // Rename
        if ($this->lastArgIs('rename')) {
            $pageParts   = $this->pagePartsWithout('rename');
            $pageUrlName = \NativeWiki::name_decode(implode('/', $pageParts));
            $newName     = trim($data['new_name'] ?? '');

            if (!$newName) {
                Response::error(400, 'new_name required');
            }

            $result = \NativeWikiPage::rename_page([
                'channel_id'    => $uid,
                'observer_hash' => $obs_hash,
                'resource_id'   => $rid,
                'pageUrlName'   => $pageUrlName,
                'pageNewName'   => $newName,
            ]);

            if (!$result['success']) {
                Response::error(422, $result['message'] ?? 'Error renaming page');
            }

            Response::send([
                'success'  => true,
                'url_name' => $result['page']['urlName'] ?? \NativeWiki::name_encode($newName),
            ]);
        }

        $pageParts   = array_slice(\App::$argv, 4);
        $pageUrlName = \NativeWiki::name_decode(implode('/', $pageParts));
        $content     = $data['content']    ?? '';
        $commit_msg  = $data['commit_msg'] ?? '';
        $mime_type   = $data['mime_type']  ?? ($w['mimeType'] ?? 'text/bbcode');

        $pageArgs = [
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'resource_id'   => $rid,
            'pageUrlName'   => $pageUrlName,
            'content'       => $content,
            'mimeType'      => $mime_type,
        ];

        // save_page only works on existing pages; if the page doesn't exist yet
        // (new page flow) we must create the page item first.
        $saved = \NativeWikiPage::save_page($pageArgs);

        if (!$saved['success'] && str_contains($saved['message'] ?? '', 'Page not found')) {
            $created = \NativeWikiPage::create_page($owner, $obs_hash, $pageUrlName, $rid, $mime_type);
            if (!$created['success']) {
                Response::error(500, $created['message'] ?? 'Error creating page');
            }
            $saved = \NativeWikiPage::save_page($pageArgs);
        }

        if (!$saved['success']) {
            Response::error(500, $saved['message'] ?? 'Error saving page');
        }

        // Commit
        $commit = \NativeWikiPage::commit([
            'commit_msg'    => $commit_msg ?: 'Page updated',
            'pageUrlName'   => $pageUrlName,
            'resource_id'   => $rid,
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'revision'      => -1,
        ]);

        if ($commit['success']) {
            \NativeWiki::sync_a_wiki_item($uid, $commit['item_id'], $rid);
        }

        Response::send([
            'success'    => true,
            'commit'     => $commit['success'] ? 'HEAD' : null,
        ]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function delete(): void
    {
        $obs_hash = Auth::requireLoggedInJson();
        $owner    = $this->resolveOwner();
        $uid      = intval($owner['channel_id']);

        $this->requireAddon($uid);
        $argc = count(\App::$argv);

        // ── DELETE /api/wiki/:nick/:wikiName  →  delete entire wiki ──────────
        // Deletes the whole resource (and its ACL), so this stays owner-only.
        if ($argc === 4) {
            if (!local_channel() || local_channel() !== $uid) {
                Response::error(403, 'Only the channel owner can delete');
            }

            $wikiName = \NativeWiki::name_decode(\App::$argv[3] ?? '');
            $w        = $this->resolveWiki($owner, $wikiName);
            $rid      = $w['resource_id'];

            $result = \NativeWiki::delete_wiki($uid, $obs_hash, $rid);
            if (!$result['success']) {
                Response::error(500, 'Error deleting wiki');
            }
            Response::send(['success' => true]);
        }

        $wikiName    = \NativeWiki::name_decode(\App::$argv[3] ?? '');
        $w           = $this->resolveWiki($owner, $wikiName);
        $rid         = $w['resource_id'];
        $perms       = \NativeWiki::get_permissions($rid, $uid, $obs_hash);

        if (!$perms['write']) {
            Response::error(403, 'Permission denied');
        }

        $pageParts   = array_slice(\App::$argv, 4);
        $pageUrlName = \NativeWiki::name_decode(implode('/', $pageParts));

        if ($pageUrlName === 'Home') {
            Response::error(400, 'Cannot delete the Home page');
        }

        $deleted = \NativeWikiPage::delete_page([
            'channel_id'    => $uid,
            'observer_hash' => $obs_hash,
            'resource_id'   => $rid,
            'pageUrlName'   => $pageUrlName,
        ]);

        if (!$deleted['success']) {
            Response::error(500, $deleted['message'] ?? 'Error deleting page');
        }

        \NativeWiki::sync_a_wiki_item($uid, 0, $rid);
        Response::send(['success' => true]);
    }
}
