<?php

namespace App\Filament\Resources\Subscriptions\Forms;

use App\Model\Subscription;
use App\Model\Transaction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CreateSubscriptionForm
{
    public static function schema($senderUserId = null): array
    {
        return [
            Section::make(__('admin.resources.subscription.sections.user_info'))
                ->schema([
                    Select::make('sender_user_id')
                        ->label(__('admin.resources.subscription.fields.sender_user_id'))
                        ->relationship('subscriber', 'username')
                        ->searchable()
                        ->default($senderUserId)
                        ->required()
                        ->preload(true),
                    Select::make('recipient_user_id')
                        ->label(__('admin.resources.subscription.fields.recipient_user_id'))
                        ->relationship('creator', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),
                ])
                ->columns(2),

            Section::make(__('admin.resources.subscription.sections.subscription_details'))
                ->schema([
                    Select::make('type')
                        ->label(__('admin.resources.subscription.fields.type'))
                        ->required()
                        ->options(static::getSubscriptionTypes())
                        ->default(Transaction::ONE_MONTH_SUBSCRIPTION),

                    Select::make('provider')
                        ->label(__('admin.resources.subscription.fields.provider'))
                        ->required()
                        ->options([
                            Transaction::MANUAL_PROVIDER => 'Manual',
                            Transaction::MERCADO_PROVIDER => 'Mercado Pago',
                            Transaction::STRIPE_PROVIDER => 'Stripe',
                            Transaction::CCBILL_PROVIDER => 'CCBill',
                            Transaction::PAYPAL_PROVIDER => 'PayPal',
                            Transaction::OXXO_PROVIDER => 'OXXO',
                            Transaction::PAYSTACK_PROVIDER => 'Paystack',
                            Transaction::CREDIT_PROVIDER => 'Credit',
                            Transaction::NOWPAYMENTS_PROVIDER => 'NowPayments',
                        ])
                        ->default(Transaction::MANUAL_PROVIDER),

                    Select::make('status')
                        ->label(__('admin.resources.subscription.fields.status'))
                        ->required()
                        ->options(static::getSubscriptionStatus()),

                    TextInput::make('amount')
                        ->label(__('admin.resources.subscription.fields.amount'))
                        ->numeric()
                        ->required()
                        ->prefix('$'),
                ])
                ->columns(2),

            Section::make(__('admin.resources.subscription.sections.platform_identifiers'))
                ->schema([
                    TextInput::make('paypal_agreement_id')->label(__('admin.resources.subscription.fields.paypal_agreement_id'))->maxLength(191),
                    TextInput::make('paypal_plan_id')->label(__('admin.resources.subscription.fields.paypal_plan_id'))->maxLength(191),
                    TextInput::make('stripe_subscription_id')->label(__('admin.resources.subscription.fields.stripe_subscription_id'))->maxLength(191),
                    TextInput::make('ccbill_subscription_id')->label(__('admin.resources.subscription.fields.ccbill_subscription_id'))->maxLength(191),
                    TextInput::make('verotel_sale_id')->label(__('admin.resources.subscription.fields.verotel_sale_id'))->maxLength(191),
                ])
                ->columns(2),

            Section::make(__('admin.resources.subscription.sections.timestamps'))
                ->schema([
                    DateTimePicker::make('expires_at')->label(__('admin.resources.subscription.fields.expires_at')),
                    DateTimePicker::make('canceled_at')->label(__('admin.resources.subscription.fields.canceled_at')),
                ])
                ->columns(2),
        ];
    }

    public static function getAvailableStatuses(): array
    {
        return [
            Subscription::ACTIVE_STATUS,
            Subscription::CANCELED_STATUS,
            Subscription::SUSPENDED_STATUS,
            Subscription::EXPIRED_STATUS,
            Subscription::FAILED_STATUS,
        ];
    }

    public static function getAvailableTypes(): array
    {
        return [
            Transaction::ONE_MONTH_SUBSCRIPTION,
            Transaction::THREE_MONTHS_SUBSCRIPTION,
            Transaction::SIX_MONTHS_SUBSCRIPTION,
            Transaction::YEARLY_SUBSCRIPTION,
        ];
    }

    public static function getSubscriptionStatus(): array
    {
        return collect(self::getAvailableStatuses())
            ->mapWithKeys(fn ($status) => [
                $status => __('admin.resources.subscription.status_labels.'.str_replace('-', '_', $status)),
            ])
            ->toArray();
    }

    public static function getSubscriptionTypes(): array
    {
        return collect(self::getAvailableTypes())
            ->mapWithKeys(fn ($type) => [
                $type => __('admin.resources.subscription.type_labels.'.str_replace('-', '_', $type)),
            ])
            ->toArray();
    }
}
