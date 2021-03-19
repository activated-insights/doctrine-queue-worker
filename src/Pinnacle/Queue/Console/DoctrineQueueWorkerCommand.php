<?php

declare(strict_types=1);

namespace Pinnacle\Queue\Console;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Worker;

class DoctrineQueueWorkerCommand extends WorkCommand
{
    protected $signature   = 'doctrine:queue:work';

    protected $description = 'Works jobs from the queue in a way that\'s compatible with Doctrine.';

    public function __construct(Worker $worker, Cache $cache)
    {
        parent::__construct($worker, $cache);
    }
}
