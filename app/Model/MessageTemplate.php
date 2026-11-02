<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    public const TRIGGER_FOLLOWER_CREATED = 'follower_created';
    public const TRIGGER_SUBSCRIPTION_CREATED = 'subscription_created';
    public const TRIGGER_TYPES = [
        self::TRIGGER_FOLLOWER_CREATED,
        self::TRIGGER_SUBSCRIPTION_CREATED,
    ];

    protected $fillable = [
        'user_id',
        'trigger_type',
        'enabled',
        'message',
        'price',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    use Traits\HasAttachmentPlacements;
}
