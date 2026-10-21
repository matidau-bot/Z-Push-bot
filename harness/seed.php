<?php
// Seed the test mailbox using native ext-imap. Run once, then snapshot the maildir.
$server = '{127.0.0.1:10143/imap/notls}';
$m = imap_open($server . 'INBOX', 'test', 'secret') or die(imap_last_error() . "\n");

$folders = ['Drafts', 'Sent', 'Trash', 'Archive', 'Archive/2024', 'Folder with space',
            mb_convert_encoding('Ünïcödé', 'UTF7-IMAP', 'UTF-8'), mb_convert_encoding('Документы', 'UTF7-IMAP', 'UTF-8')];
foreach ($folders as $f) {
    imap_createmailbox($m, $server . $f) or print("create $f: " . imap_last_error() . "\n");
    imap_subscribe($m, $server . $f);
}
imap_subscribe($m, $server . 'INBOX');

$b64 = chunk_split(base64_encode(str_repeat("%PDF-1.4 test attachment\n", 40)));
$msgs = [
'plain' => "From: Alice Example <alice@example.com>\r\nTo: test@example.com\r\nSubject: Plain ASCII message\r\nDate: Mon, 05 Oct 2026 09:15:00 +1100\r\nMessage-ID: <plain@example.com>\r\nContent-Type: text/plain; charset=us-ascii\r\n\r\nHello,\r\nThis is a plain message.\r\n",
'utf8' => "From: =?UTF-8?B?w4lsb2RpZSBNw7xsbGVy?= <elodie@example.com>\r\nTo: test@example.com\r\nSubject: =?UTF-8?Q?R=C3=A9union_=E2=80=93_caf=C3=A9_=F0=9F=98=80?=\r\nDate: Tue, 06 Oct 2026 10:00:00 +0200\r\nMessage-ID: <utf8@example.com>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nBonjour, r=C3=A9union au caf=C3=A9 =E2=80=93 =F0=9F=98=80\r\n",
'alt' => "From: Bob <bob@example.com>\r\nTo: test@example.com, Carol <carol@example.com>\r\nCc: dave@example.com\r\nSubject: Alternative with a very long subject line that is folded across\r\n two header lines to test unfolding\r\nDate: Wed, 07 Oct 2026 11:30:00 +0000\r\nMessage-ID: <alt@example.com>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"ALT\"\r\n\r\n--ALT\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nPlain part\r\n--ALT\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>HTML <b>part</b></p>\r\n--ALT--\r\n",
'attach' => "From: Eve <eve@example.com>\r\nTo: test@example.com\r\nSubject: With attachment and inline image\r\nDate: Thu, 08 Oct 2026 12:00:00 +0000\r\nMessage-ID: <attach@example.com>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"MIX\"\r\n\r\n--MIX\r\nContent-Type: multipart/related; boundary=\"REL\"\r\n\r\n--REL\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>See <img src=\"cid:img1\"></p>\r\n--REL\r\nContent-Type: image/png; name=\"dot.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <img1>\r\nContent-Disposition: inline; filename=\"dot.png\"\r\n\r\niVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==\r\n--REL--\r\n--MIX\r\nContent-Type: application/pdf; name=\"report.pdf\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf\r\n\r\n$b64--MIX--\r\n",
'nodate' => "From: camera@example.com\r\nTo: test@example.com\r\nSubject: Alarm without Date header\r\nMessage-ID: <nodate@example.com>\r\n\r\nMotion detected.\r\n",
'latin1' => "From: =?ISO-8859-1?Q?J=F6rg?= <joerg@example.com>\r\nTo: test@example.com\r\nSubject: =?ISO-8859-1?Q?Gr=FC=DFe?=\r\nDate: Fri, 09 Oct 2026 08:00:00 +0200\r\nMessage-ID: <latin1@example.com>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=iso-8859-1\r\nContent-Transfer-Encoding: 8bit\r\n\r\nGr\xFC\xDFe aus M\xFCnchen\r\n",
'fwd' => "From: Frank <frank@example.com>\r\nTo: test@example.com\r\nReply-To: frank-replies@example.com\r\nSubject: Fwd: Plain ASCII message\r\nDate: Sat, 10 Oct 2026 09:00:00 +0000\r\nMessage-ID: <fwd@example.com>\r\nIn-Reply-To: <plain@example.com>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"FWD\"\r\n\r\n--FWD\r\nContent-Type: text/plain\r\n\r\nForwarding.\r\n--FWD\r\nContent-Type: message/rfc822\r\n\r\nFrom: Alice <alice@example.com>\r\nSubject: Inner\r\n\r\nInner body\r\n--FWD--\r\n",
'jp' => "From: =?ISO-2022-JP?B?GyRCOzNFRBsoQg==?= <yamada@example.com>\r\nTo: test@example.com\r\nSubject: =?ISO-2022-JP?B?GyRCJDMkcyRLJEEkTxsoQg==?=\r\nDate: Sun, 11 Oct 2026 07:00:00 +0900\r\nMessage-ID: <jp@example.com>\r\nContent-Type: text/plain; charset=ISO-2022-JP\r\n\r\ntest\r\n",
];
$day = 0;
foreach ($msgs as $name => $raw) {
    $day++;
    $flags = ['plain' => '\\Seen', 'alt' => '\\Seen $Forwarded', 'utf8' => '\\Flagged', 'attach' => '\\Answered \\Seen'][$name] ?? null;
    imap_append($m, $server . 'INBOX', $raw, $flags, sprintf('%02d-Oct-2026 10:00:00 +0000', $day)) or print("append $name: " . imap_last_error() . "\n");
}
imap_append($m, $server . 'Drafts', "From: test@example.com\r\nTo: someone@example.com\r\nSubject: Draft message\r\nDate: Mon, 12 Oct 2026 10:00:00 +0000\r\n\r\nDraft body\r\n", '\\Seen \\Draft', '12-Oct-2026 10:00:00 +0000');
imap_append($m, $server . 'Archive/2024', "From: old@example.com\r\nTo: test@example.com\r\nSubject: Archived\r\nDate: Mon, 01 Jan 2024 10:00:00 +0000\r\n\r\nOld\r\n", '\\Seen', '01-Jan-2024 10:00:00 +0000');
imap_append($m, $server . mb_convert_encoding('Ünïcödé', 'UTF7-IMAP', 'UTF-8'), "From: u@example.com\r\nSubject: In unicode folder\r\nDate: Tue, 13 Oct 2026 10:00:00 +0000\r\n\r\nx\r\n", null, '13-Oct-2026 10:00:00 +0000');
echo "INBOX: ", imap_num_msg($m), " messages\n";
imap_close($m);
