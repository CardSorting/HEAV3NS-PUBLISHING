<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReview extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_CANCELLED = 'cancelled';
    public const MODE_MANUAL = 'manual';
    public const MODE_ASSISTED = 'assisted';
    public const MODE_AUTOMATIC = 'automatic';

    protected $fillable = [
        'source_type', 'source_id', 'owner_user_id', 'status', 'fingerprint', 'mode', 'policy',
        'media_run_id', 'text_run_id', 'attention_reason', 'rejection_reason', 'review_notes',
        'reviewed_by', 'reviewed_at', 'decision_source',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'owner_user_id' => 'integer',
        'policy' => 'array',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected $hidden = [
        'fingerprint', 'policy', 'media_run_id', 'text_run_id', 'attention_reason', 'review_notes',
        'mediaRun', 'textRun', 'reviewer',
    ];

    public function content(): Post|Reel|Story|null
    {
        return match ($this->source_type) {
            'post' => Post::query()->find($this->source_id),
            'reel' => Reel::query()->find($this->source_id),
            'story' => Story::query()->find($this->source_id),
            default => null,
        };
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<MediaModerationRun, $this> */
    public function mediaRun(): BelongsTo
    {
        return $this->belongsTo(MediaModerationRun::class, 'media_run_id');
    }

    /** @return BelongsTo<MediaModerationRun, $this> */
    public function textRun(): BelongsTo
    {
        return $this->belongsTo(MediaModerationRun::class, 'text_run_id');
    }
}
