<?php require 'vendor/autoload.php';
use Webklex\PHPIMAP\ClientManager;
$cm = new ClientManager(['options' => ['fetch_order' => 'asc']]);
$c = $cm->make(['host'=>'127.0.0.1','port'=>10143,'encryption'=>false,'validate_cert'=>false,'username'=>'test','password'=>'secret','protocol'=>'imap']);
$c->connect();
foreach ($c->getFolders(false) as $f) echo $f->path, ' => ', $f->name, "\n";
$inbox = $c->getFolderByPath('INBOX');
foreach ($inbox->messages()->all()->setFetchBody(false)->leaveUnread()->get() as $m) echo $m->getUid(), ' ', $m->getSubject(), ' | ', implode(',', $m->getFlags()->toArray()), "\n";
echo 'since: ', implode(',', $inbox->query()->since('06.10.2026')->setFetchBody(false)->leaveUnread()->get()->map(fn($m)=>$m->getUid())->toArray()), "\n";
