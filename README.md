# doctrine-queue-worker
**doctrine-queue-worker** is a package intended to address common issues with running Laravel 10.x queue worker processes with an application that interacts with the Doctrine ORM.

### Features

- Re-queues jobs and kills the worker process when the `EntityManager` closes due to an exception.
- Ensures the `EntityManager`'s database connection remains open between jobs.
- Clears the `EntityManager` between job executions.

### Installation

- Require the library via Composer: `composer require pinnacle/doctrine-queue-worker`

### Usage

- Setup worker processes to invoke the `doctine:queue:work` artisan command. Options for the command are the same as the built-in Laravel `queue:work` command.
- Use a process manager like supervisor to ensure your queue workers will be restarted after they die.
