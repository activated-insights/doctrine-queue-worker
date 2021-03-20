<?php

declare(strict_types=1);

namespace Pinnacle\DoctrineQueueWorker\Console;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use Illuminate\Queue\Worker;

class WorkCommand extends IlluminateWorkCommand
{
    protected $signature   = 'doctrine:queue:work';

    protected $description = 'Works jobs from the queue in a way that\'s compatible with Doctrine.';

    public function __construct(Worker $worker, Cache $cache)
    {
        parent::__construct($worker, $cache);
    }
}
