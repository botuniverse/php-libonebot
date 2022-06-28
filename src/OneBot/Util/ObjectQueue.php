<?php

declare(strict_types=1);

namespace OneBot\Util;

use RuntimeException;
use SplQueue;

class ObjectQueue
{
    private static $queues;

    private static $limit = [];

    public static function limit(string $queue_name, int $count)
    {
        self::$limit[$queue_name] = $count;
    }

    public static function enqueue(string $queue_name, $value)
    {
        if (!isset(self::$queues[$queue_name])) {
            self::$queues[$queue_name] = new SplQueue();
        }
        if (self::$queues[$queue_name]->count() >= (self::$limit[$queue_name] ?? 999999)) {
            self::$queues[$queue_name]->dequeue();
        }
        self::$queues[$queue_name]->enqueue($value);
    }

    public static function dequeue(string $queue_name, int $count = 1): array
    {
        $arr = [];
        if (!isset(self::$queues[$queue_name])) {
            self::$queues[$queue_name] = new SplQueue();
        }
        if ($count <= 0) {
            $count = 999999999;
        }
        try {
            for ($i = 0; $i < $count; ++$i) {
                $arr[] = self::$queues[$queue_name]->dequeue();
            }
        } catch (RuntimeException $e) {
        }
        return $arr;
    }
}
