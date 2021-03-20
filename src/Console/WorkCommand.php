<?php

declare(strict_types=1);

namespace Pinnacle\DoctrineQueueWorker\Console;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use Illuminate\Queue\Worker;

class WorkCommand extends IlluminateWorkCommand
{
    protected $description = 'Works jobs from the queue in a way that\'s compatible with Doctrine.';

    public function __construct(Worker $worker, Cache $cache)
    {
        // Keep the same signature as built-in queue worker, but use our command name.
        $this->signature = str_replace('queue:work', 'doctrine:queue:work', $this->signature);

        parent::__construct($worker, $cache);
    }
}
