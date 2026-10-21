<?php
$src = $argv[2] ?? null;
if ($argv[1] === 'lib') { spl_autoload_register(function ($c) use ($src) { foreach (['PHPFUI\\Imap2\\' => "$src/Imap2/", 'IMAP\\' => "$src/IMAP/"] as $p => $d) if (strncmp($c, $p, strlen($p)) === 0) { $f = $d . str_replace('\\', '/', substr($c, strlen($p))) . '.php'; if (is_file($f)) { require $f; return; } } }); require "$src/Imap2/IMAPStubs.php"; }
set_error_handler(fn($n, $s) => true);
$s = '{127.0.0.1:10143/imap/notls/norsh}';
$m = imap_open($s . 'INBOX', 'test', 'secret');
foreach (['SINCE 06-Oct-2026', 'SINCE "2026-10-06"', 'SINCE "6 Oct 2026"', 'SINCE "06-Oct-2026"', 'SINCE "6 Oct 2026" BEFORE "2026-10-09"', ' SINCE "6 Oct 2026" TEXT "café"', ' TEXT "Motion"'] as $q) $r[$q] = imap_search($m, $q, SE_UID, 'UTF-8');
// overview of a message with no To header
imap_reopen($m, $s . 'Drafts'); imap_append($m, $s . 'Drafts', "From: test@example.com\r\nSubject: No To\r\n\r\nx\r\n", "\\Seen \\Draft");
try { $o = imap_fetch_overview($m, '1:*'); $r['drafts overview'] = array_map(fn($x) => [$x->subject, $x->draft], $o ?: []); } catch (\Throwable $e) { $r['drafts overview'] = $e->getMessage(); }
echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), "\n";
