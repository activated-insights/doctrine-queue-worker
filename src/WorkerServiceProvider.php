<?php

declare(strict_types=1);

namespace Pinnacle\DoctrineQueueWorker;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Pinnacle\DoctrineQueueWorker\Console\WorkCommand;

class WorkerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->registerWorker();
        $this->registerWorkCommand();
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return [
            WorkCommand::class,
            Worker::class,
        ];
    }

    private function registerWorker(): void
    {
        $this->app->singleton(
            Worker::class,
            function (Application $app): Worker {
                $isDownForMaintenance = function (): bool {
                    return $this->app->isDownForMaintenance();
                };

                return new Worker(
                    $app['queue'],
                    $app['events'],
                    $app[EntityManagerInterface::class],
                    $app[ExceptionHandler::class],
                    $isDownForMaintenance
                );
            }
        );
    }

    private function registerWorkCommand(): void
    {
        $this->app->singleton(
            WorkCommand::class,
            fn($app) => new WorkCommand($app[Worker::class], $app['cache'])
        );
    }
}
