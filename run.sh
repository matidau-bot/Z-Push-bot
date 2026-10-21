#!/bin/sh
# Compare ext-imap with a php-imap2 checkout against the same mailbox.
# Usage: sudo ./run.sh /path/to/php-imap2/src [label]
# Writes results/<label>/{native,lib}.json and diff.txt.
set -eu
HERE=$(cd "$(dirname "$0")" && pwd)
LIB=${1:?path to php-imap2 src/}
LABEL=${2:-$(date +%Y-%m-%d)}
OUT=$HERE/results/$LABEL
MAIL=/srv/zpmail
mkdir -p "$OUT"

# PHP ini directory without ext-imap, for the library run
NOIMAP=$(mktemp -d)
INIDIR=$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')
cp "$INIDIR"/*.ini "$NOIMAP"/
rm -f "$NOIMAP"/*imap*

pgrep -x dovecot >/dev/null || dovecot -c "$HERE/dovecot/dovecot.conf"

reset_mailbox() {
	rm -rf "$MAIL/test"
	tar -C "$MAIL" -xf "$HERE/fixture/mailbox.tar"
}

reset_mailbox
php "$HERE/harness/harness.php" native > "$OUT/native.json"
reset_mailbox
PHP_INI_SCAN_DIR=$NOIMAP php "$HERE/harness/harness.php" lib "$LIB" > "$OUT/lib.json"
reset_mailbox
php "$HERE/harness/diff.php" "$OUT/native.json" "$OUT/lib.json" > "$OUT/diff.txt"
rm -rf "$NOIMAP"
tail -1 "$OUT/diff.txt"
grep '^===' "$OUT/diff.txt" || true
