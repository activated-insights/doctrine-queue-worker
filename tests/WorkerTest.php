<?php

declare(strict_types=1);

namespace Pinnacle\DoctrineQueueWorker\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\WorkerOptions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pinnacle\DoctrineQueueWorker\Worker;

class WorkerTest extends TestCase
{
    /**
     * @var MockInterface|QueueManager
     */
    private MockInterface $queueManager;

    /**
     * @var MockInterface|Queue
     */
    private MockInterface $queue;

    /**
     * @var MockInterface|Dispatcher
     */
    private MockInterface $dispatcher;

    /**
     * @var MockInterface|EntityManagerInterface
     */
    private MockInterface $entityManager;

    /**
     * @var MockInterface|Connection
     */
    private MockInterface $connection;

    /**
     * @var MockInterface|AbstractPlatform
     */
    private MockInterface $platform;

    private Worker $worker;

    private WorkerOptions $workerOptions;

    /**
     * @test
     */
    public function runNextJob_EntityManagerIsClosed_JobRequeuedAndWorkerSetToExit(): void
    {
        // Assemble
        $this->entityManager->shouldReceive('isOpen')->andReturn(false)->once();
        $this->entityManager->shouldNotReceive('clear');

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('isDeleted')->andReturn(false)->once();
        $job->shouldReceive('isReleased')->andReturn(false)->once();
        $job->shouldReceive('hasFailed')->andReturn(false)->once();
        $job->shouldReceive('release')->once();

        $this->prepareQueue($job);

        // Act
        $this->worker->runNextJob('connection', 'default', $this->workerOptions);

        // Assert
        $this->assertTrue($this->worker->shouldQuit);
    }

    /**
     * @test
     */
    public function runNextJob_EntityManagerClosedAndJobFailed_JobNotRequeuedAndWorkerSetToExit(): void
    {
        // Assemble
        $this->entityManager->shouldReceive('isOpen')->andReturn(false)->once();
        $this->entityManager->shouldNotReceive('clear');

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('isDeleted')->andReturn(false)->once();
        $job->shouldReceive('isReleased')->andReturn(false)->once();
        $job->shouldReceive('hasFailed')->andReturn(true)->once();
        $job->shouldNotReceive('release');

        $this->prepareQueue($job);

        // Act
        $this->worker->runNextJob('connection', 'default', $this->workerOptions);

        // Assert
        $this->assertTrue($this->worker->shouldQuit);
    }

    /**
     * @test
     */
    public function runNextJob_DatabaseConnectionClosed_ShouldReOpenConnection(): void
    {
        // Assemble
        $this->entityManager->shouldReceive('isOpen')->andReturn(true)->once();
        $this->entityManager->shouldReceive('clear')->once();

        $this->platform->shouldReceive('getDummySelectSQL')->andReturn('SELECT 1')->once();

        $this->connection->shouldReceive('executeQuery')->once();
        $this->connection->shouldReceive('getDatabasePlatform')->andReturn($this->platform)->once();
        $this->connection->shouldNotReceive('close');
        $this->connection->shouldNotReceive('connect');

        $job = Mockery::mock(Job::class);
        $job->shouldIgnoreMissing();

        $this->prepareQueue($job);

        // Act
        $this->worker->runNextJob('connection', 'default', $this->workerOptions);

        // Assert
        $this->assertFalse($this->worker->shouldQuit);
    }

    /**
     * @test
     */
    public function runNextJob_EntityManagerAndConnectionOpen_ShouldClearEntityManager(): void
    {
        // Assemble
        $this->entityManager->shouldReceive('isOpen')->andReturn(true)->once();
        $this->entityManager->shouldReceive('clear')->once();

        $this->platform->shouldReceive('getDummySelectSQL')->andReturn('SELECT 1')->once();

        $this->connection->shouldReceive('executeQuery')->once();
        $this->connection->shouldReceive('getDatabasePlatform')->andReturn($this->platform)->once();
        $this->connection->shouldNotReceive('close');
        $this->connection->shouldNotReceive('connect');

        $job = Mockery::mock(Job::class);
        $job->shouldIgnoreMissing();

        $this->prepareQueue($job);

        // Act
        $this->worker->runNextJob('connection', 'default', $this->workerOptions);

        // Assert
        $this->assertFalse($this->worker->shouldQuit);
    }

    /**
     * @test
     */
    public function runNextJob_JobThrowsException_ShouldRequeueJobAndNotKillWorkerProcess(): void
    {
        // Assemble
        $this->entityManager->shouldReceive('isOpen')->andReturn(true)->once();
        $this->entityManager->shouldReceive('clear')->once();

        $this->platform->shouldReceive('getDummySelectSQL')->andReturn('SELECT 1')->once();

        $this->connection->shouldReceive('executeQuery')->once();
        $this->connection->shouldReceive('getDatabasePlatform')->andReturn($this->platform)->once();
        $this->connection->shouldNotReceive('close');
        $this->connection->shouldNotReceive('connect');

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('fire')->andThrow(new Exception('test'))->once();
        $job->shouldReceive('isDeleted')->andReturn(false)->twice();
        $job->shouldReceive('isReleased')->andReturn(false)->once();
        $job->shouldReceive('hasFailed')->andReturn(false)->twice();
        $job->shouldReceive('release')->once();
        $job->shouldIgnoreMissing();

        $this->prepareQueue($job);

        // Act
        $this->worker->runNextJob('connection', 'default', $this->workerOptions);

        // Assert
        $this->assertFalse($this->worker->shouldQuit);
    }

    protected function setUp(): void
    {
        $this->queueManager   = Mockery::mock(QueueManager::class);
        $this->queue          = Mockery::mock(Queue::class);
        $this->dispatcher     = Mockery::mock(Dispatcher::class);
        $this->entityManager  = Mockery::mock(EntityManagerInterface::class);
        $this->connection     = Mockery::mock(Connection::class);
        $this->platform       = Mockery::mock(AbstractPlatform::class);
        $exceptions           = Mockery::mock(ExceptionHandler::class);
        $isDownForMaintenance = fn () => false;

        $this->worker = new Worker(
            $this->queueManager,
            $this->dispatcher,
            $this->entityManager,
            $exceptions,
            $isDownForMaintenance
        );
        $this->workerOptions = new WorkerOptions(
            'default',
            0,
            128,
            60,
            1,
            3
        );

        $exceptions->shouldIgnoreMissing();
        $this->entityManager->shouldReceive('getConnection')->andReturn($this->connection);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @param MockInterface|Job $job
     */
    private function prepareQueue(MockInterface $job): void
    {
        $this->queueManager->shouldReceive('isDownForMaintenance')->andReturn(false);
        $this->queueManager->shouldReceive('connection')->andReturn($this->queue);
        $this->queueManager->shouldReceive('getName')->andReturn('default');

        $this->queue->shouldReceive('pop')->andReturn(...[$job]);
        $this->queue->shouldReceive('getConnectionName')->andReturn('connection');

        $this->dispatcher->shouldReceive('dispatch');
    }
}
