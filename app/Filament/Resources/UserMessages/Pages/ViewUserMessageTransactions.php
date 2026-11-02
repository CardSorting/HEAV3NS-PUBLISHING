<?php

namespace App\Filament\Resources\UserMessages\Pages;

use App\Filament\Resources\Transactions\Forms\CreateTransactionForm;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Model\Transaction;
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
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Gate;

class ViewUserMessageTransactions extends ManageRelatedRecords
{
    protected static string $resource = UserMessageResource::class;

    protected static string $relationship = 'transactions';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';

    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    public function getTitle(): string | Htmlable
    {
        return __('admin.resources.transaction.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.user_message.transactions.breadcrumb');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.user_message.transactions.nav_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(
                CreateTransactionForm::schema(null, null, null, (int) $this->getRecord()->getKey())
            );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('sender.email')->label(__('admin.resources.user_message.transactions.fields.payer')),
                    TextEntry::make('status')
                        ->label(__('admin.resources.user_message.transactions.fields.status'))
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            Transaction::APPROVED_STATUS, Transaction::PARTIALLY_PAID_STATUS, Transaction::REFUNDED_STATUS => 'success',
                            Transaction::INITIATED_STATUS => 'gray',
                            Transaction::DECLINED_STATUS, Transaction::CANCELED_STATUS => 'danger',
                            Transaction::PENDING_STATUS => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('payment_provider')
                        ->label(__('admin.resources.user_message.transactions.fields.payment_provider'))
                        ->badge()
                        ->color('success'),
                    TextEntry::make('type')
                        ->label(__('admin.resources.user_message.transactions.fields.type'))
                        ->badge()
                        ->color('warning'),
                    TextEntry::make('amount')
                        ->label(__('admin.resources.user_message.transactions.fields.amount'))
                        ->money()
                        ->formatStateUsing(fn ($state, $record) => number_format($state, 2).strtoupper($record->currency)),
                    TextEntry::make('created_at')->dateTime()->label(__('admin.common.created_at')),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.user_message.transactions.fields.id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender.username')
                    ->label(__('admin.resources.user_message.transactions.fields.sender'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.user_message.transactions.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Transaction::APPROVED_STATUS, Transaction::PARTIALLY_PAID_STATUS, Transaction::REFUNDED_STATUS => 'success',
                        Transaction::INITIATED_STATUS => 'gray',
                        Transaction::DECLINED_STATUS, Transaction::CANCELED_STATUS => 'danger',
                        Transaction::PENDING_STATUS => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.user_message.transactions.fields.type'))
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('payment_provider')
                    ->label(__('admin.resources.user_message.transactions.fields.payment_provider'))
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.resources.user_message.transactions.fields.amount'))
                    ->badge()
                    ->money()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(),
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('admin.common.created_at'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.resources.user_message.transactions.actions.create'))
                    ->modalHeading(__('admin.resources.user_message.transactions.actions.create')),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
