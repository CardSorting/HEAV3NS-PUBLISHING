<?php

namespace App\Filament\Resources\UserTaxes;

use App\Filament\Resources\UserTaxes\Pages\CreateUserTax;
use App\Filament\Resources\UserTaxes\Pages\EditUserTax;
use App\Filament\Resources\UserTaxes\Pages\ListUserTaxes;
use App\Filament\Resources\UserTaxes\Pages\ViewUserTax;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Transaction;
use App\Model\UserTax;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use UnitEnum;

class UserTaxResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = UserTax::class;

    protected static ?int $navigationSort = 18;

    protected static UnitEnum|string|null $navigationGroup = 'FeaturedUsers';

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_tax.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_tax.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.user_tax.sections.user'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_tax.sections.user_descr'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.resources.user_tax.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->placeholder(__('admin.resources.user_tax.placeholders.user_id'))
                        ->preload(true),

                    Select::make('issuing_country_id')
                        ->label(__('admin.resources.user_tax.fields.issuing_country_id'))
                        ->relationship('issuingCountry', 'name')
                        ->searchable()
                        ->required()
                        ->placeholder(__('admin.resources.user_tax.placeholders.issuing_country_id'))
                        ->preload(true),
                ])
                ->columns(2),

            Section::make(__('admin.resources.user_tax.sections.tax'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_tax.sections.tax_descr'))
                ->schema([
                    TextInput::make('legal_name')
                        ->label(__('admin.resources.user_tax.fields.legal_name'))
                        ->required()
                        ->maxLength(191),

                    TextInput::make('tax_identification_number')
                        ->label(__('admin.resources.user_tax.fields.tax_identification_number'))
                        ->required()
                        ->maxLength(191),

                    TextInput::make('vat_number')
                        ->label(__('admin.resources.user_tax.fields.vat_number'))
                        ->maxLength(191)
                        ->default(null),

                    Select::make('tax_type')
                        ->label(__('admin.resources.user_tax.fields.tax_type'))
                        ->required()
                        ->options([
                            UserTax::DAC7_TYPE => __('admin.resources.user_tax.options.types.dac7'),
                        ])
                        ->default(UserTax::DAC7_TYPE),
                ])
                ->columns(2),

            Section::make(__('admin.resources.user_tax.sections.personal'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_tax.sections.personal_descr'))
                ->schema([
                    DateTimePicker::make('date_of_birth')
                        ->label(__('admin.resources.user_tax.fields.date_of_birth'))
                        ->required(),

                    Textarea::make('primary_address')
                        ->label(__('admin.resources.user_tax.fields.primary_address'))
                        ->required()
                        ->placeholder(__('admin.resources.user_tax.descriptions.primary_address'))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $userTaxesTable = (new UserTax)->getTable();
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.user_tax.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_type')
                    ->label(__('admin.resources.user_tax.fields.tax_type'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('legal_name')
                    ->label(__('admin.resources.user_tax.fields.legal_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('issuingCountry.name')
                    ->label(__('admin.resources.user_tax.fields.issuing_country_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_identification_number')
                    ->label(__('admin.resources.user_tax.fields.tax_identification_number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('earnings_ytd')
                    ->label(__('admin.resources.user_tax.fields.earnings_ytd'))
                    ->numeric()
                    ->money(getSetting('payments.currency_code'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

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
                        TextConstraint::make('user.username')->label(__('admin.resources.user_tax.fields.user_id')),
                        TextConstraint::make('issuingCountry.name')->label(__('admin.resources.user_tax.fields.issuing_country_id')),
                        TextConstraint::make('legal_name')->label(__('admin.resources.user_tax.fields.legal_name')),
                        TextConstraint::make('tax_identification_number')->label(__('admin.resources.user_tax.fields.tax_identification_number')),
                        TextConstraint::make('vat_number')->label(__('admin.resources.user_tax.fields.vat_number')),
                        TextConstraint::make('tax_type')->label(__('admin.resources.user_tax.fields.tax_type')),
                        TextConstraint::make('primary_address')->label(__('admin.resources.user_tax.fields.primary_address')),
                        DateConstraint::make('date_of_birth')->label(__('admin.resources.user_tax.fields.date_of_birth')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),

                // Custom calculation filter
                Tables\Filters\Filter::make('earnings_ytd')
                    ->label(__('admin.resources.user_tax.fields.earnings_ytd'))
                    ->form([
                        TextInput::make('min')
                            ->label(__('admin.resources.user_tax.filters.min_earnings'))

                            ->numeric()
                            ->placeholder('e.g. 2000'),
                    ])
                    ->query(function ($query, array $data) {
                        $min = $data['min'] ?? null;

                        if ($min === null || $min === '') {
                            return $query;
                        }

                        $year = now()->year;
                        $userTaxesTable = (new UserTax())->getTable();
                        $transactionsTable = (new Transaction())->getTable();

                        return $query->whereIn("$userTaxesTable.user_id", function ($sub) use ($min, $year, $transactionsTable) {
                            $sub->select('recipient_user_id')
                                ->from($transactionsTable)
                                ->whereYear('created_at', $year)
                                ->where('status', Transaction::APPROVED_STATUS)
                                ->where('type', '!=', Transaction::WITHDRAWAL_TYPE)
                                ->groupBy('recipient_user_id')
                                ->havingRaw('SUM(amount) >= ?', [$min]);
                        });
                    }),

            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
//                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exports([
                    ExcelExport::make('user_taxes_csv')
                        ->label('User Taxes CSV')
                        ->withWriterType(Excel::CSV)
                        ->withFilename(fn () => 'user-taxes-'.now()->format('Y-m-d'))
                        ->withColumns([
                            Column::make('id')->heading('ID'),
                            Column::make('user.username')->heading(__('admin.resources.user_tax.fields.user_id')),
                            Column::make('legal_name')->heading(__('admin.resources.user_tax.fields.legal_name')),
                            Column::make('tax_identification_number')->heading(__('admin.resources.user_tax.fields.tax_identification_number')),
                            Column::make('vat_number')->heading(__('admin.resources.user_tax.fields.vat_number')),
                            Column::make('issuingCountry.name')->heading(__('admin.resources.user_tax.fields.issuing_country_id')),
                            Column::make('primary_address')->heading(__('admin.resources.user_tax.fields.primary_address')),
                            Column::make('date_of_birth')->heading(__('admin.resources.user_tax.fields.date_of_birth')),
                            Column::make('user.earnings_ytd')->heading(__('admin.resources.user_tax.fields.earnings_ytd')),
                            Column::make('created_at')->heading(__('admin.common.created_at')),
                            Column::make('updated_at')->heading(__('admin.common.updated_at')),
                        ]),
                ]),
                DeleteBulkAction::make(),

            ])
            ->modifyQueryUsing(function ($query) use ($userTaxesTable) {
                $year = now()->year;

                $query->addSelect([
                    'earnings_ytd' => Transaction::selectRaw('COALESCE(SUM(amount), 0)')
                        ->whereColumn('transactions.recipient_user_id', "$userTaxesTable.user_id")
                        ->whereYear('transactions.created_at', $year)
                        ->where('transactions.status', Transaction::APPROVED_STATUS),
                ]);
            })
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserTaxes::route('/'),
            'create' => CreateUserTax::route('/create'),
            'edit' => EditUserTax::route('/{record}/edit'),
            'view' => ViewUserTax::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([]);
    }
}
