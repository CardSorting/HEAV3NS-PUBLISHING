<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBan extends Model
{
    protected $fillable = ['user_id', 'banned_by', 'reason', 'policy_reference', 'expires_at', 'lifted_at', 'lifted_by'];

    protected $casts = ['expires_at' => 'datetime', 'lifted_at' => 'datetime'];

    /** @param Builder<UserBan> $query
     * @return Builder<UserBan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function getStatusAttribute(): string
    {
        if ($this->lifted_at) {
            return 'lifted';
        }

        return $this->expires_at && !$this->expires_at->isFuture() ? 'expired' : 'active';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }
}
