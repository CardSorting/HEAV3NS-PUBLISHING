<?php

namespace App\Model;

use App\Model\Traits\HasAtomicFinancialUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $submitted_fields
 * @property array<string, mixed>|null $details_snapshot
 */
class PaymentRequest extends Model
{
    use HasAtomicFinancialUpdates;

    /** @return HasMany<ManualPaymentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ManualPaymentDocument::class);
    }

    public const PENDING_STATUS = 'pending';
    public const APPROVED_STATUS = 'approved';
    public const REJECTED_STATUS = 'rejected';
    public const DEPOSIT_TYPE = 'deposit';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'transaction_id', 'status', 'type', 'reason', 'message', 'amount',
        'submitted_fields', 'details_snapshot',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = ['submitted_fields', 'details_snapshot'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'submitted_fields' => 'encrypted:array',
        'details_snapshot' => 'encrypted:array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        // belongsTo(Related::class, foreignKey_on_this_model, ownerKey_on_related)
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }
}
