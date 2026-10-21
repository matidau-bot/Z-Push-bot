<?php require 'vendor/autoload.php';
use DirectoryTree\ImapEngine\Mailbox;
$mb = new Mailbox(['host'=>'127.0.0.1','port'=>10143,'encryption'=>null,'username'=>'test','password'=>'secret','validate_cert'=>false]);
echo implode(' | ', $mb->folders()->get()->map(fn($f) => $f->path())->all()), "\n";
$in = $mb->inbox();
foreach ($in->messages()->withHeaders()->withFlags()->get() as $m) echo $m->uid(), ' ', $m->subject(), ' | ', implode(',', $m->flags()), "\n";
echo 'since: ', implode(',', $in->messages()->since(new DateTime('2026-10-06'))->get()->map(fn($m) => $m->uid())->all()), "\n";
