<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaModerationItem extends Model
{
    public const SOURCE_ATTACHMENT = 'attachment';
    public const SOURCE_PROFILE_AVATAR = 'profile_avatar';
    public const SOURCE_PROFILE_COVER = 'profile_cover';
    public const SOURCE_STREAM_POSTER = 'stream_poster';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_AWAITING_PROVIDER = 'awaiting_provider';
    public const STATUS_WAITING_PROVIDER_CAPACITY = 'waiting_provider_capacity';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_QUOTA_PAUSED = 'quota_paused';
    public const STATUS_ACTION_REQUIRED = 'action_required';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';
    public const DECISION_SAFE = 'safe';
    public const DECISION_REVIEW = 'review';
    public const DECISION_VIOLATION = 'violation';
    public const OUTCOME_UNREVIEWED = 'unreviewed';
    public const OUTCOME_DISMISSED = 'dismissed';
    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_DELETED = 'deleted';
    public const OUTCOME_SUPERSEDED = 'superseded';

    protected $fillable = [
        'run_id',
        'attachment_id',
        'source_type',
        'source_id',
        'source_path',
        'source_disk',
        'owner_user_id',
        'subject_type',
        'subject_id',
        'media_type',
        'provider',
        'model_signature',
        'policy_signature',
        'fingerprint',
        'source_updated_at',
        'reused_from_item_id',
        'scan_status',
        'decision',
        'admin_outcome',
        'max_score',
        'matched_class',
        'matched_at_ms',
        'scores',
        'provider_response',
        'provider_request_id',
        'provider_media_id',
        'provider_processing_mode',
        'provider_progress',
        'provider_submitted_at',
        'provider_last_update_at',
        'provider_poll_attempts',
        'provider_next_poll_at',
        'provider_poll_error',
        'operations',
        'operations_reserved',
        'operation_reservation_date',
        'attempts',
        'error_type',
        'error_code',
        'error_message',
        'retryable',
        'is_cached',
        'queued_at',
        'started_at',
        'scanned_at',
        'failed_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'deleted_by',
        'content_deleted_at',
        'deletion_notes',
    ];

    protected $casts = [
        'scores' => 'array',
        'provider_response' => 'array',
        'max_score' => 'float',
        'retryable' => 'boolean',
        'is_cached' => 'boolean',
        'provider_progress' => 'float',
        'provider_poll_attempts' => 'integer',
        'source_updated_at' => 'datetime',
        'provider_submitted_at' => 'datetime',
        'provider_last_update_at' => 'datetime',
        'provider_next_poll_at' => 'datetime',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'scanned_at' => 'datetime',
        'failed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'content_deleted_at' => 'datetime',
        'operation_reservation_date' => 'date',
    ];

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeWithAvailableSource(Builder $query): Builder
    {
        $activeDisk = (string) (config('filesystems.defaultFilesystemDriver') ?: config('filesystems.default', 'public'));

        return $query->where(function (Builder $query) use ($activeDisk): void {
            $query->where(function (Builder $query): void {
                $query->whereNotNull('media_moderation_items.attachment_id')
                    ->whereHas('attachment', fn (Builder $attachmentQuery) => $attachmentQuery
                        ->whereNotNull('attachments.filename')
                        ->where('attachments.filename', '!=', ''));
            })->orWhere(function (Builder $query) use ($activeDisk): void {
                $query->where('media_moderation_items.source_type', self::SOURCE_PROFILE_AVATAR)
                    ->where('media_moderation_items.source_disk', $activeDisk)
                    ->whereNotNull('media_moderation_items.source_path')
                    ->whereHas('owner', fn (Builder $ownerQuery) => $ownerQuery
                        ->whereColumn('users.avatar', 'media_moderation_items.source_path'));
            })->orWhere(function (Builder $query) use ($activeDisk): void {
                $query->where('media_moderation_items.source_type', self::SOURCE_PROFILE_COVER)
                    ->where('media_moderation_items.source_disk', $activeDisk)
                    ->whereNotNull('media_moderation_items.source_path')
                    ->whereHas('owner', fn (Builder $ownerQuery) => $ownerQuery
                        ->whereColumn('users.cover', 'media_moderation_items.source_path'));
            })->orWhere(function (Builder $query) use ($activeDisk): void {
                $query->where('media_moderation_items.source_type', self::SOURCE_STREAM_POSTER)
                    ->where('media_moderation_items.source_disk', $activeDisk)
                    ->whereNotNull('media_moderation_items.source_path')
                    ->whereExists(function ($streamQuery): void {
                        $streamQuery->selectRaw('1')
                            ->from('streams')
                            ->whereColumn('streams.id', 'media_moderation_items.source_id')
                            ->whereColumn('streams.poster', 'media_moderation_items.source_path');
                    });
            });
        });
    }

    /**
     * @return BelongsTo<MediaModerationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MediaModerationRun::class, 'run_id');
    }

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * @return BelongsTo<MediaModerationItem, $this>
     */
    public function reusedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reused_from_item_id');
    }

    /**
     * @return HasMany<MediaModerationItem, $this>
     */
    public function reusedBy(): HasMany
    {
        return $this->hasMany(self::class, 'reused_from_item_id');
    }

    public function isFlagged(): bool
    {
        return in_array($this->decision, [self::DECISION_REVIEW, self::DECISION_VIOLATION], true);
    }

    public function isProfileAsset(): bool
    {
        return in_array($this->source_type, [
            self::SOURCE_PROFILE_AVATAR,
            self::SOURCE_PROFILE_COVER,
        ], true);
    }

    public function isStreamPoster(): bool
    {
        return $this->source_type === self::SOURCE_STREAM_POSTER;
    }

    public function isManagedAsset(): bool
    {
        return $this->isProfileAsset() || $this->isStreamPoster();
    }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            self::SOURCE_PROFILE_AVATAR => 'Profile avatar',
            self::SOURCE_PROFILE_COVER => 'Profile cover',
            self::SOURCE_STREAM_POSTER => 'Stream poster',
            default => str_replace('_', ' ', (string) ($this->subject_type ?: 'Attachment')),
        };
    }
}
