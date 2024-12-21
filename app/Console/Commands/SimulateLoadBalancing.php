<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SimulatedJob;
use App\Models\Worker;
use App\Jobs\ProcessSimulatedJob;
use Illuminate\Support\Facades\Log;

class SimulateLoadBalancing extends Command
{
    protected $signature = 'simulate:load-balancing
                            {totalJobs : The total number of simulated jobs to create}
                            {--max-workers=5 : The maximum number of workers (Y)}
                            {--block-size=2 : The block size (Z) for scaling}';

    protected $description = 'Simulate a job load balancer that distributes jobs among a dynamic number of workers.';

    public function handle()
    {
        // Parse the arguments and options
        $totalJobs  = (int) $this->argument('totalJobs');
        $maxWorkers = (int) $this->option('max-workers');
        $blockSize  = (int) $this->option('block-size');

        // 1) Insert the $totalJobs into the simulated_jobs table as "pending"
        for ($i = 0; $i < $totalJobs; $i++) {
            SimulatedJob::create(['status' => 'pending']);
        }
        $this->info("Inserted {$totalJobs} simulated jobs with status=pending.");

        // 2) Ensure at least 1 worker exists (and is idle)
        Worker::firstOrCreate(
            ['queue_name' => 'load-balancer_00'],
            ['status' => 'idle']
        );
        $this->info("Ensured worker-1 exists and is idle.");

        // Keep track of how many workers we currently have
        $currentWorkerCount = Worker::count();

        // 3) Fetch all pending jobs
        $pendingJobs = SimulatedJob::where('status', 'pending')->get();

        // 4) Loop until all jobs are assigned to existing or new workers
        while ($pendingJobs->isNotEmpty()) {

            // Try to find an idle worker
            $idleWorker = Worker::where('status', 'idle')->first();
            if ($idleWorker) {
                $this->info("Found idle worker: {$idleWorker->queue_name}");
            }

            if (!$idleWorker) {
                // See if we can create more workers (scaling in blocks up to max)
                if ($currentWorkerCount < $maxWorkers) {
                    for ($i = 0; $i < $blockSize; $i++) {
                        $newIndex = $currentWorkerCount;
                        if ($newIndex < $maxWorkers) {
                            $workerName = "load-balancer_0{$newIndex}";
                            Worker::create([
                                'queue_name' => $workerName,
                                'status'     => 'idle',
                            ]);
                            $this->info("Created new worker: {$workerName}");
                            $currentWorkerCount++;
                        }
                    }

                    // After creating one new worker or more, loop will continue; we’ll see if one is idle now
                    continue;
                } else {
                    // We are at max capacity, wait for a worker to become idle
                    $this->info("All {$maxWorkers} workers are busy. Waiting for an idle worker...");
                    // Check again
                    continue;
                }
            }

            // If we do have an idle worker, assign a job to that worker.
            $job = $pendingJobs->shift(); // remove the first pending job from the collection

            // Mark the job with the worker_id for reference
            $job->update(['worker_id' => $idleWorker->id]);

            // Dispatch the job to that idle worker queue
            ProcessSimulatedJob::dispatch($job->id, $idleWorker->id)->onQueue($idleWorker->queue_name);

            // Mark the worker as busy right away (the job will set it back to idle when completed)
            $idleWorker->update(['status' => 'busy']);

            // Log this assignment
            $this->info("Assigned job #{$job->id} to worker {$idleWorker->queue_name}.");
        }

        // 5) All jobs have been assigned
        $this->info("All pending jobs have been assigned. Monitor the queue to see them finish.");
        return 0;
    }
}