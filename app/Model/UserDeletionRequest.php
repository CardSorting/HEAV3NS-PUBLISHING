<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeletionRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_BLOCKED = 'blocked';
    public const MODE_MANUAL_REVIEW = 'manual_review';
    public const MODE_COOLDOWN_AUTO = 'cooldown_auto';
    public const MODE_MANUAL_REVIEW_WITH_COOLDOWN = 'manual_review_with_cooldown';
    public const MODES = [
        self::MODE_MANUAL_REVIEW,
        self::MODE_COOLDOWN_AUTO,
        self::MODE_MANUAL_REVIEW_WITH_COOLDOWN,
    ];
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_BLOCKED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'requested_name',
        'requested_email',
        'requested_username',
        'status',
        'mode',
        'reason',
        'admin_notes',
        'rejection_reason',
        'requested_at',
        'eligible_for_deletion_at',
        'reviewed_by',
        'reviewed_at',
        'canceled_at',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'requested_at' => 'datetime',
        'eligible_for_deletion_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function modeLabels(): array
    {
        return [
            self::MODE_MANUAL_REVIEW => __('admin.resources.user_deletion_request.mode_labels.manual_review'),
            self::MODE_COOLDOWN_AUTO => __('admin.resources.user_deletion_request.mode_labels.cooldown_auto'),
            self::MODE_MANUAL_REVIEW_WITH_COOLDOWN => __('admin.resources.user_deletion_request.mode_labels.manual_review_with_cooldown'),
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => __('admin.resources.user_deletion_request.status_labels.pending'),
            self::STATUS_APPROVED => __('admin.resources.user_deletion_request.status_labels.approved'),
            self::STATUS_REJECTED => __('admin.resources.user_deletion_request.status_labels.rejected'),
            self::STATUS_CANCELED => __('admin.resources.user_deletion_request.status_labels.canceled'),
            self::STATUS_COMPLETED => __('admin.resources.user_deletion_request.status_labels.completed'),
            self::STATUS_BLOCKED => __('admin.resources.user_deletion_request.status_labels.blocked'),
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
