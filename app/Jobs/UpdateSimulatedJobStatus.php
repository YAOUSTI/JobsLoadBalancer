<?php

namespace App\Jobs;

use App\Models\SimulatedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateSimulatedJobStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $time = rand(2, 10);
        sleep($time);
        $job = SimulatedJob::find($this->jobId);
        if ($job) {
            $job->update(['status' => 'completed']);
            \Log::info("SimulatedJob #{$this->jobId} status updated to 'completed' after {$time} seconds");
        }
    }
}
