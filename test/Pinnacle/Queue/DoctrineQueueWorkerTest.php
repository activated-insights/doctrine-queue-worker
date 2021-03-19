<?php

declare(strict_types=1);

namespace Pinnacle\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\WorkerOptions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DoctrineQueueWorkerTest extends TestCase
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
     * @var MockInterface|Repository
     */
    private MockInterface $cache;

    /**
     * @var MockInterface|ExceptionHandler
     */
    private MockInterface $exceptions;
    /**
     * @var DoctrineQueueWorker
     */
    private DoctrineQueueWorker $worker;
    /**
     * @var WorkerOptions
     */
    private WorkerOptions $workerOptions;

    /**
     * @test
     */
    public function runNextJob_EntityManagerIsClosed_JobRequeuedAndWorkerSetToExit(): void
    {
        // Assemble
        $this->entityManager->shouldReceive('isOpen')->andReturn(false);
        $this->entityManager->shouldNotReceive('clear');

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('hasFailed')->andReturn(false);
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
        $this->entityManager->shouldReceive('isOpen')->andReturn(false);
        $this->entityManager->shouldNotReceive('clear');

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('hasFailed')->andReturn(true);
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
        $this->entityManager->shouldReceive('isOpen')->andReturn(true);
        $this->entityManager->shouldReceive('clear');

        $this->connection->shouldReceive('ping')->andReturn(false);
        $this->connection->shouldReceive('close');
        $this->connection->shouldReceive('connect');

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
        $this->entityManager->shouldReceive('isOpen')->andReturn(true);
        $this->entityManager->shouldReceive('clear');

        $this->connection->shouldReceive('ping')->andReturn(true);
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
        $this->entityManager->shouldReceive('isOpen')->andReturn(true);
        $this->entityManager->shouldReceive('clear');

        $this->connection->shouldReceive('ping')->andReturn(true);
        $this->connection->shouldNotReceive('close');
        $this->connection->shouldNotReceive('connect');

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('handle')->andThrow(new Exception('test'));
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('hasFailed')->andReturn(false);
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
        $this->cache          = Mockery::mock(Repository::class);
        $this->exceptions     = Mockery::mock(ExceptionHandler::class);
        $isDownForMaintenance = fn () => false;

        $this->worker = new DoctrineQueueWorker(
            $this->queueManager,
            $this->dispatcher,
            $this->entityManager,
            $this->exceptions,
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

        $this->exceptions->shouldIgnoreMissing();
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
    }
}
