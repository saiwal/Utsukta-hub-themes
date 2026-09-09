<?php
/**
 * Photos.php batch move — a moved photo must follow its album in BOTH tables.
 *
 * attach_move() rewrites attach.folder and, for is_photo rows, every photo row's
 * album/os_path/content. Get the second half wrong (or hand attach_move a hash
 * that isn't a photo) and the grid keeps listing the photo in its old album
 * while the file lives somewhere else.
 *
 * Creates two albums and a real JPEG, moves it A → B, asserts both tables
 * agree, then deletes everything it made.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Photos.test.php <nick>
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once 'include/attach.php';
require_once 'include/photos.php';

$nick = $argv[1] ?? '';
if ($nick === 'step') $nick = $argv[2];
$ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
if (!$ch) { fwrite(STDERR, "usage: Photos.test.php <channel nick>\n"); exit(2); }
$ch  = $ch[0];
$uid = intval($ch['channel_id']);

// attach_store()/attach_mkdir() run perm_is_allowed against the observer.
$_SESSION['uid'] = $uid;
$_SESSION['authenticated'] = 1;
\App::$channel  = $ch;
\App::$observer = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch['channel_hash']))[0];

// ── Step mode: one batchMovePhotos() call. Response::send() exits, so the
//    handler gets its own process.  php Photos.test.php step <nick> <rid> <folder>
if (($argv[1] ?? '') === 'step') {
    $m = new ReflectionMethod(\Utsukta\SpaCore\Api\Handlers\Photos::class, 'batchMovePhotos');
    $m->setAccessible(true);
    $m->invoke(new \Utsukta\SpaCore\Api\Handlers\Photos, $uid, $ch, [$argv[3]], $argv[4]);
    exit;
}

// ── Driver ──────────────────────────────────────────────────────────────────

$A = 'spa-move-test-A';
$B = 'spa-move-test-B';

// Leftovers from an aborted earlier run would fail as "duplicate filename".
q("DELETE FROM attach WHERE uid = %d AND filename LIKE 'spa-move-test%%'", $uid);

$r = attach_mkdir($ch, $ch['channel_hash'], ['filename' => $B, 'folder' => '']);
if (empty($r['success'])) { fwrite(STDERR, "mkdir $B failed: " . ($r['message'] ?? '?') . "\n"); exit(1); }
$hashB = $r['data']['hash'];

// Upload the way the handler does — attach_store() is what creates the attach
// row (photo_upload() alone only writes the photo table).
$im = imagecreatetruecolor(8, 8);
$tmp = tempnam(sys_get_temp_dir(), 'spamove') . '.jpg';
imagejpeg($im, $tmp);
imagedestroy($im);

$rid = photo_new_resource();
$_FILES['userfile'] = [
    'name' => 'spa-move-test.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp,
    'error' => 0, 'size' => filesize($tmp),
];
$res = attach_store($ch, $ch['channel_hash'], '', ['album' => $A, 'hash' => $rid, 'nosync' => true, 'source' => 'photos']);
@unlink($tmp);
if (empty($res['success']) || !intval($res['data']['is_photo'] ?? 0)) {
    fwrite(STDERR, "upload failed: " . ($res['message'] ?? '?') . "\n"); exit(1);
}
$hashA = $res['data']['folder'];

exec(sprintf('php %s step %s %s %s', escapeshellarg(__FILE__), escapeshellarg($nick),
    escapeshellarg($rid), escapeshellarg($hashB)), $out);

$att = q("SELECT folder FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1", $uid, dbesc($rid));
$ph  = q("SELECT album, content FROM photo WHERE uid = %d AND resource_id = '%s'", $uid, dbesc($rid));

$fails = [];
if (!$ph)                                    $fails[] = 'no photo rows at all';
if (strpos(implode('', $out), '"moved"') === false) $fails[] = 'handler did not report a move: ' . implode('', $out);
if (($att[0]['folder'] ?? '') !== $hashB)    $fails[] = "attach.folder = '" . ($att[0]['folder'] ?? '') . "', want '$hashB'";
foreach ($ph as $p) {
    if ($p['album'] !== $B)                  $fails[] = "photo.album = '{$p['album']}', want '$B'";
    if (strpos(dbunescbin($p['content']), $hashB) === false) $fails[] = "photo.content still points outside '$B'";
}

attach_delete($uid, $rid, true);
q("DELETE FROM attach WHERE uid = %d AND hash IN ('%s', '%s')", $uid, dbesc($hashA), dbesc($hashB));

if ($fails) { fwrite(STDERR, "FAIL\n  " . implode("\n  ", $fails) . "\n"); exit(1); }
echo "ok — photo moved, attach.folder and photo.album agree\n";
