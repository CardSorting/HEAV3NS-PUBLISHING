<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorEnrollment extends Model
{
    public const PENDING_STATUS = 'pending';
    public const APPROVED_STATUS = 'approved';
    public const REJECTED_STATUS = 'rejected';
    public const REVOKED_STATUS = 'revoked';

    public static function statusLabels(): array
    {
        return [
            self::PENDING_STATUS => __('Pending'),
            self::APPROVED_STATUS => __('Approved'),
            self::REJECTED_STATUS => __('Rejected'),
            self::REVOKED_STATUS => __('Access removed'),
        ];
    }

    protected $guarded = ['*'];

    protected $casts = [
        'applied_at' => 'datetime',
        'agreement_accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'application_version' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
