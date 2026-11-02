<?php

namespace App\Filament\Resources\Countries;

use App\Filament\Resources\Countries\Pages\CreateCountry;
use App\Filament\Resources\Countries\Pages\EditCountry;
use App\Filament\Resources\Countries\Pages\ListCountries;
use App\Filament\Resources\Countries\Pages\ViewCountry;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Country;
use App\Services\Payments\CheckoutBilling;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;
use Filament\Schemas\Schema;
use Filament\Forms\Components as Forms;

class CountryResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Country::class;

    protected static string|UnitEnum|null $navigationGroup = 'Countries';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.country.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.country.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.country.sections.country_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.country.sections.country_details_descr'))
                ->schema([
                    Forms\TextInput::make('name')
                        ->label(__('admin.resources.country.fields.name'))
                        ->required()
                        ->rules([
                            fn (?Country $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                if (!CheckoutBilling::collectsDetails()
                                    && $record?->name === 'All'
                                    && $value !== 'All'
                                    && $record->taxes()->exists()) {
                                    $fail('Changing All countries with linked taxes requires Full details under Payments Settings. Enable billing details collection or remove its tax assignments first.');
                                }
                            },
                        ])
                        ->maxLength(191),

                    Forms\TextInput::make('country_code')
                        ->label(__('admin.resources.country.fields.country_code'))
                        ->maxLength(191)
                        ->default(null),

                    Forms\TextInput::make('phone_code')
                        ->label(__('admin.resources.country.fields.phone_code'))
                        ->tel()
                        ->maxLength(191)
                        ->default(null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.resources.country.fields.name'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label(__('admin.resources.country.fields.country_code'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_code')
                    ->label(__('admin.resources.country.fields.phone_code'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.resources.country.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.resources.country.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('name')->label(__('admin.resources.country.fields.name')),
                        TextConstraint::make('country_code')->label(__('admin.resources.country.fields.country_code')),
                        TextConstraint::make('phone_code')->label(__('admin.resources.country.fields.phone_code')),
                        DateConstraint::make('created_at')->label(__('admin.resources.country.fields.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.resources.country.fields.updated_at')),
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
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'edit' => EditCountry::route('/{record}/edit'),
            'view' => ViewCountry::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewCountry::class,
//            Pages\EditCountry::class,
        ]);
    }
}
