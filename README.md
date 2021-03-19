# doctrine-queue-worker
**doctrine-queue-worker** is a package intended address some issues with running Laravel 8.x queue worker processes with an application that interacts with the Doctrine ORM.
### Features

- Re-queues jobs and kills the worker process when the Doctrine EntityManager closes due to an exception.
- Re-queues jobs and kills the worker process when the connection to the database dies.

### Usage

- Setup worker processes to invoke the `doctine:queue:work` artisan command. Options for the command are the same as the built-in Laravel `queue:work` command.
- Use a process manager like supervisor to ensure your queue workers will be restarted after they die.
