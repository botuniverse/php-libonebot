<?php

declare(strict_types=1);

use OneBot\V12\Config\Config;
use OneBot\V12\OneBotBuilder;

require_once 'vendor/autoload.php';

$config = [
    'name' => 'repl',

    'platform' => 'qq',

    'self_id' => 'REPL-1',

    'db' => true,

    'logger' => [
        'class' => \OneBot\Logger\Console\ConsoleLogger::class,
        'level' => 'debug',
    ],

    'driver' => [
        'class' => \OneBot\Driver\WorkermanDriver::class,
        'config' => [
            //'driver_init_policy' => \OneBot\Driver\DriverInitPolicy::MULTI_PROCESS_INIT_IN_USER_PROCESS,
            'init_in_user_process_block' => true,
            'swoole_server_mode' => SWOOLE_BASE,
        ],
    ],

    'communications' => [
        /*[
            'type' => 'http',
            'host' => '127.0.0.1',
            'port' => 2345,
            'access_token' => '',
            'event_enabled' => true,
            'event_buffer_size' => 100,
        ],
        [
            'type' => 'webhook',
            'url' => 'https://example.com/webhook',
            'access_token' => '',
            'timeout' => 5,
        ],
        [
            'type' => 'websocket',
            'host' => '127.0.0.1',
            'port' => 2346,
            'access_token' => '',
        ],*/
        [
            'type' => 'ws_reverse',
            'url' => 'ws://127.0.0.1:2347',
            'access_token' => '',
            'reconnect_interval' => 5,
        ],
    ],
];

//OneBotBuilder::factory()
//    ->setName($config['name'])
//    ->setPlatform($config['platform'])
//    ->setSelfId($config['self_id'])
//    ->useLogger($config['logger'])
//    ->useDriver($config['driver'])
//    ->setCommunicationsProtocol($config['communications'])
//    ->build();

//OneBotBuilder::buildFromConfig(new Config($config));

$ob = OneBotBuilder::buildFromArray($config);
$ob->setActionHandler(\OneBot\V12\Action\ReplAction::class);
$ob->run();
