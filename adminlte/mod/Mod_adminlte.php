<?php
namespace Zotlabs\Module;

use App;

class Adminlte extends \Zotlabs\Web\Controller {

    function get() {

        $uid = local_channel();
        if (! $uid) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not logged in']);
            killme();
        }

        $mode = trim($_GET['tour'] ?? '');
        $status = 'unknown';

        switch($mode) {

            case 'hq':
                set_pconfig($uid, 'adminlte', 'tour_hq', 1);
                $status = 'hq saved';
                break;

            case 'network':
                set_pconfig($uid, 'adminlte', 'tour_network', 1);
                $status = 'network saved';
                break;

            default:
                $status = get_pconfig($uid, 'adminlte_tour', 1);
                break;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'mode'   => $mode,
            'stored' => $status
        ]);

        killme();
    }
}
