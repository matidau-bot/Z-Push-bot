<?php require 'vendor/autoload.php';
$c = new Horde_Imap_Client_Socket(['username'=>'test','password'=>'secret','hostspec'=>'127.0.0.1','port'=>10143,'secure'=>false]);
print_r(array_keys($c->listMailboxes('*')));
$q = new Horde_Imap_Client_Fetch_Query(); $q->envelope(); $q->flags(); $q->imapDate(); $q->size();
foreach ($c->fetch('INBOX', $q) as $uid => $d) echo $uid, ' ', $d->getEnvelope()->subject, ' | ', implode(',', $d->getFlags()), "\n";
$s = new Horde_Imap_Client_Search_Query(); $s->dateSearch(new DateTime('2026-10-06'), Horde_Imap_Client_Search_Query::DATE_SINCE);
print_r($c->search('INBOX', $s)['match']->ids);
