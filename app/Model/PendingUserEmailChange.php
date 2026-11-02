<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingUserEmailChange extends Model
{
    protected $fillable = [
        'user_id',
        'audit_id',
        'current_email',
        'new_email',
        'token_hash',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<UserEmailChangeRequest, $this>
     */
    public function auditRequest(): BelongsTo
    {
        return $this->belongsTo(UserEmailChangeRequest::class, 'audit_id');
    }
}
