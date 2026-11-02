<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentTranscoding extends Model
{
    public const DRIVER_FFMPEG = 'ffmpeg';
    public const DRIVER_COCONUT = 'coconut';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $primaryKey = 'attachment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'attachment_id',
        'driver',
        'status',
        'source_path',
        'output_path',
        'provider_job_id',
        'execution_mode',
        'connection',
        'queue',
        'options',
        'attempts',
        'error_code',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'options' => 'array',
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }
}
