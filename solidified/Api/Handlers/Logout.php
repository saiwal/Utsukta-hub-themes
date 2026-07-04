<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Logout
{
    public function post(): void
    {
        // No local-channel requirement: remote (OWA) visitors must be able
        // to log out too, and they have no local_channel().
        Csrf::validate();

        // call_hooks() takes $data by reference — must be a variable
        $args = ['channel_id' => \local_channel()];
        \call_hooks('logging_out', $args);

        if (!empty($_SESSION['delegate']) && !empty($_SESSION['delegate_push'])) {
            $_SESSION = $_SESSION['delegate_push'];
        } else {
            \App::$session->nuke();
        }

        Response::send(['success' => true]);
    }
}
