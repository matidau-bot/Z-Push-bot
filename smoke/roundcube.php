<?php $R = $argv[1]; define('RCUBE_CHARSET', 'UTF-8'); if (!function_exists('array_first')) { function array_first(array $a) { foreach ($a as $v) return $v; return null; } function array_last(array $a) { return $a ? $a[array_key_last($a)] : null; } }
foreach (['rcube_utils','rcube_charset','rcube_message_header','rcube_result_index','rcube_result_thread','rcube_imap_generic'] as $f) require "$R/$f.php";
$c = new rcube_imap_generic();
$c->connect('127.0.0.1', 'test', 'secret', ['port' => 10143, 'ssl_mode' => null, 'auth_type' => 'PLAIN']) or die("connect: " . $c->error . "\n");
echo implode(' | ', array_map(fn($n) => rcube_charset::convert($n, 'UTF7-IMAP'), $c->listMailboxes('', '*'))), "\n";
$c->select('INBOX');
foreach ($c->fetchHeaders('INBOX', '1:*', true, false, ['MESSAGE-ID']) as $h) echo $h->uid, ' ', $h->subject, ' | ', implode(',', array_keys($h->flags)), "\n";
echo 'search: ', implode(',', $c->search('INBOX', 'SINCE 06-Oct-2026', true)->get()), "\n";
echo 'kw: ', implode(',', $c->search('INBOX', 'KEYWORD $Forwarded', true)->get()), "\n";
echo 'flag clear multi: ', var_export($c->unflag('INBOX', '4', 'SEEN') && $c->unflag('INBOX', '4', 'ANSWERED'), true), ' unseen now: ', implode(',', $c->search('INBOX', 'UNSEEN', true)->get()), "\n";
echo 'raw header bytes uid1: ', strlen($c->fetchPartHeader('INBOX', 1, true)), ', body bytes: ', strlen($c->handlePartBody('INBOX', 1, true)), "\n";
echo 'subscribe: ', var_export($c->subscribe('Sent'), true), ' errors: ', $c->error, "\n";
