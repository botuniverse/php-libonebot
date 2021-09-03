<?php

require_once "vendor/autoload.php";

require_once "src/App/CoreActionHandler.php";

$config = new \OneBot\V12\Driver\Config\SwooleDriverConfig("demo.json");

$ob = new \OneBot\V12\OneBot("repl");
$ob->setCoreActionHandler(\App\CoreActionHandler::class);
$ob->setServerDriver(new \OneBot\V12\Driver\SwooleDriver(), $config);
$ob->run();
