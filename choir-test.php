<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

require_once 'vendor/autoload.php';

$server = new \Choir\Http\Server('0.0.0.0', 20001, false, [
    'worker-num' => 8,
    // 'logger-level' => 'debug',
]);

$server->on('workerstart', function () {
    // xhprof_enable();
});

$server->on('workerstop', function () {
    // $data = xhprof_disable();
    // $x = new XHProfRuns_Default();
    // $id = $x->save_run($data, 'xhprof_testing');
    // echo "http://127.0.0.1:8080/index.php?run={$id}&source=xhprof_testing\n";
});

$server->on('request', function (Choir\Protocol\HttpConnection $connection) {
    $connection->end('hello world');
});

require_once '/private/tmp/xhprof-2.3.8/xhprof_lib/utils/xhprof_lib.php';
require_once '/private/tmp/xhprof-2.3.8/xhprof_lib/utils/xhprof_runs.php';

$server->start();
