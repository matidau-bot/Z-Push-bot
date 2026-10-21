<?php
// Runs the imap_* calls Z-Push makes and prints normalised results as JSON.
// Usage: php harness.php native|lib <path-to-php-imap2/src>
$mode = $argv[1] ?? 'native';
if ($mode === 'lib') {
    if (extension_loaded('imap')) { fwrite(STDERR, "ext-imap is loaded; lib mode needs it off\n"); exit(1); }
    $src = $argv[2];
    spl_autoload_register(function ($c) use ($src) {
        foreach (['PHPFUI\\Imap2\\' => "$src/Imap2/", 'IMAP\\' => "$src/IMAP/"] as $p => $d) {
            if (strncmp($c, $p, strlen($p)) === 0) {
                $f = $d . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
                if (is_file($f)) { require $f; return; }
            }
        }
    });
    require "$src/Imap2/IMAPStubs.php";
}
elseif (!extension_loaded('imap')) { fwrite(STDERR, "native mode needs ext-imap\n"); exit(1); }

$server = '{127.0.0.1:10143/imap/notls/norsh}';
$out = [];
$warnings = [];
set_error_handler(function ($no, $str) use (&$warnings) { $warnings[] = preg_replace('/^imap_\w+\(\): /', '', $str); return true; });

function norm($v) {
    if (is_object($v)) $v = get_object_vars($v);
    if (is_array($v)) { $r = []; foreach ($v as $k => $x) $r[$k] = norm($x); if (array_keys($r) !== range(0, count($r) - 1)) ksort($r); return $r; }
    if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) return 'bin:' . bin2hex($v);
    return $v;
}
function t($name, callable $fn) {
    global $out, $warnings;
    $warnings = [];
    try { $r = $fn(); $res = ['result' => norm($r)]; }
    catch (\Throwable $e) { $res = ['exception' => get_class($e) . ': ' . $e->getMessage()]; }
    if ($warnings) $res['warnings'] = $warnings;
    $out[$name] = $res;
}
$m = null;
$uidOf = [];
function state() { global $m; $r = []; foreach (['ALL','DELETED','SEEN','FLAGGED','ANSWERED','DRAFT','KEYWORD $Forwarded'] as $q) $r[$q] = imap_search($m, $q, SE_UID); return $r; }

t('function_exists', function () {
    $r = [];
    foreach (['imap_open','imap_reopen','imap_getmailboxes','imap_list','imap_status','imap_check','imap_num_msg','imap_search','imap_fetch_overview','imap_fetchheader','imap_body','imap_fetchstructure','imap_ping','imap_errors','imap_last_error','imap_setflag_full','imap_clearflag_full','imap_append','imap_mail_move','imap_delete','imap_expunge','imap_createmailbox','imap_renamemailbox','imap_deletemailbox','imap_subscribe','imap_close','imap_utf7_encode','imap_mime_header_decode','imap_8bit','imap_rfc822_parse_adrlist'] as $f) $r[$f] = function_exists($f);
    return $r;
});
t('open_halfopen', function () use ($server, &$m) { $m = imap_open($server, 'test', 'secret', OP_HALFOPEN, 0); return $m !== false; });
t('getmailboxes_all', function () use ($server, &$m) {
    $r = imap_getmailboxes($m, $server, '*');
    if (is_array($r)) usort($r, fn($a, $b) => strcmp($a->name, $b->name));
    return $r;
});
t('getmailboxes_one', fn() => imap_getmailboxes($m, $server, 'Archive'));
t('getmailboxes_prefixed', fn() => imap_getmailboxes($m, $server . 'Archive', '*'));
t('list_all', function () use ($server, &$m) { $r = imap_list($m, $server, '*'); if (is_array($r)) sort($r); return $r; });
t('list_one', fn() => imap_list($m, $server, 'Drafts'));
$folders = ['INBOX', 'Drafts', 'Archive/2024', '&ANw-n&AO8-c&APY-d&AOk-', 'Folder with space', 'Missing'];
foreach ($folders as $f) t("status:$f", fn() => imap_status($m, $server . $f, SA_ALL));
t('reopen_inbox', fn() => imap_reopen($m, $server . 'INBOX'));
t('check', function () use (&$m) { $r = imap_check($m); if (is_object($r)) unset($r->Date); return $r; });
t('num_msg', fn() => imap_num_msg($m));
t('search_all_uid', fn() => imap_search($m, 'ALL', SE_UID, 'UTF-8'));
foreach (imap_search($m, 'ALL', SE_UID) ?: [] as $i => $u) $uidOf[$i] = $u;
foreach (['SINCE 06-Oct-2026', 'SINCE "06-Oct-2026"', 'SINCE "06 Oct 2026" BEFORE "09 Oct 2026"', 'UNDELETED', 'ANSWERED', 'FLAGGED', 'UNSEEN', 'KEYWORD $Forwarded', 'SUBJECT "café"', 'SUBJECT "nothing-matches"'] as $q) {
    t("search_uid:$q", fn() => imap_search($m, $q, SE_UID, 'UTF-8'));
}
t('search_seq:ANSWERED', fn() => imap_search($m, 'ANSWERED'));
t('search_seq:KEYWORD $Forwarded', fn() => imap_search($m, 'KEYWORD $Forwarded'));
t('search_uid_nocharset:KEYWORD $Forwarded', fn() => imap_search($m, 'KEYWORD $Forwarded', SE_UID));
t('fetch_overview_seq', fn() => imap_fetch_overview($m, '1:*'));
foreach ($uidOf as $i => $u) {
    t("overview_uid:$u", fn() => imap_fetch_overview($m, (string) $u, FT_UID));
    t("fetchheader_uid:$u", fn() => imap_fetchheader($m, $u, FT_UID));
    t("body_peek_uid:$u", fn() => imap_body($m, $u, FT_PEEK | FT_UID));
    t("fetchstructure_uid:$u", fn() => imap_fetchstructure($m, $u, FT_UID));
}
t('after_peek_unseen', fn() => imap_search($m, 'UNSEEN', SE_UID));
t('overview_missing_uid', fn() => imap_fetch_overview($m, '999', FT_UID));
t('overview_missing_uid_last_error', fn() => imap_last_error());
t('reopen_missing', fn() => imap_reopen($m, $server . 'Missing'));
t('reopen_missing_last_error', fn() => imap_last_error());
t('reopen_inbox_again', fn() => imap_reopen($m, $server . 'INBOX'));
t('ping', fn() => imap_ping($m));
t('utf7_encode', fn() => imap_utf7_encode('Ünïcödé'));
t('mime_header_decode', fn() => imap_mime_header_decode('=?UTF-8?Q?R=C3=A9union?= plain =?ISO-8859-1?Q?Gr=FC=DFe?='));

