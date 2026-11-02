<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Support\Facades\Gate;

class ViewPostTransactions extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = PostResource::class;

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
            ->columns(1) // root grid; inner Sections keep their own layout
            ->components(
                CreateTransactionForm::schema((int) $this->getRecord()->getKey())
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

                    TextEntry::make('sender.username')
                        ->label(__('admin.resources.transaction.fields.sender')),

                    TextEntry::make('status')
                        ->label(__('admin.resources.transaction.fields.status'))
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            Transaction::APPROVED_STATUS,
                            Transaction::PARTIALLY_PAID_STATUS,
                            Transaction::REFUNDED_STATUS => 'success',
                            Transaction::INITIATED_STATUS => 'gray',
                            Transaction::DECLINED_STATUS,
                            Transaction::CANCELED_STATUS => 'danger',
                            Transaction::PENDING_STATUS => 'warning',
                            default => 'gray', // use 'gray' unless you’ve defined 'secondary'
                        }),

                    TextEntry::make('payment_provider')
                        ->label(__('admin.resources.transaction.fields.payment_provider'))
                        ->badge()
                        ->color('success'),

                    TextEntry::make('type')
                        ->label(__('admin.resources.transaction.fields.type'))
                        ->badge()
                        ->color('warning'),

                    TextEntry::make('amount')
                        ->label(__('admin.resources.transaction.fields.amount'))
                        ->formatStateUsing(
                            fn ($state, $record) => number_format((float) $state, 2).strtoupper($record->currency)
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
                TextColumn::make('id')
                    ->label(__('admin.resources.transaction.fields.id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sender.username')
                    ->label(__('admin.resources.transaction.fields.sender_user_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('receiver.username')
                    ->label(__('admin.resources.transaction.fields.receiver_user_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('admin.resources.transaction.fields.amount'))
                    ->money()
                    ->money(getSetting('payments.currency_code'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->summarize([
                        Sum::make()->money(getSetting('payments.currency_code')),
                    ]),

                TextColumn::make('status')
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

                TextColumn::make('type')
                    ->label(__('admin.resources.transaction.fields.type'))
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => __('admin.resources.transaction.type_labels.'.str_replace('-', '_', $state))),

                TextColumn::make('payment_provider')
                    ->label(__('admin.resources.transaction.fields.payment_provider'))
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('warning'),

                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
