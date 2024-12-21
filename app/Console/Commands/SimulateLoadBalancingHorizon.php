<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SimulatedJob;
use App\Jobs\ProcessSimulatedJob;
use App\Jobs\UpdateSimulatedJobStatus;
use Illuminate\Support\Facades\Log;

class SimulateLoadBalancingHorizon extends Command
{
    protected $signature = 'simulate:horizon
                            {totalJobs : The total number of simulated jobs to create}
                            {--queue=default : The queue to which jobs will be dispatched}';

    protected $description = 'Simulate a job load balancer that distributes jobs among Horizon-managed workers.';

    public function handle()
    {
        // Parse the arguments and options
        $totalJobs  = (int) $this->argument('totalJobs');
        $queue      = $this->option('queue');

        // 1) Insert the $totalJobs into the simulated_jobs table as "pending"
        $jobs = [];
        for ($i = 0; $i < $totalJobs; $i++) {
            $jobs[] = ['status' => 'pending', 'created_at' => now(), 'updated_at' => now()];
        }
        SimulatedJob::insert($jobs);
        $this->info("Inserted {$totalJobs} simulated jobs with status=pending.");

        // 2) Fetch all pending jobs
        $pendingJobs = SimulatedJob::where('status', 'pending')->get();
        $this->info("Fetched {$pendingJobs->count()} pending jobs.");

        // 3) Dispatch all pending jobs to the specified Horizon-managed queue
        foreach ($pendingJobs as $job) {
            UpdateSimulatedJobStatus::dispatch($job->id)->onQueue($queue);

            // Update the job status to 'dispatched'
            $job->update(['status' => 'dispatched']);

            $this->info("Dispatched job #{$job->id} to queue '{$queue}'.");
        }

        $this->info("All pending jobs have been dispatched to the '{$queue}' queue. Monitor Horizon for processing details.");
        return 0;
    }
}
