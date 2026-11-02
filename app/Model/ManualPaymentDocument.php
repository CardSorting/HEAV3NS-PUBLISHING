<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path', 'original_name'];

    protected $casts = ['original_name' => 'encrypted', 'submitted_at' => 'datetime', 'size' => 'integer'];

    public function canPreview(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'application/pdf'], true);
    }

    /** @return BelongsTo<PaymentRequest, $this> */
    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    /** @return BelongsTo<Withdrawal, $this> */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
