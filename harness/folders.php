<?php
$src = $argv[2] ?? null;
if ($argv[1] === 'lib') { spl_autoload_register(function ($c) use ($src) { foreach (['PHPFUI\\Imap2\\' => "$src/Imap2/", 'IMAP\\' => "$src/IMAP/"] as $p => $d) if (strncmp($c, $p, strlen($p)) === 0) { $f = $d . str_replace('\\', '/', substr($c, strlen($p))) . '.php'; if (is_file($f)) { require $f; return; } } }); require "$src/Imap2/IMAPStubs.php"; }
set_error_handler(fn($n, $s) => true);
$s = '{127.0.0.1:10143/imap/notls/norsh}';
$m = imap_open($s . 'INBOX', 'test', 'secret');
$names = fn() => array_map(fn($x) => substr($x, strlen($s)), imap_list($m, $s, 'T*') ?: []);
$subs = fn() => array_map(fn($x) => substr($x, strlen($s)), (function_exists('imap_lsub') ? imap_lsub($m, $s, 'T*') : array_map(fn($x) => $x->name, imap_getsubscribed($m, $s, 'T*') ?: [])) ?: []);
$r = [];
$r['create TestA'] = imap_createmailbox($m, $s . 'TestA');
$r['subscribe {srv}TestA'] = imap_subscribe($m, $s . 'TestA');
$r['after: list'] = $names(); $r['after: lsub'] = $subs();
$r['create TestB'] = imap_createmailbox($m, $s . 'TestB');
$r['subscribe bare TestB'] = imap_subscribe($m, 'TestB');
$r['after bare: list'] = $names(); $r['after bare: lsub'] = $subs();
$r['create TestC'] = imap_createmailbox($m, $s . 'TestC');
$r['rename {srv}TestC -> {srv}TestD'] = imap_renamemailbox($m, $s . 'TestC', $s . 'TestD');
$r['after rename: list'] = $names();
$r['delete {srv}TestA'] = imap_deletemailbox($m, $s . 'TestA');
$r['final list'] = $names();
echo json_encode($r, JSON_UNESCAPED_SLASHES), "\n";
