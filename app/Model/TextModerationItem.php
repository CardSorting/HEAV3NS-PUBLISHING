<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextModerationItem extends Model
{
    public const SOURCE_POST = 'post';
    public const SOURCE_REEL = 'reel';
    public const SOURCE_STORY = 'story';
    public const SOURCE_POST_COMMENT = 'post_comment';
    public const SOURCE_REEL_COMMENT = 'reel_comment';
    public const SOURCE_PROFILE_BIO = 'profile_bio';
    public const SOURCE_STREAM_TITLE = 'stream_title';
    public const SOURCE_STREAM_CHAT = 'stream_chat';
    public const SOURCE_MESSAGE = 'message';
    public const STATUS_QUEUED = MediaModerationItem::STATUS_QUEUED;
    public const STATUS_PROCESSING = MediaModerationItem::STATUS_PROCESSING;
    public const STATUS_RETRYING = MediaModerationItem::STATUS_RETRYING;
    public const STATUS_COMPLETED = MediaModerationItem::STATUS_COMPLETED;
    public const STATUS_QUOTA_PAUSED = MediaModerationItem::STATUS_QUOTA_PAUSED;
    public const STATUS_ACTION_REQUIRED = MediaModerationItem::STATUS_ACTION_REQUIRED;
    public const STATUS_SKIPPED = MediaModerationItem::STATUS_SKIPPED;
    public const STATUS_FAILED = MediaModerationItem::STATUS_FAILED;
    public const DECISION_SAFE = MediaModerationItem::DECISION_SAFE;
    public const DECISION_REVIEW = MediaModerationItem::DECISION_REVIEW;
    public const DECISION_VIOLATION = MediaModerationItem::DECISION_VIOLATION;
    public const OUTCOME_UNREVIEWED = MediaModerationItem::OUTCOME_UNREVIEWED;
    public const OUTCOME_DISMISSED = MediaModerationItem::OUTCOME_DISMISSED;
    public const OUTCOME_CONFIRMED = MediaModerationItem::OUTCOME_CONFIRMED;
    public const OUTCOME_DELETED = MediaModerationItem::OUTCOME_DELETED;
    public const OUTCOME_SUPERSEDED = MediaModerationItem::OUTCOME_SUPERSEDED;

    protected $fillable = [
        'run_id',
        'source_type',
        'source_id',
        'source_field',
        'owner_user_id',
        'subject_type',
        'subject_id',
        'source_snapshot',
        'source_hash',
        'source_updated_at',
        'provider',
        'model_signature',
        'policy_signature',
        'reused_from_item_id',
        'scan_status',
        'decision',
        'admin_outcome',
        'max_score',
        'matched_class',
        'scores',
        'provider_response',
        'provider_request_id',
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
        'source_updated_at' => 'datetime',
        'operation_reservation_date' => 'date',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'scanned_at' => 'datetime',
        'failed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'content_deleted_at' => 'datetime',
    ];

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeWithAvailableSource(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            foreach (self::sourceDefinitions() as $sourceType => $definition) {
                $query->orWhere(function (Builder $query) use ($sourceType, $definition): void {
                    $query->where('text_moderation_items.source_type', $sourceType)
                        ->whereExists(function ($source) use ($definition): void {
                            $source->selectRaw('1')
                                ->from($definition['table'])
                                ->whereColumn($definition['table'].'.id', 'text_moderation_items.source_id')
                                ->whereColumn($definition['table'].'.updated_at', 'text_moderation_items.source_updated_at')
                                ->whereNotNull($definition['table'].'.'.$definition['field'])
                                ->where($definition['table'].'.'.$definition['field'], '!=', '');
                        });
                });
            }
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
     * @return BelongsTo<TextModerationItem, $this>
     */
    public function reusedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reused_from_item_id');
    }

    public function isFlagged(): bool
    {
        return in_array($this->decision, [self::DECISION_REVIEW, self::DECISION_VIOLATION], true);
    }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            self::SOURCE_POST => 'Post text',
            self::SOURCE_REEL => 'Reel caption',
            self::SOURCE_STORY => 'Story text',
            self::SOURCE_POST_COMMENT => 'Post comment',
            self::SOURCE_REEL_COMMENT => 'Reel comment',
            self::SOURCE_PROFILE_BIO => 'Profile bio',
            self::SOURCE_STREAM_TITLE => 'Stream title',
            self::SOURCE_STREAM_CHAT => 'Stream chat',
            self::SOURCE_MESSAGE => 'Private message',
            default => (string) str($this->source_type)->replace('_', ' ')->headline(),
        };
    }

    /**
     * @return array<string, array{table: string, field: string}>
     */
    public static function sourceDefinitions(): array
    {
        return [
            self::SOURCE_POST => ['table' => 'posts', 'field' => 'text'],
            self::SOURCE_REEL => ['table' => 'reels', 'field' => 'caption'],
            self::SOURCE_STORY => ['table' => 'stories', 'field' => 'text'],
            self::SOURCE_POST_COMMENT => ['table' => 'post_comments', 'field' => 'message'],
            self::SOURCE_REEL_COMMENT => ['table' => 'reel_comments', 'field' => 'message'],
            self::SOURCE_PROFILE_BIO => ['table' => 'users', 'field' => 'bio'],
            self::SOURCE_STREAM_TITLE => ['table' => 'streams', 'field' => 'name'],
            self::SOURCE_STREAM_CHAT => ['table' => 'stream_messages', 'field' => 'message'],
            self::SOURCE_MESSAGE => ['table' => 'user_messages', 'field' => 'message'],
        ];
    }
}
