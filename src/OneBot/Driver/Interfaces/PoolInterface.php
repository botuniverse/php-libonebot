<?php

declare(strict_types=1);

namespace OneBot\Driver\Interfaces;

interface PoolInterface
{
    public function __construct(int $size, string $construct_class, ...$args);

    public function __destruct();

    public function get(): object;

    public function put(object $object): bool;

    public function getFreeCount(): int;
}
