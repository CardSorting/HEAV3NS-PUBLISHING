<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const ACTIVE_STATUS = 'completed';
    public const SUSPENDED_STATUS = 'suspended';
    public const CANCELED_STATUS = 'canceled';
    public const EXPIRED_STATUS = 'expired';
    public const PENDING_STATUS = 'pending';

    // Renewal failed
    public const FAILED_STATUS = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type', 'provider', 'status',
        'sender_user_id', 'recipient_user_id', 'stripe_subscription_id', 'paypal_agreement_id', 'paypal_plan_id',
        'amount', 'expires_at', 'canceled_at', 'ccbill_subscription_id', 'verotel_sale_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [

    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'canceled_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

    ];

    /**
     * Casts the dates to carbon objects.
     */
//    Deprecated in L10
//    protected $dates = ['expires_at', 'updated_at', 'deleted_at'];

    /*
     * Relationships
     */

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
