<?php
namespace Zotlabs\Module;

use App;

class Adminlte_tour extends \Zotlabs\Web\Controller {

    function get() {
        $uid = local_channel();
        if (!$uid) killme();

        // Default behavior = mark tour complete
        set_pconfig($uid, 'adminlte', 'tour_done', 1);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        killme();
    }
}
