<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimulatedJob extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'status',
        'worker_id',
        'started_at',
        'completed_at',
    ];

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
