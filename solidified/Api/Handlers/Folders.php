<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Folders
{
    public function get(): void
    {
        Auth::RequireLocalGet();

        $uid = local_channel();

        $r = q(
            "SELECT DISTINCT term FROM term WHERE uid = %d AND ttype = %d ORDER BY term ASC",
            intval($uid),
            intval(TERM_FILE)
        );

        $folders = $r ? array_column($r, 'term') : [];

        Response::send($folders);
    }
}
