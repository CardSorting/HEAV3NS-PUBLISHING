<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Forms\CreateTransactionForm;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\ViewTransaction;
use App\Filament\Resources\Transactions\Widgets\TransactionStats;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Transaction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class TransactionResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Transaction::class;

    protected static UnitEnum|string|null $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.transaction.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.transaction.plural');
    }

    public static function getWidgets(): array
    {
        return [
            TransactionStats::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema(CreateTransactionForm::schema()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender.username')
                    ->label(__('admin.resources.transaction.fields.sender_user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receiver.username')
                    ->label(__('admin.resources.transaction.fields.receiver_user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.resources.transaction.fields.amount'))
                    ->numeric()
                    ->money(getSetting('payments.currency_code'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->default(getSetting('payments.currency_code'))
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(),
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.transaction.fields.status'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('admin.resources.transaction.status_labels.'.str_replace('-', '_', $state)))
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'initiated', 'partially-paid' => 'gray',
                        'declined', 'canceled' => 'danger',
                        'refunded' => 'info',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.transaction.fields.type'))
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => __('admin.resources.transaction.type_labels.'.str_replace('-', '_', $state))),

                Tables\Columns\TextColumn::make('payment_provider')
                    ->label(__('admin.resources.transaction.fields.payment_provider'))
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([

                QueryBuilder::make()
                    ->constraints([

                        SelectConstraint::make('status')
                            ->label(__('admin.resources.transaction.fields.status'))
                            ->options(
                                collect(CreateTransactionForm::getAvailableStatuses())
                                    ->mapWithKeys(fn ($status) => [
                                        $status => __('admin.resources.transaction.status_labels.'.str_replace('-', '_', $status)),
                                    ])->toArray()
                            ),

                        SelectConstraint::make('type')
                            ->label(__('admin.resources.transaction.fields.type'))
                            ->options(
                                collect(CreateTransactionForm::getAvailableTypes())
                                    ->mapWithKeys(fn ($type) => [
                                        $type => __('admin.resources.transaction.type_labels.'.str_replace('-', '_', $type)),
                                    ])->toArray()
                            ),
                        TextConstraint::make('sender.username')->label(__('admin.resources.transaction.fields.sender_user_id')),
                        TextConstraint::make('receiver.username')->label(__('admin.resources.transaction.fields.receiver_user_id')),
                        NumberConstraint::make('amount')
                            ->label(__('admin.resources.transaction.fields.amount'))
                            ->icon('heroicon-m-currency-dollar'),
                        TextConstraint::make('currency')->label(__('admin.resources.transaction.fields.currency')),
                        TextConstraint::make('post.id')->label(__('admin.resources.transaction.fields.post_id')),
                        TextConstraint::make('stream.id')->label(__('admin.resources.transaction.fields.stream_id')),
                        TextConstraint::make('invoice.id')->label(__('admin.resources.transaction.fields.invoice_id')),
                        TextConstraint::make('userMessage.id')->label(__('admin.resources.transaction.fields.user_message_id')),
                        TextConstraint::make('subscription.id')->label(__('admin.resources.transaction.fields.subscription_id')),
                        TextConstraint::make('stripe_transaction_id')->label(__('admin.resources.transaction.fields.stripe_transaction_id')),
                        TextConstraint::make('stripe_session_id')->label(__('admin.resources.transaction.fields.stripe_session_id')),
                        TextConstraint::make('coinbase_charge_id')->label(__('admin.resources.transaction.fields.coinbase_charge_id')),
                        TextConstraint::make('nowpayments_payment_id')->label(__('admin.resources.transaction.fields.nowpayments_payment_id')),
                        TextConstraint::make('nowpayments_order_id')->label(__('admin.resources.transaction.fields.nowpayments_order_id')),
                        TextConstraint::make('ccbill_transaction_id')->label(__('admin.resources.transaction.fields.ccbill_transaction_id')),
                        TextConstraint::make('ccbill_subscription_id')->label(__('admin.resources.transaction.fields.ccbill_subscription_id')),
                        TextConstraint::make('verotel_sale_id')->label(__('admin.resources.transaction.fields.verotel_sale_id')),
                        TextConstraint::make('mercado_payment_id')->label(__('admin.resources.transaction.fields.mercado_payment_id')),
                        TextConstraint::make('yookassa_payment_id')->label(__('admin.resources.transaction.fields.yookassa_payment_id')),
                        TextConstraint::make('mollie_payment_id')->label(__('admin.resources.transaction.fields.mollie_payment_id')),
                        TextConstraint::make('flutterwave_payment_id')->label(__('admin.resources.transaction.fields.flutterwave_payment_id')),
                        TextConstraint::make('coingate_order_id')->label(__('admin.resources.transaction.fields.coingate_order_id')),
                        TextConstraint::make('xendit_payment_id')->label(__('admin.resources.transaction.fields.xendit_payment_id')),
                        TextConstraint::make('paddle_transaction_id')->label(__('admin.resources.transaction.fields.paddle_transaction_id')),
                        TextConstraint::make('cryptocom_payment_id')->label(__('admin.resources.transaction.fields.cryptocom_payment_id')),
                        TextConstraint::make('paypal_payer_id')->label(__('admin.resources.transaction.fields.paypal_payer_id')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
            'view' => ViewTransaction::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewTransaction::class,
//            Pages\EditTransaction::class,
        ]);
    }
}
