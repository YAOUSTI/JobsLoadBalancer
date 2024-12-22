# JobsLoadBalancer

This Laravel application demonstrates two approaches to load balancing queued jobs. It includes:

1. **Custom Solution**: Works with any queue connection and uses system-configured Supervisor workers.
2. **Horizon Solution**: Designed for Redis-backed queues and managed by Laravel Horizon (Requires PHP 8.0+).

---

## Custom Solution

### Features

-   **Custom Approach**: Checks and interacts with Supervisor workers configured on the system.
-   **Dynamic Worker Management**: Adjust workers dynamically during load simulation.
-   **Job Simulation**: Simulate different load scenarios with Artisan commands.

### Installation

#### Clone the repository:

```bash
git clone https://github.com/YAOUSTI/JobsLoadBalancer.git
cd JobLoadBalancer
```

#### Install dependencies:

```bash
composer install
```

#### Environment configuration:

Copy the example .env file:

```bash
cp .env.example .env
```

Update the .env file with your database and queue settings.

#### Generate application key:

```bash
php artisan key:generate
```

#### Run database migrations:

```bash
php artisan migrate
```

#### Set up your queue:

Configure any queue connection in the `.env` file (e.g., database, SQS, or Redis).

#### Set up Supervisor

Supervisor is used to manage workers for the custom solution. Example configuration:

```bash
[program:load-balancer]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --queue=load-balancer_%(process_num)02d --sleep=3 --tries=3
numprocs=4
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker_%(process_num)02d.log
```

Key fields:

-   `command`: Defines the queue worker process and specifies the queue.
-   `numprocs`: Number of worker processes to start.
-   `stdout_logfile`: Log file path for each worker.

Save this configuration to a file (e.g., `/etc/supervisor/conf.d/load-balancer.conf`), then update Supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start load-balancer:*
```

### Usage

#### Simulate Custom Load Balancing

This solution interacts with Supervisor-configured workers and dynamically manages jobs:

```bash
php artisan simulate:load-balancing <number_of_jobs> --max-workers=<max_workers> --block-size=<block_size>
```

Parameters:

-   `number_of_jobs`: Total jobs to dispatch.
-   `max-workers`: Maximum number of workers to spawn.
-   `block-size`: Number of workers to create before reaching the maximum number of workers spawned.

Usage:

-   Designed for use with any queue connection.
-   Automatically checks and balances Supervisor-managed workers.

#### Example Use Case: Launching 100 Jobs with 4 Workers and 1 Block Size

When you launch 100 jobs with 4 workers and a block size of 1 using the custom load balancing solution, the following will happen:

1. **Job Dispatching**: The system will dispatch 100 jobs to the queue.
2. **Worker Initialization**: Initially, 4 workers will be started by Supervisor as specified in the configuration.
3. **Dynamic Management**: Since the block size is set to 1, the system will dynamically manage the workers, adding one worker at a time as needed.
4. **Job Processing**: Each worker will pick up jobs from the queue and process them. As jobs are completed, workers will continue to pick up new jobs until all 100 jobs are processed.
5. **Load Balancing**: The system will continuously monitor the job queue and worker performance, ensuring that the number of active workers is optimized for the current load.
6. **Completion**: Once all jobs are processed, the workers will remain idle, ready to process new jobs as they are dispatched.

This setup ensures efficient job processing and optimal resource utilization, dynamically adjusting the number of workers based on the job load.

---

## Horizon Solution

### Features

-   **Horizon Solution**: Utilizes Laravel Horizon for Redis-based queue management.
-   **Dynamic Worker Management**: Adjust workers dynamically during load simulation.
-   **Job Simulation**: Simulate different load scenarios with Artisan commands.
-   **Monitoring**: Horizon provides a real-time interface for Redis-backed jobs.

### Installation

#### Clone the repository:

```bash
git clone https://github.com/YAOUSTI/JobsLoadBalancer.git
cd JobLoadBalancer
```

#### Install dependencies:

```bash
composer install
```

#### Environment configuration:

Copy the example .env file:

```bash
cp .env.example .env
```

Update the .env file with your database and queue settings.

#### Generate application key:

```bash
php artisan key:generate
```

#### Run database migrations:

```bash
php artisan migrate
```

#### Set up your queue:

Set the queue connection to redis in `.env`:

```bash
QUEUE_CONNECTION=redis
```

### Horizon Installation

You may install Horizon into your project using the Composer package manager:

```bash
composer require laravel/horizon
```

After installing Horizon, publish its assets using the `horizon:install` Artisan command:

```bash
php artisan horizon:install
```

### Horizon Configuration

This configuration file `config/horizon.php` allows you to manage and customize how Horizon supervises your queue workers across different environments. Here's a breakdown of what this configuration enables:

```bash
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['high', 'default'],
                'balance' => 'auto',
                'minProcesses' => 5,
                'maxProcesses' => 25,
                'tries' => 3,
            ],

            'supervisor-2' => [
                'connection' => 'redis',
                'queue' => ['low'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
            ],
        ],
    ],
