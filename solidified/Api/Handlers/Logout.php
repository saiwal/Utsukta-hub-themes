<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Logout
{
    public function post(): void
    {
        Auth::requireLocalJson();

        $uid = \local_channel();
        \call_hooks('logging_out', ['channel_id' => $uid]);

        \App::$session->nuke();

        Response::send(['success' => true]);
    }
}
