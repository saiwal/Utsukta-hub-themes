<?php
/**
 * Self-check for Handlers/Invite: expiry maths and email-template discovery.
 *
 * calcdue() turns "2d" into the reg_expires timestamp an invite is redeemable
 * until — get it wrong and every invite is born dead or immortal. Template
 * discovery is the other half: core's own glob matches nothing (it looks in
 * view/<lang>/ while the files live in view/lang/<lang>/), so this asserts the
 * fixed one actually finds en/casual and renders a non-empty subject and body.
 *
 * Reads only. Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Invite.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use Utsukta\SpaCore\Api\Handlers\Invite;

$fail = 0;

function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

// ── calcdue ──────────────────────────────────────────────────────────────────

$now = time();

$d = Invite::calcdue('2d');
check('2d parts',   [$d['durn'], $d['durq']], ['2', 'd']);
check('2d due',     $d['due'], date('Y-m-d H:i:s', strtotime('+2 days', $now)));

$h = Invite::calcdue('99h');
check('99h parts',  [$h['durn'], $h['durq']], ['99', 'h']);
check('99h due',    $h['due'], date('Y-m-d H:i:s', strtotime('+99 hours', $now)));

$i = Invite::calcdue('30i');
check('30i unit is minutes', $i['due'], date('Y-m-d H:i:s', strtotime('+30 minutes', $now)));

// Anything the form can't produce must be refused outright rather than
// silently becoming "now" — a due date in the past is an unusable invite.
check('garbage refused',   Invite::calcdue('garbage'), false);
check('no unit refused',   Invite::calcdue('2'),       false);
check('3 digits refused',  Invite::calcdue('100d'),    false);

// ── Template discovery + rendering ───────────────────────────────────────────

$probe = new ReflectionClass(Invite::class);
$inv   = $probe->newInstanceWithoutConstructor();

$discover = $probe->getMethod('discoverTemplates');
$discover->setAccessible(true);
$found = $discover->invoke($inv);

check('en discovered',        isset($found['en']),                       true);
check('en has casual',        in_array('casual', $found['en'] ?? [], true), true);
check('subjects not styles',  in_array('casual.subject', $found['en'] ?? [], true), false);
// Every locale the SPA has a UI for must have a template, and every template
// must come in both styles — a ragged matrix is how a locale silently loses an
// option in the picker.
foreach (['en', 'de', 'hi'] as $lang)
    check("locale $lang offered", $found[$lang] ?? [], ['casual', 'formal']);

$renderBody = $probe->getMethod('renderBody');
$renderBody->setAccessible(true);
$body = $renderBody->invoke($inv, 'en', 'casual', 'TESTCODE1234', 'nick', 'nick@example.com');

check('body carries the code',  str_contains($body, 'TESTCODE1234'), true);
check('body macros expanded',   str_contains($body, '{{$'),          false);

// Hindi is the locale core has no invite template for at all, so it is the one
// that proves the templates are read from this package's own view/lang. If that
// plumbing breaks, the locale silently vanishes from the picker instead of
// erroring, so assert it explicitly.
$hiBody = $renderBody->invoke($inv, 'hi', 'casual', 'TESTCODE1234', 'nick', 'nick@example.com');
check('hi body is the package copy', str_contains($hiBody, 'आमंत्रण कोड'), true);
check('hi body macros expanded',   str_contains($hiBody, '{{$'),        false);

$renderSubject = $probe->getMethod('renderSubject');
$renderSubject->setAccessible(true);
$subject = $renderSubject->invoke($inv, 'en', 'casual');

check('subject non-empty',      $subject !== '',            true);
check('hi subject is Hindi',    str_contains($renderSubject->invoke($inv, 'hi', 'casual'), 'जुड़िए'), true);
check('subject single line',    str_contains($subject, "\n"), false);

echo $fail ? "\n$fail check(s) failed\n" : "\nall checks passed\n";
exit($fail ? 1 : 0);
