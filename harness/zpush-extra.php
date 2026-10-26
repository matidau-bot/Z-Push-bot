<?php
// Focused checks for the php-imap2 issues that matter to Z-Push. Each check does
// the operation, then reads the server state back with calls that work in both
// modes (imap_list, imap_getmailboxes, imap_search), so a wrong return value
// cannot hide a wrong result.
// Usage: php zpush-extra.php native | lib <php-imap2/src> | wrapper <php-imap2.php>
require __DIR__ . '/load.php';

$s = '{127.0.0.1:10143/imap/notls/norsh}';
$errs = [];
set_error_handler(function ($n, $m) use (&$errs) { $errs[] = preg_replace('/^imap_\w+\(\): /', '', $m); return true; });
$r = [];
function t(string $name, callable $fn) {
    global $r, $errs;
    $errs = [];
    try { $v = $fn(); } catch (\Throwable $e) { $v = 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage(); }
    $r[$name] = $errs ? ['result' => $v, 'warnings' => $errs] : $v;
}
$m = imap_open($s . 'INBOX', 'test', 'secret');
$folders = fn() => array_values(array_map(fn($x) => substr($x, strlen($s)), (array) imap_list($m, $s, '*')));
$has = fn(string $f) => in_array($f, $folders(), true);
// Subscribed folders, read with a raw IMAP LSUB through imap_getsubscribed when present.
$subscribed = function () use ($m, $s) {
    if (!function_exists('imap_getsubscribed')) return 'n/a';
    $l = imap_getsubscribed($m, $s, '*');
    return is_array($l) ? array_values(array_map(fn($o) => substr($o->name, strlen($s)), $l)) : $l;
};
$isSub = fn(string $f) => is_array($x = $subscribed()) ? in_array($f, $x, true) : $x;

// 1. imap_fetch_overview: the calls Z-Push makes (GetMessageList, StatMessage, MoveMessage)
t('overview: GetMessageList 1:*', function () use ($m) { $o = imap_fetch_overview($m, '1:*'); return is_array($o) ? count($o) . ' messages' : $o; });
t('overview: StatMessage uid 2', function () use ($m) { $o = imap_fetch_overview($m, '2', FT_UID); return is_array($o) ? ['uid' => $o[0]->uid, 'seen' => $o[0]->seen, 'flagged' => $o[0]->flagged, 'udate' => $o[0]->udate] : $o; });
t('overview: message without To (Drafts)', function () use ($m, $s) {
    imap_append($m, $s . 'Drafts', "From: test@example.com\r\nSubject: No To\r\n\r\nx\r\n", "\\Seen \\Draft");
    imap_reopen($m, $s . 'Drafts');
    try { $o = imap_fetch_overview($m, '1:*'); } finally { imap_reopen($m, $s . 'INBOX'); }
    return is_array($o) ? count($o) . ' messages' : $o;
});

// 2. MoveMessage's flag clear before the move (imap.php ~1901). uid 4 starts \Answered \Seen.
t('clearflag multi: before', fn() => ['seen' => imap_search($m, 'SEEN', SE_UID), 'answered' => imap_search($m, 'ANSWERED', SE_UID)]);
t('clearflag multi: call', fn() => imap_clearflag_full($m, '4', "\\Seen \\Answered \\Flagged \\Deleted \\Draft", ST_UID));
t('clearflag multi: after', fn() => ['seen' => imap_search($m, 'SEEN', SE_UID), 'answered' => imap_search($m, 'ANSWERED', SE_UID)]);
t('setflag multi: call', fn() => imap_setflag_full($m, '5', "\\Seen \\Flagged", ST_UID));
t('setflag multi: after', fn() => ['seen' => imap_search($m, 'SEEN', SE_UID), 'flagged' => imap_search($m, 'FLAGGED', SE_UID)]);

// 3. Search dates as Z-Push's GetSearchRestriction builds them (imap.php ~2284-2321)
foreach (['SINCE 06-Oct-2026', 'SINCE "2026-10-06"', 'SINCE "6 Oct 2026"', 'BEFORE "2026-10-04"', 'SINCE "6 Oct 2026" BEFORE "2026-10-08"', 'SINCE "2026-10-06" TEXT "Plain"', 'TEXT "café"'] as $q) {
    t("search: $q", fn() => imap_search($m, $q, SE_UID, 'UTF-8'));
}

// 4. Subscribe / unsubscribe, prefixed (as Z-Push calls it) and bare
t('subscribe: create SubA', fn() => imap_createmailbox($m, $s . 'SubA'));
t('subscribe: {server}SubA', fn() => imap_subscribe($m, $s . 'SubA'));
t('subscribe: SubA exists after', fn() => $has('SubA'));
t('subscribe: SubA subscribed after', fn() => $isSub('SubA'));
t('subscribe: create SubB', fn() => imap_createmailbox($m, $s . 'SubB'));
t('subscribe: bare SubB', fn() => imap_subscribe($m, 'SubB'));
t('subscribe: SubB exists after', fn() => $has('SubB'));
if (function_exists('imap_unsubscribe')) {
    t('unsubscribe: {server}SubA', fn() => imap_unsubscribe($m, $s . 'SubA'));
    t('unsubscribe: SubA exists after', fn() => $has('SubA'));
    t('unsubscribe: SubA subscribed after', fn() => $isSub('SubA'));
}

// 5. UTF-7 folder names, the way Z-Push passes them (already UTF7-IMAP encoded, with the server prefix)
$u = 'Archive/' . mb_convert_encoding('Réunions 2026 – Ünïcödé', 'UTF7-IMAP', 'UTF-8');
t('utf7: name sent', fn() => $u);
t('utf7: create', fn() => imap_createmailbox($m, $s . $u));
t('utf7: listed as sent', fn() => $has($u));
t('utf7: getmailboxes name', function () use ($m, $s, $u) { $l = imap_getmailboxes($m, $s, $u); return is_array($l) ? array_map(fn($o) => substr($o->name, strlen($s)), $l) : $l; });
t('utf7: status', function () use ($m, $s, $u) { $x = imap_status($m, $s . $u, SA_ALL); return is_object($x) ? ['messages' => $x->messages] : $x; });
t('utf7: move uid 1 in', fn() => imap_mail_move($m, '1', $u, CP_UID) && imap_expunge($m));
t('utf7: append', fn() => imap_append($m, $s . $u, "From: a@example.com\r\nSubject: hi\r\n\r\nx\r\n", "\\Seen"));
t('utf7: reopen and count', function () use ($m, $s, $u) { $ok = imap_reopen($m, $s . $u); $n = imap_num_msg($m); imap_reopen($m, $s . 'INBOX'); return [$ok, $n]; });
t('utf7: subscribe', fn() => imap_subscribe($m, $s . $u));
t('utf7: delete', fn() => imap_deletemailbox($m, $s . $u));
t('utf7: gone after delete', fn() => !$has($u));
t('utf7: existing folders still listed', fn() => array_values(array_filter($folders(), fn($f) => preg_match('/&/', $f))));

t('errors', fn() => imap_errors());
imap_close($m);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
