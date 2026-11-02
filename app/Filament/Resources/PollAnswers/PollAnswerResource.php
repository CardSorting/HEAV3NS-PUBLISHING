<?php

namespace App\Filament\Resources\PollAnswers;

use App\Filament\Resources\PollAnswers\Forms\CreatePollAnswerForm;
use App\Filament\Resources\PollAnswerResource\Pages;
use App\Filament\Resources\PollAnswers\Pages\CreatePollAnswer;
use App\Filament\Resources\PollAnswers\Pages\EditPollAnswer;
use App\Filament\Resources\PollAnswers\Pages\ListPollAnswers;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\PollAnswer;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class PollAnswerResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = PollAnswer::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-check';

    protected static string|UnitEnum|null $navigationGroup = 'PollAnswers';

    protected static ?string $modelLabel = 'Poll Choices';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Answer Details')
                    ->description('Set up the poll choice.')
                    ->schema(CreatePollAnswerForm::schema())
                    ->columns(2), // Optional: layout
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('poll.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('answer')->label('Choice')
                    ->searchable()
                    ->sortable(),
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
                        TextConstraint::make('poll.id')->label('Poll ID'),
                        TextConstraint::make('answer')->label('Choice'),
                        DateConstraint::make('updated_at'),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
//                Tables\Actions\EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
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
            'index' => ListPollAnswers::route('/'),
            'create' => CreatePollAnswer::route('/create'),
            'edit' => EditPollAnswer::route('/{record}/edit'),
//            'view' => Pages\ViewPollAnswer::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewPollAnswer::class,
//            Pages\EditPollAnswer::class,
        ]);
    }
}
