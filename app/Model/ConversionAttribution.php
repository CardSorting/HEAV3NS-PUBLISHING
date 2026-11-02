<?php

namespace App\Model;

use App\Model\Traits\HasSafeGuardedColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $user_id
 * @property string|null $token_hash
 * @property string|null $config_hash
 * @property \Carbon\CarbonInterface|null $consented_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $revoked_at
 */
class ConversionAttribution extends Model
{
    use HasSafeGuardedColumns;

    protected $table = 'conversion_attributions';

    protected $guarded = ['id'];

    protected $hidden = ['click_id', 'token_hash'];

    protected $casts = [
        'click_id' => 'encrypted',
        'consented_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permitsReporting(): bool
    {
        return $this->consented_at !== null && $this->revoked_at === null
            && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
