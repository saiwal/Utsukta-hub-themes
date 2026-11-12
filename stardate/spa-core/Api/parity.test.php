<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Core-parity differ for the SPA's posting pipeline.
 *
 * The conversation-target bug was not a typo, it was a class: /spa/item
 * rebuilds a datarray that core's Zotlabs\Module\Item builds, and every column
 * core sets that we don't is a divergence nobody sees locally. Reading the two
 * files finds today's drift. This finds it every time, including columns a
 * future core release adds.
 *
 * Method: post the same logical thing twice — once through core, once through
 * the SPA handler — and diff the two stored `item` rows column by column.
 * Anything outside SKIP (below) failing to match is a parity bug in one
 * direction or the other.
 *
 * Core is drivable from the CLI because Zotlabs/Module/Item.php:149 turns on
 * $api_source from $_POST, :1194 returns the item_store() result instead of
 * killme(), and :190 honours nopush so core's probe never federates.
 *
 * The SPA handler always summons the Notifier, so probes use a self-only ACL
 * (allow_cid = the channel's own hash) — delivery then resolves to nobody.
 * Set PARITY_PUBLIC=1 only on a hub you don't mind posting from.
 *
 * Probe rows are deleted with a raw DELETE (not drop_item) so no tombstone
 * federates either.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/parity.test.php [nick]
 *
 * The `step` argv mode is internal — the driver spawns it and cleans up after
 * it. Invoking `step` by hand leaves the probe row behind.
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once('include/cli_startup.php');
cli_startup();

// Columns that cannot match between two separately-created items. Everything
// else must. Keep this list short and justified — a column added here to make
// the differ pass is a parity bug swept under the rug.
const SKIP = [
    'id', 'uuid', 'mid', 'parent', 'parent_mid', 'thr_parent',
    'plink', 'llink',                       // derived from mid/uuid
    'created', 'edited', 'commented', 'received', 'changed',
    'target',                               // holds the conversation mid; checked separately
    'obj',                                  // holds the item mid
    'sig',                                  // signature over the mid; presence-checked instead
];

// ---------------------------------------------------------------------------
// Subprocess step: one post, one process (both handlers exit when done)
// ---------------------------------------------------------------------------
if (($argv[1] ?? '') === 'step') {
    $side    = $argv[2];
    $nick    = $argv[3];
    $payload = json_decode($argv[4], true) ?: [];
    $argvIn  = json_decode($argv[5] ?? '[]', true) ?: [];

    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
    $x  = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));

    @session_start();
    $_SESSION['uid']              = intval($ch[0]['channel_id']);
    // Channel_calendar::post() reads get_account_id(); without this the event
    // datarray gets account 0 and event_store_event() silently stores nothing.
    $_SESSION['account_id']       = intval($ch[0]['channel_account_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    // parseJsonBody()'s form-data branch reads $_POST; php://input can't be
    // written from the CLI.
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';
    $_POST                        = $payload;

    App::$channel  = $ch[0];
    App::$observer = $x[0];

    if ($side === 'core') {
        require_once('include/items.php');
        $post = (new Zotlabs\Module\Item())->post();
        echo json_encode(['item_id' => intval($post['item_id'] ?? 0)]);
        exit;
    }

    // Core modules other than Item::post — Like, Share, Item::get('drop').
    // They killme() rather than returning, so nothing useful comes back on
    // stdout; the driver finds the row they created by querying for it.
    if ($side === 'mod') {
        require_once('include/items.php');
        App::$argv = $argvIn;
        App::$argc = count($argvIn);
        $_GET = $_POST = $_REQUEST = $payload;
        $cls = 'Zotlabs\\Module\\' . ucfirst($argvIn[0]);
        $mod = new $cls();
        if (method_exists($mod, 'init')) $mod->init();
        if (method_exists($mod, 'get'))  $mod->get();
        exit;
    }

    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    require_once($autoload);
    // argv either names its own route ('spa','cards',…) or is a tail under
    // /spa/item, which is what every case predating the app types passes.
    App::$argv = (($argvIn[0] ?? '') === 'spa') ? $argvIn : array_merge(['spa', 'item'], $argvIn);
    App::$argc = count(App::$argv);
    $cls = 'Utsukta\\SpaCore\\Api\\Handlers\\' . ucfirst(App::$argv[1]);
    (new $cls())->post();
    exit;
}

// ---------------------------------------------------------------------------
// Driver
// ---------------------------------------------------------------------------

// Probes are real posts and the SPA always summons the Notifier, so prefer a
// channel with no connections — then even a public probe federates to nobody.
$nick = $argv[1] ?? null;
if (!$nick) {
    $c = q("SELECT channel_address FROM channel c WHERE channel_removed = 0 AND channel_system = 0
            AND (SELECT COUNT(*) FROM abook a WHERE a.abook_channel = c.channel_id AND a.abook_self = 0) = 0
            ORDER BY channel_id LIMIT 1");
    if (!$c) {
        echo "SKIP  no connection-free channel to probe with.\n";
        echo "      Make one, or name a channel and set PARITY_ALLOW_CONNECTED=1\n";
        echo "      (its probe posts will then really federate).\n";
        exit(0);
    }
    $nick = $c[0]['channel_address'];
}
$ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
if (!$ch) { echo "no such channel: $nick\n"; exit(2); }
$uid   = intval($ch[0]['channel_id']);
$hash  = $ch[0]['channel_hash'];
$conns = intval(q("SELECT COUNT(*) AS n FROM abook WHERE abook_channel = %d AND abook_self = 0", $uid)[0]['n']);

if ($conns && !getenv('PARITY_ALLOW_CONNECTED')) {
    echo "REFUSE  $nick has $conns connection(s); its probe posts would federate.\n";
    echo "        Use a connection-free channel, or set PARITY_ALLOW_CONNECTED=1.\n";
    exit(2);
}

echo "channel: $nick (uid $uid, $conns connections)\n";

$fail = 0;
$probes = [];
$startedAt = datetime_convert();

// A second local xchan to aim a custom ACL at — local, so nothing leaves the hub.
$other = q("SELECT channel_hash FROM channel WHERE channel_id != %d AND channel_removed = 0
            AND channel_system = 0 LIMIT 1", intval($uid));
$otherHash = $other ? $other[0]['channel_hash'] : null;

function step(string $side, string $nick, array $payload, array $argvIn = []): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
        . escapeshellarg($side) . ' ' . escapeshellarg($nick) . ' '
        . escapeshellarg(json_encode($payload)) . ' ' . escapeshellarg(json_encode($argvIn));
    $out = shell_exec($cmd . ' 2>&1');
    $j   = json_decode((string) $out, true);
    if (!is_array($j)) {
        echo "      raw: " . substr((string) $out, 0, 500) . "\n";
        return [];
    }
    return $j;
}

/** Drive a core module that exits instead of returning; returns its raw output,
 *  which is the only thing that explains a module that refused. */
function stepMod(string $nick, array $argvIn, array $get = []): string
{
    return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step mod '
        . escapeshellarg($nick) . ' ' . escapeshellarg(json_encode($get)) . ' '
        . escapeshellarg(json_encode($argvIn)) . ' 2>&1');
}

/** Print why a core module produced nothing. */
function why(string $side, string $out): void
{
    echo "      $side said: " . substr(trim(preg_replace('/\s+/', ' ', $out)), 0, 300) . "\n";
}

function maskUuids(string $v): string
{
    return preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<uuid>', $v);
}

function maxItemId(): int
{
    $r = dbq("SELECT MAX(id) AS m FROM item");
    return intval($r[0]['m'] ?? 0);
}

/** Newest row matching a WHERE fragment, or null. */
function latest(string $where): ?array
{
    $r = dbq("SELECT * FROM item WHERE $where ORDER BY id DESC LIMIT 1");
    return $r ? $r[0] : null;
}

/** Response::send() wraps in {data:…}, and the id sits at a different depth
 *  per endpoint — just find the first 'iid' anywhere in the response. */
function findIid($v): int
{
    if (!is_array($v)) return 0;
    if (isset($v['iid'])) return intval($v['iid']);
    foreach ($v as $sub) {
        if (is_array($sub) && ($id = findIid($sub))) return $id;
    }
    return 0;
}

// Core's 'suppress_duplicates' feature (Zotlabs/Module/Item.php:999) rejects a
// second post carrying the same body, so every probe needs its own. The SPA
// implements no such guard — noted as a Phase 1 finding, not worked around.
function probeBody(string $kind = 'probe'): string
{
    return 'parity ' . $kind . ' ' . bin2hex(random_bytes(6));
}

function row(int $id): ?array
{
    $r = q("SELECT * FROM item WHERE id = %d LIMIT 1", intval($id));
    return $r ? $r[0] : null;
}

/** The slug, the group membership and the embed index live in iconfig, not in
 *  `item` — and item_store_update() wipes and rewrites the lot, so a column
 *  diff alone would miss an app item losing its slug. */
function diffIconfig(string $label, int $coreId, int $spaId): void
{
    $get = function (int $id) {
        $r = dbq("SELECT cat, k, v FROM iconfig WHERE iid = " . intval($id) . " ORDER BY cat, k");
        $out = [];
        // Keys the SPA owns and core has no equivalent for (kanban templates,
        // the embed index, the Markdown source stash). Their absence on the core
        // side is not a divergence.
        $spaOnly = ['card/template', 'spa/embeds', 'spa/md_source', 'spa/md_hash', 'spa/local_only'];
        foreach ($r ?: [] as $one) {
            $key = $one['cat'] . '/' . $one['k'];
            if (in_array($key, $spaOnly, true)) continue;
            $out[$key] = maskUuids((string) $one['v']);
        }
        return $out;
    };
    $c = $get($coreId);
    $s = $get($spaId);

    if ($c === $s) { ok("$label iconfig — " . (count($c) ?: 'none') . " entr(ies), identical"); return; }

    $detail = '';
    foreach (array_unique(array_merge(array_keys($c), array_keys($s))) as $k) {
        if (($c[$k] ?? null) === ($s[$k] ?? null)) continue;
        $detail .= sprintf("      %-22s spa=%-24s core=%s\n", $k,
            json_encode($s[$k] ?? null), json_encode($c[$k] ?? null));
    }
    bad("$label iconfig", $detail);
}

function ok(string $label): void { echo "ok    $label\n"; }
function bad(string $label, string $detail = ''): void
{
    global $fail; $fail++;
    echo "FAIL  $label\n" . ($detail ? $detail : '');
}

/** Column-by-column diff of two item rows. */
function diff(string $label, ?array $core, ?array $spa, array $extraSkip = []): void
{
    if (!$core || !$spa) {
        bad("$label — " . (!$core ? 'core' : 'spa') . " probe was not stored");
        return;
    }

    $cols = [];
    foreach ($core as $col => $want) {
        if (in_array($col, SKIP, true) || in_array($col, $extraSkip, true)) continue;
        if (!array_key_exists($col, $spa)) { $cols[$col] = ['(missing)', $want]; continue; }
        $a = (string) $spa[$col];
        $b = (string) $want;
        // A reaction body quotes the permalink of the thing it reacts to, so
        // the two sides differ by the uuid each minted. Compare modulo uuids.
        if ($col === 'body') { $a = maskUuids($a); $b = maskUuids($b); }
        if ($a !== $b) $cols[$col] = [$spa[$col], $want];
    }

    // Not value comparisons — sig signs the mid and obj embeds it, so only
    // whether each side produces one at all is comparable.
    foreach (['sig' => ['signed', 'unsigned'], 'obj' => ['present', 'absent']] as $col => [$yes, $no]) {
        if ((bool) ($core[$col] ?? '') !== (bool) ($spa[$col] ?? '')) {
            $cols["$col(presence)"] = [($spa[$col] ?? '') ? $yes : $no,
                                       ($core[$col] ?? '') ? $yes : $no];
        }
    }

    if (!$cols) { ok("$label — every non-derived column matches core"); return; }

    $detail = '';
    foreach ($cols as $col => [$got, $want]) {
        $detail .= sprintf("      %-18s spa=%-30s core=%s\n", $col,
            json_encode(substr((string) $got, 0, 110)), json_encode(substr((string) $want, 0, 110)));
    }
    bad("$label — " . count($cols) . " column(s) diverge from core", $detail);
}

/** The conversation Collection is mid-dependent, so it is checked by shape. */
function diffTarget(string $label, ?array $core, ?array $spa): void
{
    if (!$core || !$spa) return;

    $c = json_decode($core['target'] ?: 'null', true);
    $s = json_decode($spa['target'] ?: 'null', true);

    if (!$c && !$s) { ok("$label target — neither stores one (core agrees)"); return; }
    if (!$c) { echo "SKIP  $label target — core stored none to compare\n"; return; }

    $want = str_replace('/item/', '/conversation/', $spa['parent_mid']);
    if ($s
        && ($s['type'] ?? '') === ($c['type'] ?? '')
        && ($s['attributedTo'] ?? '') === ($c['attributedTo'] ?? '')
        && ($spa['tgt_type'] ?? '') === ($core['tgt_type'] ?? '')
        && ($s['id'] ?? '') === $want) {
        ok("$label target — same shape, id is the spa row's own conversation");
        return;
    }
    bad("$label target", "      spa  " . json_encode($s) . "\n      core " . json_encode($c) . "\n");
}

/** Post the same thing through both sides and return the two stored rows. */
function pair(string $nick, array $coreExtra, array $spaPayload, array $spaArgv = []): array
{
    global $uid, $probes;

    $coreRes = step('core', $nick, $coreExtra + ['api_source' => 1, 'nopush' => 1,
        'profile_uid' => $uid, 'webpage' => 0]);
    $spaRes  = step('spa', $nick, $spaPayload, $spaArgv);

    $ci = intval($coreRes['item_id'] ?? 0);
    $si = findIid($spaRes);
    $probes[] = $ci; $probes[] = $si;
    echo "  core id=$ci  spa id=$si\n";
    if (!$ci) echo "      core said: " . json_encode($coreRes) . "\n";
    if (!$si) echo "      spa  said: " . substr(json_encode($spaRes), 0, 300) . "\n";
    return [row($ci), row($si)];
}

// ---------------------------------------------------------------------------
// Permission limits: item_store() substitutes 'contacts' for an omitted
// comment_policy and '' for an omitted public_policy, and those are ALSO what
// core derives from a default channel — so a handler that sets neither looks
// identical to one that sets both. The second pass moves the limits off those
// values so the columns are actually exercised.
// ---------------------------------------------------------------------------
$savedVs = \Zotlabs\Access\PermissionLimits::Get($uid, 'view_stream');
$savedPc = \Zotlabs\Access\PermissionLimits::Get($uid, 'post_comments');

function restoreLimits(): void
{
    global $uid, $savedVs, $savedPc;
    \Zotlabs\Access\PermissionLimits::Set($uid, 'view_stream', intval($savedVs));
    \Zotlabs\Access\PermissionLimits::Set($uid, 'post_comments', intval($savedPc));
}
register_shutdown_function('restoreLimits');

// Every paired case posts one body through core and then through the spa, which
// suppress_duplicates (on by default) would read as a repeat. It is exercised
// deliberately by its own case instead.
$savedDup = get_pconfig($uid, 'feature', 'suppress_duplicates');
set_pconfig($uid, 'feature', 'suppress_duplicates', 0);
register_shutdown_function(function () use ($uid, $savedDup) {
    $savedDup === false
        ? del_pconfig($uid, 'feature', 'suppress_duplicates')
        : set_pconfig($uid, 'feature', 'suppress_duplicates', $savedDup);
});

function runCases(string $nick, string $pass): void
{
    global $uid, $hash, $otherHash, $probes;


    // --- top-level post, three audiences ------------------------------------
    $audiences = [
        'private (self only)' => [
            ['contact_allow' => [$hash]],
            ['scope' => 'private'],
        ],
        'public' => [
            ['contact_allow' => [], 'group_allow' => [], 'contact_deny' => [], 'group_deny' => []],
            ['scope' => 'public'],
        ],
    ];
    if ($otherHash) {
        $audiences['custom (one contact)'] = [
            ['contact_allow' => [$otherHash]],
            ['scope' => 'custom', 'contact_allow' => [$otherHash]],
        ];
    }

    $firstPublic = null;

    foreach ($audiences as $label => [$coreAcl, $spaAcl]) {
        echo "\n[$pass] top-level post — $label\n";
        $tag = probeBody();
        [$c, $s] = pair($nick,
            $coreAcl + ['title' => 'parity probe', 'body' => $tag],
            $spaAcl  + ['profile_uid' => $uid, 'title' => 'parity probe',
                        'body' => $tag, 'mimetype' => 'text/bbcode']);
        diff("[$pass] post/$label", $c, $s);
        diffTarget("[$pass] post/$label", $c, $s);
        if ($label === 'public' && $c) $firstPublic = [$c, $s];
    }

    if (!$firstPublic) return;
    [$coreRoot, $spaRoot] = $firstPublic;

    // --- duplicate suppression ---------------------------------------------
    // Core drops a second post with the same body inside two minutes when the
    // channel has the feature on (it is on by default). Each side is tested on
    // its own body — sharing one would just have core's probe block the spa's.
    foreach ([1 => 'on', 0 => 'off'] as $on => $word) {
        set_pconfig($uid, 'feature', 'suppress_duplicates', $on);
        echo "\n[$pass] duplicate suppression ($word)\n";

        $twice = function (string $side) use ($nick, $uid, &$probes) {
            $b = probeBody('dup');
            $mk = fn() => $side === 'core'
                ? intval((step('core', $nick, ['api_source' => 1, 'nopush' => 1,
                    'profile_uid' => $uid, 'webpage' => 0, 'contact_allow' => [],
                    'group_allow' => [], 'body' => $b])['item_id'] ?? 0))
                : findIid(step('spa', $nick, ['scope' => 'public', 'profile_uid' => $uid,
                    'body' => $b, 'mimetype' => 'text/bbcode']));
            $a = $mk(); $c = $mk();
            $probes[] = $a; $probes[] = $c;
            return [$a, $c];
        };

        [$c1, $c2] = $twice('core');
        [$s1, $s2] = $twice('spa');
        echo "  core first=$c1 second=$c2\n  spa  first=$s1 second=$s2\n";

        if (!$c1 || !$s1) { bad("[$pass] duplicate suppression ($word) — a first post was refused"); continue; }

        $want = $on ? 0 : 1;      // was the second one stored?
        $got  = [($c2 ? 1 : 0), ($s2 ? 1 : 0)];
        if ($got === [$want, $want]) {
            ok("[$pass] duplicate suppression ($word) — both sides "
                . ($on ? 'blocked the repeat' : 'allowed the repeat'));
        } else {
            bad("[$pass] duplicate suppression ($word)",
                "      core " . ($c2 ? 'stored' : 'blocked')
                . ", spa " . ($s2 ? 'stored' : 'blocked')
                . " — expected both " . ($on ? 'blocked' : 'stored') . "\n");
        }
    }
    set_pconfig($uid, 'feature', 'suppress_duplicates', 0);   // off for the paired cases below

    // --- comment ------------------------------------------------------------
    echo "\n[$pass] comment on a public thread\n";
    $ctag = probeBody('reply');
    [$c, $s] = pair($nick,
        ['parent' => intval($coreRoot['id']), 'body' => $ctag],
        ['body' => $ctag, 'mimetype' => 'text/bbcode'],
        [$spaRoot['uuid'], 'comment']);
    diff("[$pass] comment", $c, $s);
    diffTarget("[$pass] comment", $c, $s);

    // --- nested reply -------------------------------------------------------
    // Core cannot produce one (its comment box always targets the thread root),
    // so there is no row to diff against — assert the invariants instead.
    if ($s) {
        echo "\n[$pass] nested reply (spa-only shape — invariants, no core row)\n";
        $ntag = probeBody('reply');
        $res  = step('spa', $nick, ['body' => $ntag, 'mimetype' => 'text/bbcode'],
            [$s['uuid'], 'comment']);
        $nid  = findIid($res);
        $probes[] = $nid;
        $n = row($nid);
        echo "  spa id=$nid\n";

        if (!$n) { bad("[$pass] nested reply was not stored"); }
        else {
            $conv = str_replace('/item/', '/conversation/', $s['parent_mid']);
            $t = json_decode($n['target'] ?: 'null', true);
            $n['parent_mid'] === $s['parent_mid']
                ? ok("[$pass] nested reply re-parented to the thread root")
                : bad("[$pass] nested reply parent_mid", "      got " . $n['parent_mid'] . "\n");
            $n['thr_parent'] === $s['mid']
                ? ok("[$pass] nested reply thr_parent is the comment it answers")
                : bad("[$pass] nested reply thr_parent", "      got " . $n['thr_parent'] . "\n");
            ($t['id'] ?? '') === $conv
                ? ok("[$pass] nested reply joins the ROOT conversation")
                : bad("[$pass] nested reply conversation", "      got " . json_encode($t) . "\n      want $conv\n");
            intval($n['item_thread_top']) === 0
                ? ok("[$pass] nested reply is not a thread top")
                : bad("[$pass] nested reply item_thread_top");
        }
    }

    // --- edit ---------------------------------------------------------------
    // editItem() bypasses item_store_update() and hand-rolls the term rebuild,
    // so this is the densest patch of hand-written writes in the handler.
    echo "\n[$pass] edit a top-level post\n";
    $etag = probeBody('edited');
    step('core', $nick, ['api_source' => 1, 'nopush' => 1, 'profile_uid' => $uid, 'webpage' => 0,
        'post_id' => intval($coreRoot['id']), 'title' => 'parity probe edited',
        'body' => $etag, 'summary' => '']);
    step('spa', $nick, ['title' => 'parity probe edited', 'body' => $etag,
        'mimetype' => 'text/bbcode'], [$spaRoot['uuid'], 'edit']);
    diff("[$pass] edit", row(intval($coreRoot['id'])), row(intval($spaRoot['id'])));

    // --- like ---------------------------------------------------------------
    // Core: /like/<item id>?verb=like (Zotlabs\Module\Like::get, argc()==2).
    echo "\n[$pass] like\n";
    $before = maxItemId();
    stepMod($nick, ['like', intval($coreRoot['id'])], ['verb' => 'like']);
    $cLike = latest("id > $before AND uid = $uid AND verb = 'Like'");

    $before = maxItemId();
    step('spa', $nick, [], [$spaRoot['uuid'], 'like']);
    $sLike = latest("id > $before AND uid = $uid AND verb = 'Like'");

    $probes[] = intval($cLike['id'] ?? 0); $probes[] = intval($sLike['id'] ?? 0);
    echo "  core id=" . ($cLike['id'] ?? 0) . "  spa id=" . ($sLike['id'] ?? 0) . "\n";
    diff("[$pass] like", $cLike, $sLike);
    diffTarget("[$pass] like", $cLike, $sLike);

    // --- repeat / boost -----------------------------------------------------
    // Core's Share module is a boost: an Announce hung off the original thread.
    // The SPA spells that /repeat. (Its /reshare is a different feature — a
    // quote-post — checked separately below.)
    echo "\n[$pass] repeat (core Share vs spa /repeat)\n";
    $before = maxItemId();
    stepMod($nick, ['share', intval($coreRoot['id'])]);
    $cRep = latest("id > $before AND uid = $uid AND verb = 'Announce'");

    $before = maxItemId();
    step('spa', $nick, [], [$spaRoot['uuid'], 'repeat']);
    $sRep = latest("id > $before AND uid = $uid AND verb = 'Announce'");

    $probes[] = intval($cRep['id'] ?? 0); $probes[] = intval($sRep['id'] ?? 0);
    echo "  core id=" . ($cRep['id'] ?? 0) . "  spa id=" . ($sRep['id'] ?? 0) . "\n";
    if (!$cRep && !$sRep) {
        ok("[$pass] repeat — both sides refused (root is private)");
    } else {
        diff("[$pass] repeat", $cRep, $sRep);
    }

    // --- quote reshare (spa-only shape) -------------------------------------
    echo "\n[$pass] quote reshare (spa-only shape — invariants, no core module)\n";
    $before = maxItemId();
    step('spa', $nick, [], [$spaRoot['uuid'], 'reshare']);
    $q = latest("id > $before AND uid = $uid AND mid = parent_mid");
    $probes[] = intval($q['id'] ?? 0);
    echo "  spa id=" . ($q['id'] ?? 0) . "\n";
    if (!$q && intval($spaRoot['item_private'])) {
        ok("[$pass] quote reshare — refused on a private root, as core's Share does");
    } elseif (!$q) { bad("[$pass] quote reshare was not stored"); }
    else {
        intval($q['item_thread_top']) === 1
            ? ok("[$pass] quote reshare is its own thread top")
            : bad("[$pass] quote reshare item_thread_top");
        str_contains($q['body'], '[share')
            ? ok("[$pass] quote reshare embeds a [share] block")
            : bad("[$pass] quote reshare body", "      " . substr($q['body'], 0, 120) . "\n");
        str_contains((string) $q['tgt_type'], 'Collection')
            ? ok("[$pass] quote reshare opens its own conversation")
            : bad("[$pass] quote reshare tgt_type", "      got '" . $q['tgt_type'] . "'\n");
    }

    // --- app item types ------------------------------------------------------
    // Core builds all four through the same Item::post(), with $_POST['webpage']
    // carrying the item type and 'pagetitle' the slug.
    // Columns an app type legitimately owns that core leaves alone.
    $appSkip = ['article' => ['lang']];   // the SPA's articles carry a language (translations)

    $appTypes = [
        'card'    => [ITEM_TYPE_CARD,    'cards',     fn($slug, $t, $b) => ['title' => $t, 'body' => $b, 'slug' => $slug, 'mimetype' => 'text/bbcode']],
        // 'lang' is required by the SPA's article handler (it supports
        // translations); core has no such requirement.
        'article' => [ITEM_TYPE_ARTICLE, 'articles',  fn($slug, $t, $b) => ['title' => $t, 'body' => $b, 'slug' => $slug, 'mimetype' => 'text/bbcode', 'lang' => 'en']],
        'webpage' => [ITEM_TYPE_WEBPAGE, 'webpages',  fn($slug, $t, $b) => ['nick' => null, 'action' => 'create', 'title' => $t, 'body' => $b, 'pagetitle' => $slug, 'mimetype' => 'text/bbcode', 'scope' => 'public']],
        'block'   => [ITEM_TYPE_BLOCK,   'blocks',    fn($slug, $t, $b) => ['nick' => null, 'action' => 'create', 'title' => $t, 'body' => $b, 'name' => $slug, 'mimetype' => 'text/bbcode', 'scope' => 'public']],
    ];

    foreach ($appTypes as $name => [$type, $route, $mk]) {
        echo "\n[$pass] $name\n";
        $slug  = 'parity-' . $name . '-' . bin2hex(random_bytes(3));
        $title = 'parity ' . $name;
        $body  = probeBody($name);

        $coreRes = step('core', $nick, ['api_source' => 1, 'nopush' => 1, 'profile_uid' => $uid,
            'webpage' => $type, 'pagetitle' => $slug, 'title' => $title, 'body' => $body,
            'contact_allow' => [], 'group_allow' => [], 'contact_deny' => [], 'group_deny' => []]);

        $payload = $mk($slug, $title, $body);
        if (array_key_exists('nick', $payload)) $payload['nick'] = $nick;
        $argv = array_key_exists('action', $payload) ? ['spa', $route] : ['spa', $route, $nick];
        $spaRes = step('spa', $nick, $payload, $argv);

        $ci = intval($coreRes['item_id'] ?? 0);
        $si = findIid($spaRes);
        if (!$si) {
            // these handlers answer with the stored row rather than an iid
            // must exclude core's row, or a spa handler that stored nothing
            // silently gets diffed against core's own probe and "passes"
            $si = intval(latest("uid = $uid AND item_type = " . intval($type)
                . " AND title = '" . dbesc($title) . "' AND id != " . intval($ci))['id'] ?? 0);
        }
        $probes[] = $ci; $probes[] = $si;
        echo "  core id=$ci  spa id=$si\n";
        if (!$ci) echo "      core said: " . json_encode($coreRes) . "\n";
        if (!$si) echo "      spa  said: " . substr(json_encode($spaRes), 0, 300) . "\n";

        diff("[$pass] $name", row($ci), row($si), $appSkip[$name] ?? []);
        if ($ci && $si) diffIconfig("[$pass] $name", $ci, $si);
    }

    // --- poll + vote ---------------------------------------------------------
    // Core builds a poll through Item::post() with poll_answers[]; voting is its
    // own module (Zotlabs\Module\Vote, argv(1) = the poll's item id).
    echo "\n[$pass] poll\n";
    $ptag    = probeBody('poll');
    $answers = ['parity yes', 'parity no'];
    [$cPoll, $sPoll] = pair($nick,
        ['contact_allow' => [], 'group_allow' => [], 'body' => $ptag,
         'poll_answers' => $answers, 'poll_expire_value' => 1, 'poll_expire_unit' => 'Days'],
        ['scope' => 'public', 'profile_uid' => $uid, 'body' => $ptag, 'mimetype' => 'text/bbcode',
         'poll_answers' => $answers, 'poll_expire_value' => 1, 'poll_expire_unit' => 'Days']);
    // comments_closed is minted from the poll's end time on each side, a second
    // or two apart — compared for closeness below rather than equality, so the
    // fix that sets it at all stays guarded without the clock making the suite
    // flaky. Everything else, including expires, must match exactly.
    diff("[$pass] poll", $cPoll, $sPoll, ['comments_closed']);
    diffTarget("[$pass] poll", $cPoll, $sPoll);

    if ($cPoll && $sPoll) {
        $c = strtotime($cPoll['comments_closed']);
        $v = strtotime($sPoll['comments_closed']);
        $set = $v && $sPoll['comments_closed'] > '0002-01-01';
        $set && abs($c - $v) <= 120
            ? ok("[$pass] poll comments_closed within 2 minutes of core's")
            : bad("[$pass] poll comments_closed",
                "      spa=" . $sPoll['comments_closed'] . "  core=" . $cPoll['comments_closed'] . "\n");
    }

    if ($cPoll && $sPoll) {
        // Voting on your OWN poll is broken in core on this build, and the
        // conversation Collection is why: core emits an 'Add' activity as a
        // child of every post it stores, and Activity::update_poll() treats
        // *any* child of the poll by the voter as a prior vote
        // (Zotlabs/Lib/Activity.php ~2105, "already voted"). Both sides hit it,
        // so the check is that they agree — if core is ever fixed, this case
        // starts diffing two real Answer rows instead.
        echo "\n[$pass] poll vote\n";
        $before = maxItemId();
        $out = stepMod($nick, ['vote', intval($cPoll['id'])], ['answer' => $answers[0]]);
        $cVote = latest("id > $before AND uid = $uid AND obj_type = 'Answer'");

        $before = maxItemId();
        step('spa', $nick, ['answer' => $answers[0]], [$sPoll['uuid'], 'vote']);
        $sVote = latest("id > $before AND uid = $uid AND obj_type = 'Answer'");

        $probes[] = intval($cVote['id'] ?? 0); $probes[] = intval($sVote['id'] ?? 0);
        echo "  core id=" . ($cVote['id'] ?? 0) . "  spa id=" . ($sVote['id'] ?? 0) . "\n";
        if (!$cVote && !$sVote) {
            ok("[$pass] poll vote — both sides refused (core's own 'already voted' bug)");
        } else {
            diff("[$pass] poll vote", $cVote, $sVote);
        }
    }

    // --- event ----------------------------------------------------------------
    // NOT parity-checked against core. Zotlabs\Module\Channel_calendar::post()
    // could not be driven headlessly here: it killme()s silently somewhere past
    // its ACL block, and the cost of bisecting that outweighed the case. Both
    // sides do end at the same core functions (event_store_event() +
    // event_store_item()), so the divergence surface is the datarray fed in —
    // which is exactly what stays unverified. Invariants only, and this case is
    // listed as an open gap rather than pretending to be a diff.
    echo "\n[$pass] event (spa invariants only — core module not drivable headlessly)\n";
    $etitle = 'parity event ' . bin2hex(random_bytes(4));
    $start  = datetime_convert('UTC', 'UTC', 'now + 1 day', 'Y-m-d H:i:s');
    $end    = datetime_convert('UTC', 'UTC', 'now + 1 day + 1 hour', 'Y-m-d H:i:s');

    $before = maxItemId();
    step('spa', $nick, ['title' => $etitle, 'description' => 'parity event body',
        'location' => 'here', 'start' => $start, 'end' => $end,
        'timezone' => 'UTC', 'scope' => 'public'], ['spa', 'cal']);
    $sEv = latest("id > $before AND uid = $uid AND obj_type = 'Event'");
    $probes[] = intval($sEv['id'] ?? 0);
    echo "  spa id=" . ($sEv['id'] ?? 0) . "\n";

    if (!$sEv) { bad("[$pass] event was not stored"); }
    else {
        $sEv['verb'] === 'Invite'
            ? ok("[$pass] event verb is Invite, as event_store_item writes it")
            : bad("[$pass] event verb", "      got '" . $sEv['verb'] . "'\n");
        $sEv['resource_type'] === 'event' && $sEv['resource_id']
            ? ok("[$pass] event item points at its event row")
            : bad("[$pass] event resource link");
        str_contains((string) $sEv['tgt_type'], 'Collection')
            ? ok("[$pass] event opens its own conversation")
            : bad("[$pass] event tgt_type", "      got '" . $sEv['tgt_type'] . "'\n");

        // --- RSVP ------------------------------------------------------------
        // Driven against the same (spa-made) event on both sides, so the RSVP
        // shape is compared even though the event case above is not.
        echo "\n[$pass] rsvp (accept)\n";
        $before = maxItemId();
        stepMod($nick, ['like', intval($sEv['id'])], ['verb' => 'accept']);
        $cR = latest("id > $before AND uid = $uid AND verb = 'Accept'");

        // core's Like refuses a second RSVP by the same author, so the spa's
        // must hang off a second event of its own.
        $before = maxItemId();
        step('spa', $nick, ['title' => $etitle . ' b', 'description' => 'parity event body',
            'location' => 'here', 'start' => $start, 'end' => $end,
            'timezone' => 'UTC', 'scope' => 'public'], ['spa', 'cal']);
        $sEv2 = latest("id > $before AND uid = $uid AND obj_type = 'Event'");
        $probes[] = intval($sEv2['id'] ?? 0);

        $before = maxItemId();
        step('spa', $nick, [], [$sEv2['uuid'], 'accept']);
        $sR = latest("id > $before AND uid = $uid AND verb = 'Accept'");

        $probes[] = intval($cR['id'] ?? 0); $probes[] = intval($sR['id'] ?? 0);
        echo "  core id=" . ($cR['id'] ?? 0) . "  spa id=" . ($sR['id'] ?? 0) . "\n";
        // each RSVP quotes its own event's permalink and inherits its parent ids
        diff("[$pass] rsvp", $cR, $sR);
    }

    // --- delete (last: it destroys both roots) ------------------------------
    echo "\n[$pass] delete\n";
    stepMod($nick, ['item', 'drop', intval($coreRoot['id'])]);
    step('spa', $nick, [], [$spaRoot['uuid'], 'delete']);
    diff("[$pass] delete (tombstone)", row(intval($coreRoot['id'])), row(intval($spaRoot['id'])));
}


// ---------------------------------------------------------------------------
// Delivery. The one thing a column diff cannot see.
//
// Adding the conversation target made item_store() also write an `Add`
// collection activity and return its id as `approval_id`, and
// Libzot::process_delivery() rejects a plain Create whose tgt_type is a
// Collection unless it arrives as a relay or a collection operation — so the
// Add is what carries the post to zot recipients. Core summons a second
// Notifier for it; the SPA did not. The two stored rows stayed byte-identical
// and this suite passed green while nothing was delivered to anyone.
//
// Probes are real public posts, so this only runs against a channel whose
// connections are ALL local — nothing leaves the hub. PARITY_DELIVERY=1 forces
// it on a channel with remote connections.
// ---------------------------------------------------------------------------
function deliveryCase(): void
{
    global $fail, $startedAt;

    $force = (bool) getenv('PARITY_DELIVERY');
    $rows  = q("SELECT c.channel_address nick, c.channel_id uid,
               SUM(CASE WHEN lc.channel_id IS NOT NULL THEN 1 ELSE 0 END) loc,
               SUM(CASE WHEN lc.channel_id IS NULL     THEN 1 ELSE 0 END) rem
        FROM channel c
        JOIN abook a ON a.abook_channel = c.channel_id AND a.abook_self = 0
        LEFT JOIN channel lc ON lc.channel_hash = a.abook_xchan AND lc.channel_removed = 0
        WHERE c.channel_removed = 0 AND c.channel_system = 0
        GROUP BY c.channel_address, c.channel_id
        HAVING loc > 0 " . ($force ? "" : "AND rem = 0") . "
        ORDER BY loc DESC LIMIT 1");

    if (!is_array($rows)) {
        bad('[delivery] channel query did not return rows',
            "      got " . (is_object($rows) ? get_class($rows) : gettype($rows))
            . " — dba_pdo::q() only treats a string starting with 'select' as a\n"
            . "      query, so the SQL must not begin with a newline\n");
        return;
    }

    if (!$rows) {
        echo "SKIP  delivery — no channel with local-only connections to probe with.\n";
        echo "      Connect two local channels, or set PARITY_DELIVERY=1 to use one\n";
        echo "      with remote connections (its probe posts really will federate).\n";
        return;
    }

    $nick = $rows[0]['nick'];
    $uid  = intval($rows[0]['uid']);
    echo "\n[delivery] poster $nick (uid $uid, {$rows[0]['loc']} local / {$rows[0]['rem']} remote connections)\n";

    $probe = function (string $side) use ($nick, $uid) {
        $body = probeBody('deliv-' . $side);
        if ($side === 'core') {
            // no nopush here: delivery is the point
            step('core', $nick, ['api_source' => 1, 'profile_uid' => $uid, 'webpage' => 0,
                'contact_allow' => [], 'group_allow' => [], 'contact_deny' => [], 'group_deny' => [],
                'title' => 'delivery probe', 'body' => $body]);
        } else {
            step('spa', $nick, ['scope' => 'public', 'profile_uid' => $uid,
                'title' => 'delivery probe', 'body' => $body, 'mimetype' => 'text/bbcode']);
        }
        $r = q("SELECT mid FROM item WHERE uid = %d AND body = '%s' LIMIT 1",
            intval($uid), dbesc($body));
        return $r ? $r[0]['mid'] : '';
    };

    $coreMid = $probe('core');
    $spaMid  = $probe('spa');

    if (!$coreMid || !$spaMid) {
        bad('[delivery] a probe was not stored at all');
        return;
    }

    // Delivery is queued (Master::Summon -> QueueWorker), and local recipients
    // are a second-level job, so wait for the queue to drain rather than sleep
    // a fixed amount.
    $deadline = time() + 90;
    while (time() < $deadline) {
        $q = q('SELECT COUNT(*) AS n FROM workerq');
        if (!is_array($q) || intval($q[0]['n'] ?? 0) === 0) { sleep(3); break; }
        sleep(2);
    }

    $spread = function (string $mid) {
        $r = q("SELECT COUNT(DISTINCT uid) AS n FROM item WHERE mid = '%s'", dbesc($mid));
        return is_array($r) ? intval($r[0]['n'] ?? 0) : 0;
    };
    // A local recipient's dreport is addressed by xchan hash; a remote one by
    // URL. Only the local ones prove the local delivery path ran.
    $localReports = function (string $mid) {
        // a local recipient is addressed by xchan hash; remote ones by URL or
        // hostname, both of which contain a dot. The %% are literal for q().
        $r = q("SELECT COUNT(*) AS n FROM dreport
                WHERE dreport_mid = '%s' AND dreport_recip NOT LIKE 'http%%'
                  AND dreport_recip NOT LIKE '%%.%%'", dbesc($mid));
        return is_array($r) ? intval($r[0]['n'] ?? 0) : 0;
    };

    $cN = $spread($coreMid);
    $sN = $spread($spaMid);
    echo "  copies: core=$cN  spa=$sN   local dreports: core=" . $localReports($coreMid)
       . "  spa=" . $localReports($spaMid) . "\n";

    $cN > 1
        ? ok("[delivery] core reached $cN channels (the baseline)")
        : bad("[delivery] core itself delivered nowhere — the probe setup is wrong, not the SPA",
              "      this channel may have no reachable local recipient\n");

    if ($cN > 1) {
        $sN === $cN
            ? ok("[delivery] spa reached the same $sN channels as core")
            : bad("[delivery] spa reached $sN channel(s), core reached $cN",
                  "      a public post the SPA stores identically to core is not being delivered\n");

        $localReports($spaMid) > 0
            ? ok('[delivery] spa produced local delivery reports')
            : bad('[delivery] spa produced no local delivery report',
                  "      the local recipients were never handed the packet at all\n");
    }

    // Clean up every copy and its reports, across all uids.
    foreach ([$coreMid, $spaMid] as $mid) {
        q("DELETE FROM dreport WHERE dreport_mid = '%s'", dbesc($mid));
        q("DELETE FROM item WHERE mid = '%s' OR parent_mid = '%s'", dbesc($mid), dbesc($mid));
    }
    q("DELETE FROM item WHERE uid = %d AND created >= '%s'", intval($uid), dbesc($startedAt));
}

foreach (['default', 'strict'] as $pass) {
    if ($pass === 'strict') {
        // PERMS_NETWORK / PERMS_AUTHED: both map to values item_store never
        // substitutes, so an omitted policy column cannot hide.
        \Zotlabs\Access\PermissionLimits::Set($uid, 'view_stream', PERMS_NETWORK);
        \Zotlabs\Access\PermissionLimits::Set($uid, 'post_comments', PERMS_AUTHED);
    }
    $vs = \Zotlabs\Access\PermissionLimits::Get($uid, 'view_stream');
    $pc = \Zotlabs\Access\PermissionLimits::Get($uid, 'post_comments');
    echo "\n=========================================================\n";
    echo "pass: $pass   view_stream=" . (map_scope($vs, true) ?: '(public)')
       . "  post_comments=" . map_scope($pc) . "\n";
    echo "=========================================================\n";
    runCases($nick, $pass);
    restoreLimits();
}

deliveryCase();

// ---------------------------------------------------------------------------
// Cleanup — raw DELETE, not drop_item: a tombstone would federate.
// ---------------------------------------------------------------------------
if (getenv('PARITY_KEEP')) {
    echo "\nPARITY_KEEP set — probe rows left in place: " . implode(',', array_filter($probes)) . "\n";
} else {
    foreach (array_filter($probes) as $id) {
        q("DELETE FROM item WHERE id = %d OR parent = %d", intval($id), intval($id));
    }
    // Reaction and boost rows carry core's own wording, not our probe tag, so
    // sweep everything this channel created during the run.
    q("DELETE FROM item WHERE uid = %d AND created >= '%s'", intval($uid), dbesc($startedAt));
    // The event cases store an `event` row per probe as well, and deleting the
    // item does not take it with it — 88 of these had piled up before this line
    // existed.
    q("DELETE FROM event WHERE uid = %d AND created >= '%s'", intval($uid), dbesc($startedAt));
    echo "\nprobes removed, limits restored\n";
}

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall parity checks passed\n";
exit($fail ? 1 : 0);
