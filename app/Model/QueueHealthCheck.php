<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class QueueHealthCheck extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'execution_mode',
        'connection',
        'queue',
        'status',
        'dispatched_at',
        'started_at',
        'completed_at',
        'failed_at',
        'error',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
