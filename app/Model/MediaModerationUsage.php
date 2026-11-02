<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class MediaModerationUsage extends Model
{
    protected $table = 'media_moderation_usage';

    protected $fillable = [
        'provider',
        'usage_date',
        'operations_used',
        'operations_reserved',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];
}
