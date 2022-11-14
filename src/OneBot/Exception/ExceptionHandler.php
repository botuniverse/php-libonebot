<?php

/** @noinspection PhpUndefinedClassInspection */
/* @noinspection PhpUndefinedNamespaceInspection */

declare(strict_types=1);

namespace OneBot\Exception;

use OneBot\Util\Singleton;
use Throwable;

class ExceptionHandler implements ExceptionHandlerInterface
{
    use Singleton;

    protected $whoops;

    protected ?ExceptionHandlerInterface $overridden_by;

    protected function __construct()
    {
        $whoops_class = 'Whoops\Run';
        $collision_class = 'NunoMaduro\Collision\Handler';
        if (class_exists($collision_class) && class_exists($whoops_class)) {
            /* @phpstan-ignore-next-line */
            $this->whoops = new $whoops_class();
            $this->whoops->allowQuit(false);
            $this->whoops->writeToOutput(false);
            $this->whoops->pushHandler(new $collision_class());
            $this->whoops->register();
        }
    }

    public function getWhoops()
    {
        return $this->whoops;
    }

    /**
     * 处理异常
     */
    public function handle(Throwable $e): void
    {
        if ($this->overridden_by !== null) {
            $this->overridden_by->handle($e);
            return;
        }

        $this->handle0($e);
    }

    public function overrideWith(ExceptionHandlerInterface $handler): void
    {
        $this->overridden_by = $handler;
    }

    protected function handle0(Throwable $e): void
    {
        if (is_null($this->whoops)) {
            ob_logger()->error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . '(' . $e->getLine() . ')');
            ob_logger()->error($e->getTraceAsString());
            return;
        }

        $this->whoops->handleException($e);
    }
}
