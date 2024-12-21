<?php

namespace App\Jobs;

use App\Models\SimulatedJob;
use App\Models\Worker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSimulatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $simulatedJobId;
    public $workerId;

    public function __construct($simulatedJobId, $workerId)
    {
        $this->simulatedJobId = $simulatedJobId;
        $this->workerId       = $workerId;
    }

    // Execute the job
    public function handle()
    {
        // Retrieve the job
        $job = SimulatedJob::find($this->simulatedJobId);
        $worker = Worker::find($this->workerId);

        if (!$job || !$worker) {
            return;
        }

        // Update job to in-progress
        $job->update([
            'status' => 'in-progress',
            'started_at' => now(),
        ]);

        // Update worker status to busy
        $worker->update(['status' => 'busy']);

        // Simulate the job taking 2-10 seconds
        $sleepTime = rand(2, 10);
        sleep($sleepTime);

        // Mark job as completed
        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Mark worker as idle
        $worker->update(['status' => 'idle']);

        // Log for debugging
        \Log::info("Job #{$job->id} completed by worker #{$worker->id} ({$worker->queue_name}) after {$sleepTime} seconds");
    }
}
