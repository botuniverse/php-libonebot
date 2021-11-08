<?php

require_once "vendor/autoload.php";

/** @noinspection PhpIncludeInspection */
require_once "src/App/ReplAction.php";

try {
    $ob = new \OneBot\V12\OneBot("repl", "qq");
    $ob->setServerDriver(new \OneBot\V12\Driver\WorkermanDriver(), new \OneBot\V12\Driver\Config\WorkermanConfig("demo.json"));
    $ob->setActionHandler(\App\ReplAction::class);
    $ob->run();
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
}
