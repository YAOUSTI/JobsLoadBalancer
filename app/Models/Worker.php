<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_name',
        'status',
    ];

    public function simulatedJobs()
    {
        return $this->hasMany(SimulatedJob::class);
    }
}
