<?php
/**
 * Pure, no database, no Hubzilla: php ContentTypes.test.php
 *
 * gfmToBbcode() is regex over user text, and the collisions are the point —
 * ~sub~ lives inside ~~strike~~'s delimiter, and neither may fire inside a
 * code span. Mirrors src/shared/editor/core/markedExtended.test.ts, which
 * pins the same dialect in the composer.
 */

require_once __DIR__ . '/ContentTypes.php';

$gfm = new ReflectionMethod(\Utsukta\SpaCore\Api\ContentTypes::class, 'gfmToBbcode');
$gfm->setAccessible(true);

$cases = [
    // Extended inline marks MarkdownExtra has no idea about.
    '==lit=='       => '[mark]lit[/mark]',
    'H~2~O'         => 'H[sub]2[/sub]O',
    'X^2^'          => 'X[sup]2[/sup]',

    // Strikethrough is consumed first, so a surviving single ~ is subscript.
    '~~gone~~'      => '[s]gone[/s]',
    '~~a~~ and ~b~' => '[s]a[/s] and [sub]b[/sub]',

    // Running prose keeps its punctuation.
    'a ~ b ~ c'     => 'a ~ b ~ c',
    '2 ^ 3 = 8'     => '2 ^ 3 = 8',
    'a == b'        => 'a == b',

    // Footnote syntax is MarkdownExtra's job, so gfmToBbcode has to leave it
    // intact — including two references side by side, whose carets would
    // otherwise pair up into a superscript.
    'see [^1] and [^2]' => 'see [^1] and [^2]',
    '[^a][^b]'          => '[^a][^b]',
    "[^1]: the note"    => "[^1]: the note",

    // Code spans are literal.
    '`x~2~`'        => '`x~2~`',

    // Task lists still collapse into one [checklist].
    "- [ ] a\n- [x] b" => "[checklist]\n[] a\n[x] b\n[/checklist]\n",
];

$failed = 0;
foreach ($cases as $in => $want) {
    $got = $gfm->invoke(null, $in);
    if ($got !== $want) {
        $failed++;
        printf("FAIL %s\n  want %s\n  got  %s\n", var_export($in, true), var_export($want, true), var_export($got, true));
    }
}

if ($failed) {
    exit(1);
}
echo "ContentTypes: ok (" . count($cases) . " cases)\n";
