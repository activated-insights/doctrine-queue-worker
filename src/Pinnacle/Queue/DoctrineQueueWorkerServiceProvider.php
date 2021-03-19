<?php

declare(strict_types=1);

namespace Pinnacle\Queue;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Pinnacle\Queue\Console\DoctrineQueueWorkerCommand;

class DoctrineQueueWorkerServiceProvider extends ServiceProvider implements DeferrableProvider
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
            DoctrineQueueWorkerCommand::class,
            DoctrineQueueWorker::class
        ];
    }

    private function registerWorker(): void
    {
        $this->app->singleton(
            DoctrineQueueWorker::class,
            function (Application $app): DoctrineQueueWorker {
                $isDownForMaintenance = function (): bool {
                    return $this->app->isDownForMaintenance();
                };

                return new DoctrineQueueWorker(
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
            DoctrineQueueWorkerCommand::class,
            fn ($app) => new DoctrineQueueWorkerCommand($app[DoctrineQueueWorker::class], $app['cache'])
        );
    }
}
