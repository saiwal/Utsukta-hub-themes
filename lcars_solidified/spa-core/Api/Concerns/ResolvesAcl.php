<?php
namespace Utsukta\SpaCore\Api\Concerns;

/**
 * Item ACL packing/unpacking, shared by every handler that stores content
 * through item_store().
 *
 * Hubzilla keeps an item's ACL in four columns as "<hash1><hash2>…" plus
 * item_private and public_policy. Two composer conventions reach the API and
 * both are served here:
 *
 *   aclFromScope()          — { scope, allow_cid[], allow_gid[], … }
 *                             (Blocks, Webpages: a named scope, where
 *                             'connections' means public_policy = 'contacts')
 *   aclFromComposerInput()  — { scope, contact_allow[], group_allow[], …,
 *                             public_policy } (Articles, Cards: an explicit
 *                             public_policy rather than a 'connections' scope)
 *
 * Both return the same positional 6-tuple, in item-column order:
 *   [allow_cid, allow_gid, deny_cid, deny_gid, item_private, public_policy]
 *
 * Not used by Cal/Menus: their 'connections' scope copies the *channel's*
 * default ACL columns instead of setting a policy, which is a different
 * behaviour rather than a different spelling of this one.
 */
trait ResolvesAcl
{
    /** "<a><b>" -> ['a','b'] */
    private static function parseHashList(string $str): array
    {
        if (!$str) return [];
        preg_match_all('/<([^>]+)>/', $str, $m);
        return $m[1] ?? [];
    }

    /** ['a','b'] -> "<a><b>", dropping empties. */
    private static function packHashList(array $hashes): string
    {
        return implode('', array_map(fn($h) => '<' . $h . '>', array_filter($hashes)));
    }

    /**
     * Named-scope form. 'connections' sets item_private = 1 with
     * public_policy = 'contacts' — the mechanism item_permissions_sql()
     * checks via scopes_sql().
     */
    private function aclFromScope(string $scope, array $body, string $ownerHash): array
    {
        if ($scope === 'connections') {
            return ['', '', '', '', 1, 'contacts'];
        }

        if ($scope === 'private') {
            return ['<' . $ownerHash . '>', '', '', '', 1, ''];
        }

        if ($scope === 'custom') {
            $allow_cid = self::packHashList((array) ($body['allow_cid'] ?? []));
            $allow_gid = self::packHashList((array) ($body['allow_gid'] ?? []));
            $deny_cid  = self::packHashList((array) ($body['deny_cid']  ?? []));
            $deny_gid  = self::packHashList((array) ($body['deny_gid']  ?? []));

            return [$allow_cid, $allow_gid, $deny_cid, $deny_gid,
                ($allow_cid || $allow_gid) ? 1 : 0, ''];
        }

        // public — no ACL restrictions
        return ['', '', '', '', 0, ''];
    }

    /**
     * Explicit-list form: the raw contact_allow/group_allow/contact_deny/
     * group_deny arrays plus public_policy as sent by the SPA composers.
     */
    private function aclFromComposerInput(array $input, string $ownerHash): array
    {
        if (($input['scope'] ?? null) === 'private') {
            return ['<' . $ownerHash . '>', '', '', '', 1, ''];
        }

        $allow_cid     = self::packHashList((array) ($input['contact_allow'] ?? []));
        $allow_gid     = self::packHashList((array) ($input['group_allow']   ?? []));
        $deny_cid      = self::packHashList((array) ($input['contact_deny']  ?? []));
        $deny_gid      = self::packHashList((array) ($input['group_deny']    ?? []));
        $public_policy = trim($input['public_policy'] ?? '');

        $item_private = ($public_policy === 'contacts' || $allow_cid || $allow_gid) ? 1 : 0;

        return [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy];
    }
}
