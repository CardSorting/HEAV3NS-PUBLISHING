<?php

namespace App\Filament\Resources\Wallets;

use App\Filament\Resources\Wallets\Pages\CreateWallet;
use App\Filament\Resources\Wallets\Pages\EditWallet;
use App\Filament\Resources\Wallets\Pages\ListWallets;
use App\Filament\Resources\Wallets\Pages\ViewWallet;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Wallet;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class WalletResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Wallet::class;

    protected static ?int $navigationSort = 10;

    protected static UnitEnum|string|null $navigationGroup = 'Wallets';

    public static function getModelLabel(): string
    {
        return __('admin.resources.wallet.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.wallet.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $uuid = Str::uuid()->toString();
        return $schema->components([
            Section::make(__('admin.resources.wallet.sections.wallet_details'))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('id')
                        ->label(__('admin.resources.wallet.fields.id'))
                        ->helperText(__('admin.resources.wallet.helper_texts.id'))
                        ->required()
                        ->default($uuid),
                    Select::make('user_id')
                        ->relationship('user', 'username')
                        ->label(__('admin.resources.wallet.fields.user_id'))
                        ->searchable()
                        ->required()
                        ->preload(true),
                    TextInput::make('total')
                        ->label(__('admin.resources.wallet.fields.total'))
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->required(),
                ])
                ->columns(1), // You can change to 2 if you'd like fields side by side
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.wallet.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label(__('admin.resources.wallet.fields.total'))
                    ->numeric()
                    ->money(getSetting('payments.currency_code'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('id')->label(__('admin.resources.wallet.fields.id')),
                        TextConstraint::make('user.username')->label(__('admin.resources.wallet.fields.user_id')),
                        NumberConstraint::make('total')->label(__('admin.resources.wallet.fields.total')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
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
            'index' => ListWallets::route('/'),
            'create' => CreateWallet::route('/create'),
            'edit' => EditWallet::route('/{record}/edit'),
            'view' => ViewWallet::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewWallet::class,
//            Pages\EditWallet::class,
        ]);
    }
}
