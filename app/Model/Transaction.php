<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property \Carbon\CarbonInterface|null $purchase_completed_at
 * @property bool $conversion_checkout
 * @property int|null $conversion_attribution_id
 * @property int|null $sender_user_id
 * @property int|null $manual_method_id
 */
class Transaction extends Model
{
    // Statuses
    public const PENDING_STATUS = 'pending';
    public const CANCELED_STATUS = 'canceled';
    public const APPROVED_STATUS = 'approved';
    public const DECLINED_STATUS = 'declined';
    public const REFUNDED_STATUS = 'refunded';
    public const INITIATED_STATUS = 'initiated';
    public const PARTIALLY_PAID_STATUS = 'partially-paid';

    // Types
    public const TIP_TYPE = 'tip';
    public const CHAT_TIP_TYPE = 'chat-tip';
    public const POST_UNLOCK = 'post-unlock';
    public const MESSAGE_UNLOCK = 'message-unlock';
    public const DEPOSIT_TYPE = 'deposit';
    public const WITHDRAWAL_TYPE = 'withdrawal';
    public const ONE_MONTH_SUBSCRIPTION = 'one-month-subscription';
    public const THREE_MONTHS_SUBSCRIPTION = 'three-months-subscription';
    public const SIX_MONTHS_SUBSCRIPTION = 'six-months-subscription';
    public const YEARLY_SUBSCRIPTION = 'yearly-subscription';
    public const SUBSCRIPTION_RENEWAL = 'subscription-renewal';
    public const STREAM_ACCESS = 'stream-access';

    // Providers
    public const PAYPAL_PROVIDER = 'paypal';
    public const STRIPE_PROVIDER = 'stripe';
    public const MANUAL_PROVIDER = 'manual';
    public const CREDIT_PROVIDER = 'credit';
    public const YOOKASSA_PROVIDER = 'yookassa';
    public const MOLLIE_PROVIDER = 'mollie';
    public const FLUTTERWAVE_PROVIDER = 'flutterwave';
    public const COINGATE_PROVIDER = 'coingate';
    public const XENDIT_PROVIDER = 'xendit';
    public const PADDLE_PROVIDER = 'paddle';
    public const CRYPTOCOM_PROVIDER = 'cryptocom';
    public const CCBILL_PROVIDER = 'ccbill';
    public const NOWPAYMENTS_PROVIDER = 'nowpayments';
    public const PAYSTACK_PROVIDER = 'paystack';
    public const OXXO_PROVIDER = 'oxxo';
    public const MERCADO_PROVIDER = 'mercado';
    public const VEROTEL_PROVIDER = 'verotel';
    public const RAZORPAY_PROVIDER = 'razorpay';
    public const NOWPAYMENTS_API_BASE_PATH = 'https://api.nowpayments.io/v1/';
    public const ALLOWED_PAYMENT_PROVIDERS = [
        self::NOWPAYMENTS_PROVIDER,
        self::PAYPAL_PROVIDER,
        self::STRIPE_PROVIDER,
        self::YOOKASSA_PROVIDER,
        self::MOLLIE_PROVIDER,
        self::FLUTTERWAVE_PROVIDER,
        self::COINGATE_PROVIDER,
        self::XENDIT_PROVIDER,
        self::PADDLE_PROVIDER,
        self::CRYPTOCOM_PROVIDER,
        self::CCBILL_PROVIDER,
        self::PAYSTACK_PROVIDER,
        self::OXXO_PROVIDER,
        self::MERCADO_PROVIDER,
        self::VEROTEL_PROVIDER,
        self::RAZORPAY_PROVIDER,
    ];
    public const NEW_PAYMENT_PROVIDERS = [
        self::NOWPAYMENTS_PROVIDER,
        self::PAYPAL_PROVIDER,
        self::STRIPE_PROVIDER,
        self::YOOKASSA_PROVIDER,
        self::MOLLIE_PROVIDER,
        self::FLUTTERWAVE_PROVIDER,
        self::COINGATE_PROVIDER,
        self::XENDIT_PROVIDER,
        self::PADDLE_PROVIDER,
        self::CRYPTOCOM_PROVIDER,
        self::CCBILL_PROVIDER,
        self::PAYSTACK_PROVIDER,
        self::OXXO_PROVIDER,
        self::MERCADO_PROVIDER,
        self::VEROTEL_PROVIDER,
        self::RAZORPAY_PROVIDER,
        self::MANUAL_PROVIDER,
        self::CREDIT_PROVIDER,
    ];
    public const PENDING_PAYMENT_PROCESSORS = [
        self::YOOKASSA_PROVIDER,
        self::MOLLIE_PROVIDER,
        self::FLUTTERWAVE_PROVIDER,
        self::COINGATE_PROVIDER,
        self::XENDIT_PROVIDER,
        self::PADDLE_PROVIDER,
        self::CRYPTOCOM_PROVIDER,
        self::NOWPAYMENTS_PROVIDER,
        self::CCBILL_PROVIDER,
        self::OXXO_PROVIDER,
        self::VEROTEL_PROVIDER,
    ];
    public const CCBILL_FLEX_FORM_BASE_PATH = 'https://api.ccbill.com/wap-frontflex/flexforms/';
    public const CCBILL_CANCEL_SUBSCRIPTION_BASE_PATH = 'https://datalink.ccbill.com/utils/subscriptionManagement.cgi';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sender_user_id', 'recipient_user_id', 'subscription_id', 'stripe_transaction_id', 'paypal_payer_id', 'post_id',
        'paypal_transaction_id', 'status', 'type', 'amount', 'payment_provider', 'paypal_transaction_token', 'currency', 'taxes',
        'coinbase_charge_id', 'coinbase_transaction_token', 'ccbill_payment_token', 'ccbill_transaction_id', 'nowpayments_payment_id',
        'nowpayments_order_id', 'stream_id', 'ccbill_subscription_id', 'user_message_id', 'paystack_transaction_token',
        'verotel_payment_token', 'verotel_sale_id', 'yookassa_payment_id', 'yookassa_payment_token', 'xendit_payment_id',
        'xendit_payment_token', 'mollie_payment_id', 'mollie_payment_token', 'flutterwave_payment_id',
        'flutterwave_payment_token', 'coingate_order_id', 'coingate_payment_token', 'paddle_transaction_id',
        'paddle_transaction_token', 'cryptocom_payment_id', 'cryptocom_payment_token',
        'manual_method_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'taxes' => 'array',
        'conversion_checkout' => 'boolean',
        'purchase_completed_at' => 'datetime',
    ];

    public function getDecodedTaxesAttribute(): ?array
    {
        $raw = $this->attributes['taxes'] ?? null;

        if ($raw === null) {
            return null;
        }

        // legacy format: JSON string
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        // new format: already cast to array
        if (is_array($this->taxes)) {
            return $this->taxes;
        }

        return null;
    }

    /*
     * Relationships
     */

    /**
     * @return BelongsTo<ManualPaymentMethod, $this>
     */
    public function manualMethod(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentMethod::class, 'manual_method_id');
    }

    /**
     * @return HasOne<PaymentRequest, $this>
     */
    public function paymentRequest(): HasOne
    {
        return $this->hasOne(PaymentRequest::class, 'transaction_id')->where('type', PaymentRequest::DEPOSIT_TYPE);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }

    /**
     * @return BelongsTo<UserMessage, $this>
     */
    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(UserMessage::class, 'user_message_id');
    }
}
