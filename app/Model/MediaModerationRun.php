<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $has_retryable_failed_media_items
 * @property bool $has_retryable_failed_text_items
 */
class MediaModerationRun extends Model
{
    public const TYPE_MEDIA = 'media';
    public const TYPE_TEXT = 'text';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_RUNNING = 'running';
    public const STATUS_WAITING_PROVIDER_CAPACITY = 'waiting_provider_capacity';
    public const STATUS_QUOTA_PAUSED = 'quota_paused';
    public const STATUS_ACTION_REQUIRED = 'action_required';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'initiated_by',
        'scan_type',
        'provider',
        'model_signature',
        'models',
        'scope',
        'policy',
        'status',
        'execution_mode',
        'connection',
        'queue',
        'max_items',
        'max_operations',
        'operations_reserved',
        'operations_used',
        'total_items',
        'processed_items',
        'safe_items',
        'review_items',
        'violation_items',
        'failed_items',
        'skipped_items',
        'reused_items',
        'started_at',
        'last_activity_at',
        'cancel_requested_at',
        'completed_at',
        'failed_at',
        'error',
    ];

    protected $casts = [
        'models' => 'array',
        'scope' => 'array',
        'policy' => 'array',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** The approval ID is stored in scope, not in a separate database column. */
    public function getContentReviewIdAttribute(): ?int
    {
        return isset($this->scope['content_review_id']) ? (int) $this->scope['content_review_id'] : null;
    }

    /** @return BelongsTo<ContentReview, $this> */
    public function contentReview(): BelongsTo
    {
        return $this->belongsTo(ContentReview::class, 'content_review_id');
    }

    /**
     * @return HasMany<MediaModerationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MediaModerationItem::class, 'run_id');
    }

    /**
     * @return HasMany<TextModerationItem, $this>
     */
    public function textItems(): HasMany
    {
        return $this->hasMany(TextModerationItem::class, 'run_id');
    }

    public function isMedia(): bool
    {
        return ($this->scan_type ?: self::TYPE_MEDIA) === self::TYPE_MEDIA;
    }

    public function isText(): bool
    {
        return $this->scan_type === self::TYPE_TEXT;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_COMPLETED_WITH_ERRORS,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
        ], true);
    }
}
