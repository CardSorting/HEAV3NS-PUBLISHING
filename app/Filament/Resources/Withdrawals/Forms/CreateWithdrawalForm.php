<?php

namespace App\Filament\Resources\Withdrawals\Forms;

use App\Filament\Resources\Shared\RequestDocuments;
use App\Model\Withdrawal;
use App\Model\ManualPaymentMethod;
use App\Model\UserPayoutAccount;
use App\Providers\PaymentsServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Services\ManualPayments\ManualPaymentDocuments;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class CreateWithdrawalForm
{
    public static function schema($userId = null): array
    {
        return [
            Section::make(__('admin.resources.withdrawal.sections.summary'))
                ->visible(fn ($record) => filled($record))
                ->dehydrated(false)
                ->columnSpanFull()
                ->columns(['default' => 2, 'lg' => 5])
                ->schema([
                    TextEntry::make('summary_user')
                        ->label(__('admin.resources.withdrawal.fields.user_id'))
                        ->state(fn ($record) => $record?->user?->username)
                        ->placeholder('—')
                        ->wrap()
                        ->columnSpan(['default' => 2, 'lg' => 1]),
                    TextEntry::make('summary_amount')
                        ->label(__('admin.resources.withdrawal.fields.requested_amount'))
                        ->state(fn ($record) => self::formatAmount($record?->amount)),
                    TextEntry::make('summary_fee')
                        ->label(__('admin.resources.withdrawal.fields.fee'))
                        ->state(fn ($record) => self::formatAmount($record?->fee)),
                    TextEntry::make('summary_net')
                        ->label(__('admin.resources.withdrawal.fields.net_payout'))
                        ->state(fn ($record) => self::formatAmount($record?->net_amount))
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold)
                        ->color('primary'),
                    TextEntry::make('summary_status')
                        ->label(__('admin.resources.withdrawal.fields.status'))
                        ->state(fn ($record) => $record?->status)
                        ->formatStateUsing(fn ($state) => __('admin.resources.withdrawal.status_labels.'.strtolower((string) $state)))
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            Withdrawal::APPROVED_STATUS => 'success',
                            Withdrawal::REQUESTED_STATUS => 'warning',
                            Withdrawal::REJECTED_STATUS => 'danger',
                            default => 'gray',
                        }),
                ]),

            Section::make(__('admin.resources.withdrawal.sections.payout_details'))
                ->visible(fn ($record) => filled($record))
                ->dehydrated(false)
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 2])
                        ->schema(fn ($record) => self::payoutDetailsSchema($record)),
                ]),

            RequestDocuments::make(__('admin.resources.withdrawal.sections.documents')),

            Section::make(fn ($record) => $record
                ? __('admin.resources.withdrawal.sections.processing')
                : __('admin.resources.withdrawal.sections.details'))
                ->description(fn ($record) => $record
                    ? __('admin.resources.withdrawal.sections.processing_descr')
                    : __('admin.resources.withdrawal.sections.details_descr'))
                ->columns(2)
                ->schema(self::processingFields($userId))
                ->columnSpanFull(),
        ];
    }

    protected static function processingFields($userId): array
    {
        return [
            Select::make('user_id')
                ->label(__('admin.resources.withdrawal.fields.user_id'))
                ->relationship('user', 'username')
                ->searchable()
                ->default($userId)
                ->disabled(fn ($record) => filled($record))
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::resetPayoutSelection($get, $set))
                ->required()
                ->preload(true),

            Select::make('manual_method_id')
                ->label(__('admin.resources.withdrawal.fields.payment_method'))
                ->options(function (): array {
                    $methods = ManualPaymentMethod::enabledFor(ManualPaymentMethod::WITHDRAWAL);
                    $counts = $methods->countBy('name');

                    return $methods->mapWithKeys(fn ($method) => [$method->id => $method->name
                        .($counts[$method->name] > 1 ? ' (#'.$method->id.')' : '')])->all();
                })
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::resetPayoutSelection($get, $set))
                ->visible(fn ($record) => blank($record))
                ->dehydrated(fn ($record) => blank($record)),

            Select::make('payout_account_id')
                ->label(__('admin.resources.withdrawal.fields.payout_account'))
                ->options(fn (Get $get) => self::availableAccounts($get('user_id'), $get('manual_method_id'))
                    ->mapWithKeys(fn ($account) => [$account->id => $account->display_label
                        .($account->masked_details ? ' · '.$account->masked_details : '')])->all())
                ->disabled(fn (Get $get) => !$get('user_id') || !$get('manual_method_id'))
                ->helperText(fn (Get $get) => $get('user_id') && $get('manual_method_id')
                    && self::availableAccounts($get('user_id'), $get('manual_method_id'))->isEmpty()
                        ? __('admin.resources.withdrawal.helpers.no_payout_accounts') : null)
                ->required()
                ->visible(fn ($record) => blank($record))
                ->dehydrated(fn ($record) => blank($record))
                ->columnSpanFull(),

            Select::make('status')
                ->label(__('admin.resources.withdrawal.fields.status'))
                ->required()
                ->options(self::getTranslatedStatuses())
                ->default(Withdrawal::REQUESTED_STATUS)
                ->visible(fn ($record) => filled($record))
                ->rule(function ($get, $state, $set, $context) {
                    return function ($attribute, $value, $fail) use ($context) {
                        $isCreating = $context === 'create';
                        if ($isCreating && $value !== Withdrawal::REQUESTED_STATUS) {
                            $fail(__('admin.resources.withdrawal.helpers.status_creation_rule'));
                        }
                    };
                }),

            TextInput::make('amount')
                ->label(__('admin.resources.withdrawal.fields.amount'))
                ->numeric()
                ->minValue(fn ($record) => blank($record) ? PaymentsServiceProvider::getWithdrawalMinimumAmount() : null)
                ->maxValue(fn ($record) => blank($record) ? PaymentsServiceProvider::getWithdrawalMaximumAmount() : null)
                ->disabled(fn ($record) => filled($record))
                ->required()
                ->rule(function ($get, $context) {
                    if ($context === 'edit') {
                        return null;
                    }

                    $userId = $get('user_id');
                    $user = \App\Model\User::find($userId);
                    $walletTotal = (float) data_get($user, 'wallet.total', 0);

                    return function ($attribute, $value, $fail) use ($walletTotal) {
                        if ($value > $walletTotal) {
                            $fail(__('admin.resources.withdrawal.helpers.amount_overflow'));
                        }
                    };
                }),

            TextInput::make('fee')
                ->label(__('admin.resources.withdrawal.fields.fee'))
                ->numeric()
                ->disabled()
                ->visible(fn ($record) => blank($record))
                ->helperText(__('admin.resources.withdrawal.helpers.fees_info'))
                ->default(0),

            Grid::make(2)
                ->schema(fn (Get $get, $record) => blank($record)
                    ? self::requestFields(ManualPaymentMethod::query()->whereKey($get('manual_method_id'))
                        ->where('direction', ManualPaymentMethod::WITHDRAWAL)->where('enabled', true)->first()->request_fields ?? [])
                    : [])
                ->visible(fn ($record) => blank($record))
                ->columnSpanFull(),

            Textarea::make('message')
                ->label(fn ($record) => $record?->message_label ?: __('admin.resources.withdrawal.fields.message'))
                ->helperText(fn ($record) => $record ? __('admin.resources.withdrawal.helpers.stored_notes') : null)
                ->visible(fn ($record) => $record && (!$record->details_snapshot || filled($record->message)))
                ->columnSpanFull(),

            TextInput::make('payment_method')->hidden()->disabled()->dehydrated(false),

            TextInput::make('payment_identifier')
                ->label(fn ($record) => $record?->payment_identifier_label ?: __('admin.resources.withdrawal.fields.payment_identifier'))
                ->helperText(function ($record) {
                    if (!$record) {
                        return null;
                    }

                    if (self::shouldLockPaymentIdentifier($record)) {
                        return __('admin.resources.withdrawal.helpers.stored_payout_reference');
                    }

                    return __('admin.resources.withdrawal.helpers.stored_payout_used');
                })
                ->disabled(fn ($record) => self::shouldLockPaymentIdentifier($record))
                ->visible(fn ($record) => $record && (!$record->details_snapshot || filled($record->payment_identifier)))
                ->dehydrated(fn ($record) => !self::shouldLockPaymentIdentifier($record))
                ->maxLength(191)
                ->default(null),

            // Stripe references are displayed in the read-only details section.
            TextInput::make('stripe_payout_id')->hidden()->disabled()->dehydrated(false),
            TextInput::make('stripe_transfer_id')->hidden()->disabled()->dehydrated(false),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, UserPayoutAccount> */
    protected static function availableAccounts($userId, $methodId): \Illuminate\Database\Eloquent\Collection
    {
        return UserPayoutAccount::query()->with('manualMethod')
            ->where('user_id', $userId)->where('manual_method_id', $methodId)->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('id')->get();
    }

    protected static function resetPayoutSelection(Get $get, Set $set): void
    {
        $set('payout_account_id', self::availableAccounts($get('user_id'), $get('manual_method_id'))->first()?->id);
        $set('submitted_fields', []);
    }

    protected static function requestFields(array $definitions): array
    {
        return array_map(function (array $field) {
            $name = 'submitted_fields.'.$field['key'];
            $component = match ($field['type']) {
                'textarea' => Textarea::make($name)->rows(3)->maxLength($field['max_length'])->columnSpanFull(),
                'select' => Select::make($name)->options(array_column($field['options'], 'label', 'value')),
                'email' => TextInput::make($name)->email()->maxLength($field['max_length']),
                'phone' => TextInput::make($name)->tel()->maxLength($field['max_length']),
                'file' => FileUpload::make($name)->multiple()->storeFiles(false)
                    ->disk(ManualPaymentDocuments::DISK)->visibility('private')->previewable(false)
                    ->preventFilePathTampering()
                    ->acceptedFileTypes(collect($field['allowed_extensions'])->flatMap(fn ($extension) => match ($extension) {
                        'jpg', 'jpeg' => ['image/jpeg'],
                        'png' => ['image/png'],
                        'pdf' => ['application/pdf'],
                        // Some valid XLSX files are detected as ZIP; the document service checks the extension and workbook content.
                        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
                        default => throw new \InvalidArgumentException('Unsupported manual payment document format.'),
                    })->unique()->values()->all())
                    ->maxSize(ManualPaymentDocuments::maxSizeKb($field))->maxFiles($field['max_files'])
                    ->columnSpanFull(),
                default => TextInput::make($name)->maxLength($field['max_length']),
            };

            return $component->label($field['label'])->helperText($field['help_text'])->required($field['required']);
        }, $definitions);
    }

    public static function getAvailableStatuses(): array
    {
        return [
            Withdrawal::APPROVED_STATUS,
            Withdrawal::REQUESTED_STATUS,
            Withdrawal::REJECTED_STATUS,
        ];
    }

    public static function getTranslatedStatuses(): array
    {
        return collect(self::getAvailableStatuses())
            ->mapWithKeys(fn ($status) => [
                $status => __('admin.resources.withdrawal.status_labels.'.$status),
            ])->toArray();
    }

    protected static function shouldLockPaymentIdentifier(?Withdrawal $record): bool
    {
        if (!$record) {
            return false;
        }

        $methodKey = PaymentsServiceProvider::getWithdrawalMethodKey($record->payout_method_key ?: $record->payment_method);

        return $methodKey === PaymentsServiceProvider::WITHDRAWAL_METHOD_BANK_TRANSFER
            || $methodKey === PaymentsServiceProvider::WITHDRAWAL_METHOD_CUSTOM
            || empty($record->payout_snapshot);
    }

    protected static function payoutDetailsSchema(?Withdrawal $record): array
    {
        $entries = [];
        $items = $record->payout_snapshot_items ?? [];
        if ($record && !$record->details_snapshot && filled($record->payment_method)
            && !collect($items)->contains(fn ($item) => in_array($item['label'], [__('Method'), __('admin.resources.withdrawal.fields.method')], true))) {
            array_unshift($items, ['label' => __('admin.resources.withdrawal.fields.method'), 'value' => $record->payment_method]);
        }
        $requestItems = $record->details_snapshot['request_items'] ?? [];
        foreach ($items as $index => $item) {
            $value = (string) $item['value'];
            $fullWidth = mb_strlen($value) > 24 || str_contains($value, "\n")
                || in_array($item, $requestItems, true) || $item['label'] === $record->message_label;

            $entries[] = TextEntry::make('payout_detail_'.$index)
                ->label($item['label'])
                ->state($value)
                ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state))))
                ->wrap()
                ->columnSpan(['default' => $fullWidth ? 'full' : 1]);
        }

        if ($record && PaymentsServiceProvider::isStripeConnectWithdrawalMethod($record->payout_method_key ?: $record->payment_method)) {
            foreach (['stripe_payout_id', 'stripe_transfer_id'] as $field) {
                $entries[] = TextEntry::make('payout_reference_'.$field)
                    ->label(__('admin.resources.withdrawal.fields.'.$field))
                    ->state($record->{$field})
                    ->placeholder('—')
                    ->wrap();
            }
        }

        return $entries ?: [
            TextEntry::make('payout_details_empty')
                ->hiddenLabel()
                ->state(__('admin.resources.withdrawal.helpers.payout_details_empty'))
                ->color('gray')
                ->columnSpanFull(),
        ];
    }

    protected static function formatAmount($amount): string
    {
        return SettingsServiceProvider::getWebsiteFormattedAmount(number_format((float) $amount, 2, '.', ''));
    }
}
