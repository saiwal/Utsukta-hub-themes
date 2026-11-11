<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\ChatAddresses;
use Utsukta\SpaCore\Api\Response;

/**
 * Matrix / Delta Chat addresses — see Api/ChatAddresses.php.
 *
 * GET  /spa/chatids?xchan=<hash>   addresses of one contact (visitors: published ones only)
 * GET  /spa/chatids?self=1         your own published list (profile editor)
 * GET  /spa/chatids?type=matrix    your connections with a Matrix ID (new-DM picker)
 * POST /spa/chatids { self: [{type, value}] }           set your published list
 * POST /spa/chatids { xchan, items: [{type, value}] }   set your private entries for a connection
 */
class ChatIds
{
    public function get(): void
    {
        if (!empty($_GET['self'])) {
            Response::send(ChatAddresses::own(Auth::requireLocalGet()));
        }
        if (($_GET['type'] ?? '') === 'matrix') {
            Response::send(ChatAddresses::matrixForConnections(Auth::requireLocalGet(), (string) get_observer_hash()));
        }
        $hash = (string) ($_GET['xchan'] ?? '');
        if ($hash === '') Response::error(400, 'xchan required');
        Response::send(ChatAddresses::forXchan(intval(local_channel()), $hash, (string) get_observer_hash()));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        if (isset($body['self'])) {
            Response::send(ChatAddresses::setOwn($uid, (array) $body['self']));
        }

        $hash = (string) ($body['xchan'] ?? '');
        $abook = q("SELECT abook_id FROM abook WHERE abook_channel = %d AND abook_xchan = '%s' AND abook_self = 0 LIMIT 1",
            intval($uid), dbesc($hash));
        if (!$abook) Response::error(404, 'Connection not found');
        Response::send(ChatAddresses::setPrivate($uid, $hash, (array) ($body['items'] ?? [])));
    }
}
