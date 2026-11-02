<?php

namespace App\Filament\Resources\Transactions\Forms;

use App\Model\Transaction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CreateTransactionForm
{
    public static function getAvailableStatuses(): array
    {
        return [
            Transaction::PENDING_STATUS,
            Transaction::REFUNDED_STATUS,
            Transaction::PARTIALLY_PAID_STATUS,
            Transaction::DECLINED_STATUS,
            Transaction::INITIATED_STATUS,
            Transaction::CANCELED_STATUS,
            Transaction::APPROVED_STATUS,
        ];
    }

    public static function getAvailableTypes(): array
    {
        return [
            Transaction::TIP_TYPE,
            Transaction::DEPOSIT_TYPE,
            Transaction::WITHDRAWAL_TYPE,
            Transaction::CHAT_TIP_TYPE,
            Transaction::STREAM_ACCESS,
            Transaction::MESSAGE_UNLOCK,
            Transaction::POST_UNLOCK,
            Transaction::ONE_MONTH_SUBSCRIPTION,
            Transaction::THREE_MONTHS_SUBSCRIPTION,
            Transaction::SIX_MONTHS_SUBSCRIPTION,
            Transaction::YEARLY_SUBSCRIPTION,
        ];
    }

    public static function getTransactionStatus()
    {
        return collect(self::getAvailableStatuses())
            ->mapWithKeys(fn ($status) => [$status => __('admin.resources.transaction.status_labels.'.str_replace('-', '_', $status))])
            ->toArray();
    }

    public static function getTransactionTypes()
    {
        return collect(self::getAvailableTypes())
            ->mapWithKeys(fn ($type) => [$type => __('admin.resources.transaction.type_labels.'.str_replace('-', '_', $type))])
            ->toArray();
    }

    public static function getTransactionProviders() {
        return [
            Transaction::MANUAL_PROVIDER => ucfirst(Transaction::MANUAL_PROVIDER),
            Transaction::MERCADO_PROVIDER => ucfirst(Transaction::MERCADO_PROVIDER),
            Transaction::STRIPE_PROVIDER => ucfirst(Transaction::STRIPE_PROVIDER),
            Transaction::CCBILL_PROVIDER => ucfirst(Transaction::CCBILL_PROVIDER),
            Transaction::PAYPAL_PROVIDER => ucfirst(Transaction::PAYPAL_PROVIDER),
            Transaction::OXXO_PROVIDER => ucfirst(Transaction::OXXO_PROVIDER),
            Transaction::PAYSTACK_PROVIDER => ucfirst(Transaction::PAYSTACK_PROVIDER),
            Transaction::YOOKASSA_PROVIDER => ucfirst(Transaction::YOOKASSA_PROVIDER),
            Transaction::XENDIT_PROVIDER => ucfirst(Transaction::XENDIT_PROVIDER),
            Transaction::PADDLE_PROVIDER => ucfirst(Transaction::PADDLE_PROVIDER),
            Transaction::CRYPTOCOM_PROVIDER => ucfirst(Transaction::CRYPTOCOM_PROVIDER),
            Transaction::CREDIT_PROVIDER => ucfirst(Transaction::CREDIT_PROVIDER),
            Transaction::NOWPAYMENTS_PROVIDER => ucfirst(Transaction::NOWPAYMENTS_PROVIDER),
        ];
    }

    public static function schema(
        $postId = null,
        $subscriptionId = null,
        $streamId = null,
        $userMessageId = null,
        $invoiceId = null
    ): array {
        return [
            Section::make(__('admin.resources.transaction.sections.participants'))
                ->description(__('admin.resources.transaction.sections.participants_descr'))
                ->schema([
                    Select::make('sender_user_id')
                        ->label(__('admin.resources.transaction.fields.sender_user_id'))
                        ->relationship('sender', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),

                    Select::make('recipient_user_id')
                        ->label(__('admin.resources.transaction.fields.recipient_user_id'))
                        ->relationship('receiver', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),
                ])
                ->columns(2),

            Section::make(__('admin.resources.transaction.sections.details'))
                ->description(__('admin.resources.transaction.sections.details_descr'))
                ->schema([
                    Select::make('status')
                        ->label(__('admin.resources.transaction.fields.status'))
                        ->required()
                        ->options(self::getTransactionStatus())
                        ->default(Transaction::INITIATED_STATUS),

                    Select::make('type')
                        ->label(__('admin.resources.transaction.fields.type'))
                        ->required()
                        ->options(self::getTransactionTypes()),

                    Select::make('payment_provider')
                        ->label(__('admin.resources.transaction.fields.payment_provider'))
                        ->required()
                        ->options(self::getTransactionProviders())
                        ->default(Transaction::MANUAL_PROVIDER),

                    TextInput::make('currency')
                        ->label(__('admin.resources.transaction.fields.currency'))
                        ->required()
                        ->maxLength(191),

                    TextInput::make('amount')
                        ->label(__('admin.resources.transaction.fields.amount'))
                        ->numeric()
                        ->prefix('$')
                        ->required(),

                    Textarea::make('taxes')
                        ->label(__('admin.resources.transaction.fields.taxes'))
                        ->placeholder(__('admin.resources.transaction.helpers.taxes_placeholder'))
                        ->columnSpanFull()
                        ->helperText(__('admin.resources.transaction.helpers.taxes'))
                        // When loading the record -> show JSON string
                        ->formatStateUsing(function ($state) {
                            if ($state === null || $state === '') {
                                return null;
                            }

                            // If it's already an array (because of casts), pretty-print it
                            if (is_array($state)) {
                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            }

                            // If it's a JSON string already, try to pretty-print it too
                            if (is_string($state)) {
                                $decoded = json_decode($state, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                }
                            }

                            // Fallback (string/number/etc.)
                            return (string) $state;
                        })
                        // When saving -> parse JSON back to array so Eloquent cast stores proper JSON
                        ->dehydrateStateUsing(function ($state) {
                            if ($state === null || trim($state) === '') {
                                return null;
                            }

                            // If user pasted JSON, store as array
                            $decoded = json_decode($state, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                return $decoded;
                            }

                            // If it's not valid JSON, keep as-is (or return null / throw validation)
                            return $state;
                        })
                        ->rows(6)
                        ->rule('json'),
                ])
                ->columns(2),

            Section::make(__('admin.resources.transaction.sections.related'))
                ->description(__('admin.resources.transaction.sections.related_descr'))
                ->schema([
                    Select::make('subscription_id')
                        ->label(__('admin.resources.transaction.fields.subscription_id'))
                        ->relationship('subscription', 'id')
                        ->searchable()
                        ->default($subscriptionId)
                        ->preload(),

                    Select::make('post_id')
                        ->label(__('admin.resources.transaction.fields.post_id'))
                        ->relationship('post', 'id')
                        ->searchable()
                        ->default($postId)
                        ->preload(),

                    Select::make('stream_id')
                        ->label(__('admin.resources.transaction.fields.stream_id'))
                        ->relationship('stream', 'id')
                        ->searchable()
                        ->default($streamId)
                        ->preload(),

                    Select::make('invoice_id')
                        ->label(__('admin.resources.transaction.fields.invoice_id'))
                        ->relationship('invoice', 'id')
                        ->searchable()
                        ->default($invoiceId)
                        ->preload(),

                    Select::make('user_message_id')
                        ->label(__('admin.resources.transaction.fields.user_message_id'))
                        ->relationship('userMessage', 'id')
                        ->searchable()
                        ->default($userMessageId)
                        ->preload(),
                ])
                ->columns(2),

            Section::make(__('admin.resources.transaction.sections.provider_info'))
                ->description(__('admin.resources.transaction.sections.provider_info_descr'))
                ->schema([
                    TextInput::make('paypal_payer_id')->label(__('admin.resources.transaction.fields.paypal_payer_id'))->maxLength(191),
                    TextInput::make('paypal_transaction_id')->label(__('admin.resources.transaction.fields.paypal_transaction_id'))->maxLength(191),
                    TextInput::make('paypal_transaction_token')->label(__('admin.resources.transaction.fields.paypal_transaction_token'))->maxLength(191),

                    TextInput::make('stripe_transaction_id')->label(__('admin.resources.transaction.fields.stripe_transaction_id'))->maxLength(191),
                    TextInput::make('stripe_session_id')->label(__('admin.resources.transaction.fields.stripe_session_id'))->maxLength(191),

                    TextInput::make('coinbase_charge_id')->label(__('admin.resources.transaction.fields.coinbase_charge_id'))->maxLength(191),
                    TextInput::make('coinbase_transaction_token')->label(__('admin.resources.transaction.fields.coinbase_transaction_token'))->maxLength(191),

                    TextInput::make('nowpayments_payment_id')->label(__('admin.resources.transaction.fields.nowpayments_payment_id'))->maxLength(191),
                    TextInput::make('nowpayments_order_id')->label(__('admin.resources.transaction.fields.nowpayments_order_id'))->maxLength(191),

                    TextInput::make('ccbill_payment_token')->label(__('admin.resources.transaction.fields.ccbill_transaction_token'))->maxLength(191),
                    TextInput::make('ccbill_transaction_id')->label(__('admin.resources.transaction.fields.ccbill_transaction_id'))->maxLength(191),
                    TextInput::make('ccbill_subscription_id')->label(__('admin.resources.transaction.fields.ccbill_subscription_id'))->maxLength(191),

                    TextInput::make('verotel_payment_token')->label(__('admin.resources.transaction.fields.verotel_payment_token'))->maxLength(191),
                    TextInput::make('verotel_sale_id')->label(__('admin.resources.transaction.fields.verotel_sale_id'))->maxLength(191),

                    TextInput::make('paystack_payment_token')->label(__('admin.resources.transaction.fields.paystack_payment_token'))->maxLength(191),

                    TextInput::make('mercado_payment_token')->label(__('admin.resources.transaction.fields.mercado_payment_token'))->maxLength(191),
                    TextInput::make('mercado_payment_id')->label(__('admin.resources.transaction.fields.mercado_payment_id'))->maxLength(191),

                    TextInput::make('yookassa_payment_id')->label(__('admin.resources.transaction.fields.yookassa_payment_id'))->maxLength(191),
                    TextInput::make('yookassa_payment_token')->label(__('admin.resources.transaction.fields.yookassa_payment_token'))->maxLength(191),

                    TextInput::make('mollie_payment_id')->label(__('admin.resources.transaction.fields.mollie_payment_id'))->maxLength(191),
                    TextInput::make('mollie_payment_token')->label(__('admin.resources.transaction.fields.mollie_payment_token'))->maxLength(191),

                    TextInput::make('flutterwave_payment_id')->label(__('admin.resources.transaction.fields.flutterwave_payment_id'))->maxLength(191),
                    TextInput::make('flutterwave_payment_token')->label(__('admin.resources.transaction.fields.flutterwave_payment_token'))->maxLength(191),

                    TextInput::make('coingate_order_id')->label(__('admin.resources.transaction.fields.coingate_order_id'))->maxLength(191),
                    TextInput::make('coingate_payment_token')->label(__('admin.resources.transaction.fields.coingate_payment_token'))->maxLength(191),

                    TextInput::make('xendit_payment_id')->label(__('admin.resources.transaction.fields.xendit_payment_id'))->maxLength(191),
                    TextInput::make('xendit_payment_token')->label(__('admin.resources.transaction.fields.xendit_payment_token'))->maxLength(191),

                    TextInput::make('paddle_transaction_id')->label(__('admin.resources.transaction.fields.paddle_transaction_id'))->maxLength(191),
                    TextInput::make('paddle_transaction_token')->label(__('admin.resources.transaction.fields.paddle_transaction_token'))->maxLength(191),

                    TextInput::make('cryptocom_payment_id')->label(__('admin.resources.transaction.fields.cryptocom_payment_id'))->maxLength(191),
                    TextInput::make('cryptocom_payment_token')->label(__('admin.resources.transaction.fields.cryptocom_payment_token'))->maxLength(191),
                ])
                ->columns(2),
        ];
    }
}
