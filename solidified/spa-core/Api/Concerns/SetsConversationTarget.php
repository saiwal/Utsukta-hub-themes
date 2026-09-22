<?php
// packages/spa-core/php/Api/Concerns/SetsConversationTarget.php

namespace Utsukta\SpaCore\Api\Concerns;

/**
 * The conversation Collection every locally-originated item carries.
 *
 * Core sets it in one place (Zotlabs\Module\Item::post, the block it labels
 * "Set the conversation target") which runs *before* any item-type branching —
 * so posts, comments, cards, articles, webpages and blocks all get one. The SPA
 * builds each of those in a different handler, which is exactly how they all
 * came to be missing it; this trait is the one place they now share.
 */
trait SetsConversationTarget
{
    /**
     * Deliver a stored item AND the collection activity item_store() created
     * alongside it.
     *
     * Any item carrying a Collection target makes item_store() also write an
     * `Add` activity and return its id as `approval_id` (include/items.php
     * ~5364). That Add is not cosmetic: Libzot::process_delivery() *rejects* a
     * plain Create whose tgt_type is a Collection unless it arrives as a
     * relay or a collection operation ("not a collection activity"), so the Add
     * is what actually carries the post to its recipients. Core summons a
     * second Notifier for it at every store site (Zotlabs\Module\Item:1182,
     * Like, Share, Vote); summoning only the main item delivers to remote AP
     * inboxes but to no zot recipient at all.
     */
    private static function summonWithApproval(string $cmd, array $post): void
    {
        if (!empty($post['item_id'])) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', $cmd, $post['item_id']]);
        }
        if (!empty($post['approval_id'])) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', $cmd, $post['approval_id']]);
        }
    }

    // Conversation collection, mirroring core Zotlabs\Module\Item::post (the
    // block core labels "Set the conversation target"). Remote hubs thread
    // replies on this: both Activity::store() and Libzot::process_delivery()
    // *drop* a relayed comment whose parent carries a Collection target unless
    // the receiving channel owns the conversation, so follower copies wait for
    // the owner's canonical relay. An item stored without one has its replies
    // accepted out of band instead, and every hub ends up with a different
    // partial version of the thread.
    //
    // $parent is the item that was replied to, null for a new thread. Its
    // parent_mid — not its mid — is the thread root, which matters because the
    // SPA lets you reply to a comment and core only ever passes the root here.
    private static function conversationTarget(int $uid, string $ownerHash, string $mid, ?array $parent = null): array
    {
        $c = q('SELECT channel_address, channel_hash FROM channel WHERE channel_id = %d LIMIT 1',
            intval($uid)
        );

        if ($c && $c[0]['channel_hash'] === $ownerHash) {
            return [
                'target' => [
                    'id'           => str_replace('/item/', '/conversation/', $parent ? $parent['parent_mid'] : $mid),
                    'type'         => 'Collection',
                    'attributedTo' => z_root() . '/channel/' . $c[0]['channel_address'],
                ],
                'tgt_type' => 'Collection',
            ];
        }

        // Someone else's conversation: carry their collection through unchanged.
        // item_store() passes a string target straight to the column, so the
        // JSON we read back out of the parent row needs no re-encoding.
        if ($parent && !empty($parent['target'])) {
            return ['target' => $parent['target'], 'tgt_type' => $parent['tgt_type']];
        }

        return ['target' => '', 'tgt_type' => ''];
    }

    // Map a scope string to an ACL array
    private static function scopeToAcl(string $scope, int $profileUid): array
    {
        if ($scope === 'private') {
            $channel = App::get_channel();
            return [
                'allow_cid' => '<' . $channel['channel_hash'] . '>',
                'allow_gid' => '',
                'deny_cid' => '',
                'deny_gid' => '',
            ];
        }
        if ($scope === 'contacts') {
            // Use the channel's configured default ACL
            $r = q('SELECT * FROM channel WHERE channel_id = %d LIMIT 1', $profileUid);
            $acl = new \Zotlabs\Access\AccessList($r ? $r[0] : App::get_channel());
            $g = $acl->get();
            return [
                'allow_cid' => $g['allow_cid'],
                'allow_gid' => $g['allow_gid'],
                'deny_cid' => $g['deny_cid'],
                'deny_gid' => $g['deny_gid'],
            ];
        }
        // public
        return ['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => ''];
    }
}
