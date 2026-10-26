<?php
// Replays MoveMessage's flag handling (imap.php ~1901-1914) for every combination of
// source flags. IMAP MOVE keeps the flags, then Z-Push clears all five and sets
// $newflags built from the source overview, in one string.
// Usage: php moveflags.php native | lib <php-imap2/src> | wrapper <php-imap2.php>
require __DIR__ . '/load.php';
$s = '{127.0.0.1:10143/imap/notls/norsh}';
set_error_handler(fn() => true);
$m = imap_open($s . 'Trash', 'test', 'secret');
$get = function ($uid) use ($m) {
    $f = [];
    foreach (['SEEN', 'FLAGGED', 'ANSWERED'] as $k) {
        if (in_array($uid, (array) imap_search($m, $k, SE_UID))) $f[] = $k;
    }
    return $f ? implode(',', $f) : '-';
};
foreach ([[1, 0, 0], [0, 1, 0], [0, 0, 1], [1, 1, 0], [1, 0, 1], [0, 1, 1], [1, 1, 1], [0, 0, 0]] as [$seen, $flagged, $answered]) {
    $src = trim(($seen ? '\\Seen ' : '') . ($flagged ? '\\Flagged ' : '') . ($answered ? '\\Answered' : ''));
    imap_append($m, $s . 'Trash', "From: a@example.com\r\nSubject: t\r\n\r\nx\r\n", $src);
    $uid = max((array) imap_search($m, 'ALL', SE_UID));
    imap_clearflag_full($m, (string) $uid, "\\Seen \\Answered \\Flagged \\Deleted \\Draft", FT_UID);
    $newflags = '';
    if ($seen) $newflags .= "\\Seen";
    if ($flagged) $newflags .= " \\Flagged";
    if ($answered) $newflags .= " \\Answered";
    imap_setflag_full($m, (string) $uid, $newflags, FT_UID);
    $want = array_filter([$seen ? 'SEEN' : '', $flagged ? 'FLAGGED' : '', $answered ? 'ANSWERED' : '']);
    $want = $want ? implode(',', $want) : '-';
    $got = $get($uid);
    printf("%-34s want %-22s got %-22s %s\n", "source \"$src\"", $want, $got, $want === $got ? 'ok' : 'WRONG');
}
