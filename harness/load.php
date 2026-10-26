<?php
// Shared loader. Modes:
//   native               ext-imap
//   lib <src>            a php-imap2 src/ directory, via its own stubs
//   wrapper <file>       Z-Push's backend/imap/php-imap2/php-imap2.php, as imap.php loads it
$mode = $argv[1] ?? 'native';
if ($mode === 'native') {
    if (!extension_loaded('imap')) { fwrite(STDERR, "native mode needs ext-imap\n"); exit(1); }
    return;
}
if (extension_loaded('imap')) { fwrite(STDERR, "ext-imap is loaded; $mode mode needs it off\n"); exit(1); }
if ($mode === 'wrapper') {
    require $argv[2];
    if (!function_exists('imap_open')) { fwrite(STDERR, "wrapper did not define imap_open (PHP < 8.3?)\n"); exit(1); }
    return;
}
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