```

#### Environment-Specific Settings:

- The `environments` array separates configurations for different environments such as production and local.
- Each environment can have unique supervisor settings tailored to the workload and resources of that environment.

#### Supervisor Configuration:

- Supervisors are defined with specific settings for managing queue workers. For example, `supervisor-1` and `supervisor-2` are defined with their respective properties.

#### Production Environment:

- `maxProcesses`: Specifies the maximum number of processes that the supervisor can scale to, ensuring resource limitations are respected.
- `balanceMaxShift`: Adjusts the worker balancing sensitivity, allowing the system to adapt gradually to changes in job loads.
- `balanceCooldown`: Sets the cooldown period (in seconds) between balance adjustments, preventing excessive recalibration.

#### Local Environment:

- `connection`: Defines the Redis connection used for the queues.
- `queue`: Specifies the queues the supervisor will listen to. For example:
    - `['high', 'default']`: Handles high-priority and default jobs.
    - `['low']`: Handles low-priority jobs.
- `balance`: Determines the load balancing strategy:
    - `auto`: Automatically adjusts worker allocation based on the job load.
    - `simple`: Distributes workers evenly across the specified queues.
    - `false` : Turns off the balancing strategy.
- `minProcesses` and `maxProcesses`:
    - Define the range of processes the supervisor can use, allowing dynamic scaling based on job demand.
- `tries`: Sets the maximum number of attempts for a job before marking it as failed.

### Use Cases of This Configuration:

#### Dynamic Worker Scaling:

- Adjusts the number of workers in real time, depending on the job load and priority of the queues.

#### Environment-Specific Optimization:

- In production, the focus is on maintaining efficiency and stability with fine-tuned scaling and cooldown settings.
- In local, more aggressive scaling and flexibility are allowed for testing and development purposes.

#### Load Balancing:

- Enables different balancing strategies (`auto`, `simple`) to ensure optimal job distribution across queues.

#### Queue Prioritization:

- Assigns specific supervisors to different priority queues (`high`, `low`), ensuring high-priority jobs are processed first.

#### Error Handling:

- Configures job retry attempts (`tries`) to handle transient failures gracefully.

This configuration centralizes all Horizon-related settings into one file, making it easy to manage and customize worker behavior across environments and queues.

### Horizon: Running & Deployment

Once you've configured your Horizon supervisors and workers in `config/horizon.php`, you can start Horizon with:
```bash
php artisan horizon
```

To pause or continue all Horizon processes:
```bash
php artisan horizon:pause
php artisan horizon:continue
```

Or pause / continue a specific supervisor:
```bash
php artisan horizon:pause-supervisor supervisor-1
php artisan horizon:continue-supervisor supervisor-1
```

Check status:
```bash
php artisan horizon:status
```

Gracefully end Horizon (finishes current jobs then stops):
```bash
php artisan horizon:terminate
```
Use this command before deploying code changes so Supervisor can restart Horizon and load your updated code.

### Installing Supervisor

Supervisor is a Linux process monitor that restarts Horizon automatically if it stops.
On Ubuntu:
```bash
sudo apt-get install supervisor
```

Create a file like /etc/supervisor/conf.d/horizon.conf:
```bash
[program:horizon]
process_name=%(program_name)s
command=php /home/forge/example.com/artisan horizon
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/example.com/horizon.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

Set `stopwaitsecs` higher than your longest-running job, or Supervisor might end it before it is finished processing.

### Monitor with Horizon

Horizon provides a real-time UI to monitor and manage Redis-based jobs. Access Horizon at:

```http
http://<your-domain>/horizon
```
#### Simulate Horizon Load Balancing
This solution interacts with Horizon-managed workers and dynamically manages jobs:

```bash
php artisan simulate:horizon <totalJobs> --queue=<queue>
```

Parameters:

- `totalJobs`: Total jobs to dispatch.
- `queue`: The queue to which jobs will be dispatched (default is `default`).

Usage:

- Designed for use with Redis-backed queues managed by Horizon.
- Automatically dispatches jobs to the specified queue and updates their status.

#### Example Use Case: Launching 100 Jobs to the Default Queue

When you launch 100 jobs to the default queue using the Horizon load balancing solution, the following will happen:

1. **Job Insertion**: The system will insert 100 jobs into the `simulated_jobs` table with a status of "pending".
2. **Job Dispatching**: The system will fetch all pending jobs and dispatch them to the specified Horizon-managed queue.
3. **Status Update**: Each job's status will be updated to "dispatched" after being queued.
4. **Job Processing**: Horizon-managed workers will pick up jobs from the queue and process them. As jobs are completed, their status will be updated accordingly.
5. **Monitoring**: You can monitor the job processing in real-time using the Horizon UI.

This setup ensures efficient job processing and optimal resource utilization, leveraging Horizon's dynamic worker management and real-time monitoring capabilities.

---

## Key Differences

| Feature           | Custom Solution                            | Horizon Solution     |
| ----------------- | ------------------------------------------ | -------------------- |
| Queue Connection  | Works with any queue (e.g., database, SQS) | Requires Redis       |
| Worker Management | Interacts with Supervisor workers          | Horizon auto-scaling |
| Monitoring        | Logs and status updates                    | Horizon UI           |
| Flexibility       | High, supports multiple backends           | Limited to Redis     |

This provides an overview of the two solutions, their usage, and configuration details. Let me know if you need additional information or enhancements!
