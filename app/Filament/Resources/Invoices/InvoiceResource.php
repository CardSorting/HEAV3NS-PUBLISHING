<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Invoice;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Invoice::class;

    protected static string|UnitEnum|null $navigationGroup = 'Invoices';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.invoice.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make(__('admin.resources.invoice.sections.invoice_info'))
                    ->columnSpanFull()
                    ->description(__('admin.resources.invoice.sections.invoice_info_descr'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('invoice_id')
                            ->label(__('admin.resources.invoice.fields.invoice_id'))
                            ->required()
                            ->maxLength(191),
                        Textarea::make('data')
                            ->label(__('admin.resources.invoice.fields.data'))
                            ->required()
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_id')
                    ->label(__('admin.resources.invoice.fields.invoice_id'))
                    ->searchable(),
                TextColumn::make('transaction.id')
                    ->label(__('admin.resources.invoice.fields.transaction_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('transaction.id')->label(__('admin.resources.invoice.fields.transaction_id')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                Action::make('invoice_url')
                    ->label(__('admin.resources.invoice.actions.invoice_url'))
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn ($record) => route('invoices.get', ['id' => $record->id]))
                    ->openUrlInNewTab()
                    ->color('info'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
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
            'index' => ListInvoices::route('/'),
//            'create' => Pages\CreateInvoice::route('/create'),
//            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewInvoice::class,
//            Pages\EditInvoice::class,
        ]);
    }
}
