<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseForm extends Model
{
    public const PENDING_STATUS = 'pending';
    public const APPROVED_STATUS = 'approved';
    public const REJECTED_STATUS = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'notes',
        'files',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'files' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
