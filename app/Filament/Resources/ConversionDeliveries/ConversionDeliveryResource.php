<?php

namespace App\Filament\Resources\ConversionDeliveries;

use App\Filament\Resources\ConversionDeliveries\Pages\ListConversionDeliveries;
use App\Filament\Resources\ConversionDeliveries\Pages\ViewConversionDelivery;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\ConversionDelivery;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ConversionDeliveryResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = ConversionDelivery::class;

    protected static UnitEnum|string|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string { return __('admin.resources.conversion_delivery.label'); }

    public static function getPluralModelLabel(): string { return __('admin.resources.conversion_delivery.plural'); }

    public static function getNavigationLabel(): string { return __('admin.resources.conversion_delivery.navigation'); }

    public static function getEloquentQuery(): Builder
    {
        // Do not hydrate click IDs, cookie hashes, claim tokens, or payment-provider credentials into Livewire.
        return parent::getEloquentQuery()->select([
            'id', 'transaction_id', 'attribution_id', 'event_id', 'method', 'counting_mode',
            'purchase_type', 'is_recurring', 'value', 'currency', 'test_mode', 'status', 'attempts',
            'available_at', 'sent_at', 'last_error', 'created_at', 'updated_at',
        ])->with([
            'transaction:id,sender_user_id,recipient_user_id',
            'transaction.sender:id,username',
            'transaction.receiver:id,username',
            'attribution:id,consented_at,expires_at,revoked_at',
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime()->sortable(),
                TextColumn::make('transaction_id')->label(self::label('transaction'))->prefix('#')->searchable()->sortable()
                    ->url(fn (ConversionDelivery $record) => static::transactionUrl($record)),
                TextColumn::make('transaction.receiver.username')->label(self::label('creator'))->placeholder('—')->searchable(),
                TextColumn::make('purchase_type')->label(self::label('purchase_type'))
                    ->formatStateUsing(fn (ConversionDelivery $record) => static::purchaseLabel($record)),
                TextColumn::make('value')->label(self::label('value'))->money(fn (ConversionDelivery $record) => $record->currency)->sortable(),
                TextColumn::make('method')->label(self::label('method'))->formatStateUsing(fn ($state) => static::methodLabels()[$state] ?? $state),
                TextColumn::make('status')->label(self::label('status'))->badge()->sortable()
                    ->formatStateUsing(fn ($state) => static::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => static::statusColor($state)),
                TextColumn::make('attempts')->label(self::label('attempts'))->numeric()->sortable(),
                TextColumn::make('sent_at')->label(self::label('sent_at'))->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()->constraints([
                    DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    SelectConstraint::make('status')->label(self::label('status'))->options(static::statusLabels()),
                    SelectConstraint::make('purchase_type')->label(self::label('purchase_type'))->options(static::purchaseLabels()),
                    SelectConstraint::make('method')->label(self::label('method'))->options(static::methodLabels()),
                    TextConstraint::make('transaction.receiver.username')->label(self::label('creator')),
                    TextConstraint::make('transaction_id')->label(self::label('transaction')),
                ])->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                ViewAction::make()->url(fn (ConversionDelivery $record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn (ConversionDelivery $record) => static::canView($record)),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('admin.resources.conversion_delivery.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.conversion_delivery.sections.purchase'))->columns(2)->columnSpanFull()->schema([
                TextEntry::make('transaction_id')->label(self::label('transaction'))->prefix('#')
                    ->url(fn (ConversionDelivery $record) => static::transactionUrl($record)),
                TextEntry::make('transaction.receiver.username')->label(self::label('creator'))->placeholder('—'),
                TextEntry::make('transaction.sender.username')->label(self::label('buyer'))->placeholder('—'),
                TextEntry::make('purchase_type')->label(self::label('purchase_type'))
                    ->formatStateUsing(fn (ConversionDelivery $record) => static::purchaseLabel($record)),
                TextEntry::make('value')->label(self::label('value'))->money(fn (ConversionDelivery $record) => $record->currency),
                TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime(),
            ]),
            Section::make(__('admin.resources.conversion_delivery.sections.delivery'))->columns(2)->columnSpanFull()
                ->description(__('admin.resources.conversion_delivery.receipt_notice'))->schema([
                    TextEntry::make('status')->label(self::label('status'))->badge()
                        ->formatStateUsing(fn ($state) => static::statusLabels()[$state] ?? $state)
                        ->color(fn ($state) => static::statusColor($state)),
                    TextEntry::make('method')->label(self::label('method'))->formatStateUsing(fn ($state) => static::methodLabels()[$state] ?? $state),
                    TextEntry::make('test_mode')->label(self::label('test_mode'))->formatStateUsing(fn ($state) => $state ? __('Yes') : __('No')),
                    TextEntry::make('attempts')->label(self::label('attempts')),
                    TextEntry::make('available_at')->label(self::label('available_at'))->dateTime()->placeholder('—'),
                    TextEntry::make('sent_at')->label(self::label('sent_at'))->dateTime()->placeholder('—'),
                    TextEntry::make('updated_at')->label(__('admin.common.updated_at'))->dateTime(),
                    TextEntry::make('counting_mode')->label(self::label('counting_mode'))
                        ->formatStateUsing(fn ($state) => __('admin.resources.conversion_delivery.counting_modes.'.$state)),
                    TextEntry::make('event_id')->label(self::label('event_id'))->columnSpanFull(),
                    TextEntry::make('last_error')->label(self::label('last_error'))->placeholder('—')->columnSpanFull(),
                ]),
            Section::make(__('admin.resources.conversion_delivery.sections.attribution'))->columns(2)->columnSpanFull()->schema([
                TextEntry::make('consent_state')->label(self::label('consent_state'))
                    ->state(fn (ConversionDelivery $record) => static::consentLabel($record)),
                TextEntry::make('attribution.consented_at')->label(self::label('consented_at'))->dateTime()->placeholder('—'),
                TextEntry::make('attribution.expires_at')->label(self::label('expires_at'))->dateTime()->placeholder('—'),
                TextEntry::make('attribution.revoked_at')->label(self::label('revoked_at'))->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    private static function label(string $field): string { return __('admin.resources.conversion_delivery.fields.'.$field); }

    public static function statusLabels(): array { return __('admin.resources.conversion_delivery.statuses'); }

    public static function purchaseLabels(): array { return __('admin.resources.conversion_delivery.purchase_types'); }

    public static function methodLabels(): array { return __('admin.resources.conversion_delivery.methods'); }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'sent', 'browser_dispatched' => 'success',
            'pending', 'delivering', 'browser_claimed' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function purchaseLabel(ConversionDelivery $record): string
    {
        return $record->purchase_type === 'subscription' && $record->is_recurring
            ? __('admin.resources.conversion_delivery.renewal')
            : (static::purchaseLabels()[$record->purchase_type] ?? __('Purchase'));
    }

    public static function consentLabel(ConversionDelivery $record): string
    {
        $attribution = $record->attribution;
        $state = match (true) {
            $attribution === null, $attribution->consented_at === null => 'unavailable',
            $attribution->revoked_at !== null => 'withdrawn',
            $attribution->expires_at === null, !$attribution->expires_at->isFuture() => 'expired',
            default => 'granted',
        };
        return __('admin.resources.conversion_delivery.consent_states.'.$state);
    }

    public static function transactionUrl(ConversionDelivery $record): ?string
    {
        return $record->transaction && TransactionResource::canView($record->transaction)
            ? TransactionResource::getUrl('view', ['record' => $record->transaction_id]) : null;
    }

    // Delivery records are an audit trail, even when an administrator has all standard resource permissions.
    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => ListConversionDeliveries::route('/'),
            'view' => ViewConversionDelivery::route('/{record}'),
        ];
    }
}
