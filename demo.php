<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

$ob = new \OneBot\V12\OneBot('repl', 'qq', 'REPL-1');
$ob->setLogger(new \OneBot\Logger\Console\ConsoleLogger());
$ob->setDriver(
    new \OneBot\V12\Driver\WorkermanDriver(),
    new \OneBot\V12\Config\Config('demo.json')
);
$ob->setActionHandler(\OneBot\V12\Action\ReplAction::class);
$ob->run();
