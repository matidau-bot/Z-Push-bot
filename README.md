# imap-compat-harness

Checks how closely a pure-PHP IMAP library matches PHP's `imap` extension (ext-imap), using the calls Z-Push's IMAP backend makes. It is written for phpfui/php-imap2: the same script runs once with ext-imap and once with the library's `imap_*` stubs, against the same Dovecot mailbox restored from a snapshot each time, and the results are compared call by call.

## Setup (Ubuntu/Debian, as root)

```sh
apt install dovecot-imapd php8.3-cli php8.3-imap php8.3-mbstring
useradd --system zptest
install -d -o zptest -g zptest /srv/zpmail
mkdir -p /run/zpdc
```

Dovecot runs on 127.0.0.1:10143 with no TLS, user `test`, password `secret` (`dovecot/dovecot.conf`). The mail store is `/srv/zpmail/test`.

## Run

```sh
./run.sh /path/to/php-imap2/src my-branch
```

This writes `results/my-branch/native.json`, `lib.json` and `diff.txt`. The last line of `diff.txt` gives the count of identical results. Each `===` entry is a call whose result differs, with the ext-imap result first.

## Files

| Path | What it is |
|---|---|
| `harness/harness.php` | The comparison script. `php harness.php native` or `php harness.php lib <src>`. Prints JSON to stdout. |
| `harness/diff.php` | Compares two result files. |
| `harness/seed.php` | Builds the test mailbox with ext-imap. Only needed to rebuild `fixture/mailbox.tar`. |
| `harness/folders.php` | Subscribe, rename and delete checks, including bare folder names. |
| `harness/search.php` | The search date formats Z-Push sends, and an overview of a message with no To header. |
| `fixture/mailbox.tar` | Maildir snapshot of `/srv/zpmail/test`. |
| `dovecot/dovecot.conf` | Test server config. |
| `smoke/` | One-off tests of other libraries without ext-imap: Roundcube's `rcube_imap_generic`, Horde Imap_Client, Webklex/php-imap, DirectoryTree/ImapEngine. Each needs its library installed; see the top of each file. |
| `patches/` | The local fixes used to get past php-imap2 0.5.2's `imap_fetch_overview` crashes. |
| `results/2026-10-21/` | First run: `lib` is php-imap2 0.5.2, `libp` is 0.5.2 with the patch, both against php8.3-imap. |

## Test mailbox

The INBOX holds 8 messages with fixed internal dates (1 to 8 Oct 2026):

1. Plain ASCII.
2. UTF-8 Q-encoded subject and B-encoded From.
3. multipart/alternative with a folded subject and several To/Cc.
4. A mixed message with an inline image and a PDF attachment with an RFC 2231 filename.
5. No Date header.
6. latin1 8bit.
7. A forward with a message/rfc822 part and Reply-To.
8. ISO-2022-JP.

Flags include `\Seen`, `\Flagged`, `\Answered` and `$Forwarded`. Other folders: Drafts (one `\Draft` message), Sent, Trash, Archive, Archive/2024 (one message), "Folder with space", and two non-ASCII folders (Ünïcödé, Документы).

## Findings from the first run (php-imap2 0.5.2)

- `imap_fetch_overview` crashes on every message. The causes are typed properties without defaults (`MessageHeader::$charset`, `$to`, `Mime::$default_charset`) and a From address with no display name.
- `imap_clearflag_full` with several flags in one string leaves `\Seen` set.
- Search dates such as `SINCE "2026-10-06"` or `SINCE "6 Oct 2026"` are sent to the server unchanged, so the search fails. ext-imap converts them to IMAP format.
- `imap_subscribe` and `imap_unsubscribe` call `deleteFolder()`. With a bare folder name, subscribe deletes the folder.

Notes: ext-imap prints "SECURITY PROBLEM: insecure server advertised AUTH=PLAIN" because the test server has no TLS. Searching for `DRAFT` is not supported by ext-imap; the "Unknown search criterion" errors in the native run are from the harness, not the library.
