<?php

/** @noinspection PhpUndefinedClassInspection */
/* @noinspection PhpUndefinedNamespaceInspection */

declare(strict_types=1);

namespace OneBot\Exception;

use OneBot\Util\Singleton;

class ExceptionHandler implements ExceptionHandlerInterface
{
    use Singleton;

    protected $whoops;

    protected ?ExceptionHandlerInterface $overridden_by = null;

    protected function __construct()
    {
        $this->tryEnableCollision();
    }

    public function getWhoops()
    {
        return $this->whoops;
    }

    /**
     * 处理异常
     */
    public function handle(\Throwable $e): void
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

    protected function handle0(\Throwable $e): void
    {
        if (is_null($this->whoops)) {
            ob_logger()->error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . '(' . $e->getLine() . ')');
            ob_logger()->error($e->getTraceAsString());
            return;
        }

        $this->whoops->handleException($e);
    }

    protected function tryEnableCollision($solution_repo = null): void
    {
        $whoops_class = 'Whoops\Run';
        $collision_namespace = 'NunoMaduro\Collision';
        $collision_handler = "{$collision_namespace}\\Handler";
        $collision_writer = "{$collision_namespace}\\Writer";
        $collision_repo = "{$collision_namespace}\\Contracts\\SolutionsRepository";
        if (class_exists($collision_handler) && class_exists($whoops_class)) {
            if ($solution_repo instanceof $collision_repo) {
                // @phpstan-ignore-next-line
                $writer = new $collision_writer($solution_repo);
            } else {
                // @phpstan-ignore-next-line
                $writer = new $collision_writer();
            }

            $this->whoops = new $whoops_class();
            $this->whoops->allowQuit(false);
            $this->whoops->writeToOutput(false);
            $this->whoops->pushHandler(new $collision_handler($writer));
            $this->whoops->register();
        }
    }
}