// mutations, in the order Z-Push would make them
[$plain, $utf8, $alt, $attach, $nodate] = [$uidOf[0], $uidOf[1], $uidOf[2], $uidOf[3], $uidOf[4]];
t('setflag_seen', fn() => imap_setflag_full($m, (string) $utf8, "\\Seen", ST_UID));
t('state_after:setflag_seen', fn() => state());
t('setflag_forwarded', fn() => imap_setflag_full($m, (string) $plain, "\$Forwarded", ST_UID));
t('state_after:setflag_forwarded', fn() => state());
t('setflag_answered', fn() => imap_setflag_full($m, (string) $plain, "\\Answered", ST_UID));
t('state_after:setflag_answered', fn() => state());
t('clearflag_flagged', fn() => imap_clearflag_full($m, (string) $utf8, "\\Flagged", ST_UID));
t('state_after:clearflag_flagged', fn() => state());
t('clearflag_multi', fn() => imap_clearflag_full($m, (string) $attach, "\\Seen \\Answered \\Flagged \\Deleted \\Draft", FT_UID));
t('state_after:clearflag_multi', fn() => state());
t('flags_after', function () use (&$m, $uidOf) {
    $r = [];
    foreach (imap_fetch_overview($m, implode(',', $uidOf), FT_UID) ?: [] as $o) $r[$o->uid] = ['seen' => $o->seen, 'flagged' => $o->flagged, 'answered' => $o->answered, 'deleted' => $o->deleted, 'draft' => $o->draft];
    $r['forwarded'] = imap_search($m, 'KEYWORD $Forwarded', SE_UID);
    return $r;
});
t('append_sent', fn() => imap_append($m, $server . 'Sent', "From: test@example.com\r\nTo: x@example.com\r\nSubject: Sent copy\r\nDate: Wed, 14 Oct 2026 10:00:00 +0000\r\n\r\nSent body\r\n", "\\Seen"));
t('append_draft', fn() => imap_append($m, $server . 'Drafts', "From: test@example.com\r\nSubject: New draft\r\n\r\nDraft\r\n", "\\Seen \\Draft"));
t('status_sent_after_append', fn() => imap_status($m, $server . 'Sent', SA_ALL));
t('status_drafts_after_append', fn() => imap_status($m, $server . 'Drafts', SA_ALL));
t('drafts_flags_after_append', function () use ($server, &$m) {
    imap_reopen($m, $server . 'Drafts');
    try { $r = imap_fetch_overview($m, '1:*'); } finally { imap_reopen($m, $server . 'INBOX'); }
    return array_map(fn($o) => [$o->subject ?? null, $o->seen, $o->draft], $r ?: []);
});
t('mail_move_to_trash', fn() => imap_mail_move($m, (string) $nodate, 'Trash', CP_UID));
t('state_after:mail_move_to_trash', fn() => state());
t('expunge_after_move', fn() => imap_expunge($m));
t('state_after:expunge_after_move', fn() => state());
t('status_trash_after_move', fn() => imap_status($m, $server . 'Trash', SA_ALL));
t('setflag_deleted', fn() => imap_setflag_full($m, (string) $alt, "\\Deleted", FT_UID));
t('state_after:setflag_deleted', fn() => state());
t('expunge_after_delete', fn() => imap_expunge($m));
t('state_after:expunge_after_delete', fn() => state());
t('delete_seq_then_expunge', function () use (&$m) { $a = imap_delete($m, '1'); $b = imap_expunge($m); return [$a, $b, imap_num_msg($m)]; });
t('state_after:delete_seq_then_expunge', fn() => state());
t('inbox_uids_after', fn() => imap_search($m, 'ALL', SE_UID));
$new = 'Archive/' . mb_convert_encoding('Nouveau dossier é', 'UTF7-IMAP', 'UTF-8');
t('createmailbox', fn() => imap_createmailbox($m, $server . $new));
t('subscribe', fn() => imap_subscribe($m, $server . $new));
t('createmailbox_exists', fn() => imap_createmailbox($m, $server . $new));
t('createmailbox_exists_last_error', fn() => imap_last_error());
t('renamemailbox', fn() => imap_renamemailbox($m, $server . $new, $server . 'Archive/Renamed'));
t('deletemailbox', fn() => imap_deletemailbox($m, $server . 'Archive/Renamed'));
t('deletemailbox_missing', fn() => imap_deletemailbox($m, $server . 'Archive/Renamed'));
t('list_after', function () use ($server, &$m) { $r = imap_list($m, $server, '*'); if (is_array($r)) sort($r); return $r; });
t('errors', fn() => imap_errors());
t('close', fn() => imap_close($m));
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
