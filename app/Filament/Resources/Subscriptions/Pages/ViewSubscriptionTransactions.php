<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\Transactions\Forms\CreateTransactionForm;
use App\Model\Transaction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Support\Facades\Gate;

class ViewSubscriptionTransactions extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = SubscriptionResource::class;

    protected static string $relationship = 'transactions';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';

    public function getTitle(): string | Htmlable
    {
        return __('admin.resources.transaction.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.transaction.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.transaction.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // or Grid::make(1) wrapper if you prefer
            ->components(
                CreateTransactionForm::schema(null, (int) $this->getRecord()->getKey())
            );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('id')
                        ->label(__('admin.resources.transaction.fields.id')),

                    TextEntry::make('sender.email')
                        ->label(__('admin.resources.transaction.fields.sender')),

                    TextEntry::make('status')
                        ->label(__('admin.resources.transaction.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                        ->color(fn (string $state): string => match ($state) {
                            Transaction::APPROVED_STATUS,
                            Transaction::PARTIALLY_PAID_STATUS,
                            Transaction::REFUNDED_STATUS => 'success',
                            Transaction::INITIATED_STATUS => 'gray',
                            Transaction::DECLINED_STATUS,
                            Transaction::CANCELED_STATUS => 'danger',
                            Transaction::PENDING_STATUS => 'warning',
                            default => 'gray',
                        }),

                    TextEntry::make('payment_provider')
                        ->label(__('admin.resources.transaction.fields.payment_provider'))
                        ->badge()
                        ->color('success'),

                    TextEntry::make('type')
                        ->label(__('admin.resources.transaction.fields.type'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                        ->color('warning'),

                    TextEntry::make('amount')
                        ->label(__('admin.resources.transaction.fields.amount'))
                        // Option A (custom format):
                        ->formatStateUsing(
                            fn ($state, $record) => number_format((float) $state, 2).' '.strtoupper($record->currency)
                        ),

                    TextEntry::make('created_at')
                        ->dateTime()
                        ->label(__('admin.common.created_at')),
                ])
                ->columns(1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.transaction.fields.id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sender.username')
                    ->label(__('admin.resources.transaction.fields.sender_user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.transaction.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        Transaction::APPROVED_STATUS, Transaction::PARTIALLY_PAID_STATUS, Transaction::REFUNDED_STATUS => 'success',
                        Transaction::INITIATED_STATUS => 'gray',
                        Transaction::DECLINED_STATUS, Transaction::CANCELED_STATUS => 'danger',
                        Transaction::PENDING_STATUS => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.transaction.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_provider')
                    ->label(__('admin.resources.transaction.fields.payment_provider'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('success')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.resources.transaction.fields.amount'))
                    ->money()
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2).' '.strtoupper($record->currency))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.common.create'))
                    ->modalHeading(__('admin.common.create')),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('admin.common.view'))
                    ->modalHeading(__('admin.common.view')),
                EditAction::make()
                    ->label(__('admin.common.edit'))
                    ->modalHeading(__('admin.common.edit')),
                DeleteAction::make()
                    ->label(__('admin.common.delete'))
                    ->modalHeading(__('admin.common.delete')),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
