<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCancellationRequest extends Model
{
    public const PENDING = 'pending';
    public const AWAITING_SUBSCRIPTION = 'awaiting_subscription';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const REVIEW = 'review';

    protected $fillable = [
        'user_ban_id', 'subscription_id', 'transaction_id', 'status', 'attempts',
        'attempted_at', 'next_attempt_at', 'completed_at', 'confirmed_by', 'failure_code',
    ];

    protected $casts = [
        'attempts' => 'integer', 'attempted_at' => 'datetime',
        'next_attempt_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<UserBan, $this> */
    public function ban(): BelongsTo
    {
        return $this->belongsTo(UserBan::class, 'user_ban_id');
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
