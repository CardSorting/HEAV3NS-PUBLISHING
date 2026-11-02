<?php

namespace App\Filament\Resources\Polls;

use App\Filament\Resources\Polls\Pages\CreatePoll;
use App\Filament\Resources\Polls\Pages\EditPoll;
use App\Filament\Resources\Polls\Pages\ListPolls;
use App\Filament\Resources\Polls\Pages\ManagePollAnswers;
use App\Filament\Resources\Polls\Pages\ManageUserPollAnswers;
use App\Filament\Resources\Polls\Pages\ViewPoll;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Poll;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Pages\Enums\SubNavigationPosition;
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

class PollResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Poll::class;

    protected static string|UnitEnum|null $navigationGroup = 'Polls';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.poll.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.poll.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make(__('admin.resources.poll.sections.post_details'))
                    ->columnSpanFull()
                    ->description(__('admin.resources.poll.sections.post_details_descr'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('admin.resources.poll.fields.user_id'))
                            ->relationship('user', 'username')
                            ->searchable()
                            ->required()
                            ->preload(true),
                        Forms\Components\Select::make('post_id')
                            ->label(__('admin.resources.poll.fields.post_id'))
                            ->relationship('post', 'id')
                            ->searchable()
                            ->required()
                            ->preload(true),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('admin.resources.poll.fields.ends_at')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.poll.fields.user_id'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('post.id')
                    ->label(__('admin.resources.poll.fields.post_id'))
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('admin.resources.poll.fields.ends_at'))
                    ->dateTime()
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
                        TextConstraint::make('poll.id')->label(__('admin.resources.poll.filters.poll.id')),
                        TextConstraint::make('user.username')->label(__('admin.resources.poll.filters.user.username')),
                        DateConstraint::make('ends_at')->label(__('admin.resources.poll.fields.ends_at')),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolls::route('/'),
            'create' => CreatePoll::route('/create'),
            'edit' => EditPoll::route('/{record}/edit'),
            'view' => ViewPoll::route('/{record}'),
            'choices' => ManagePollAnswers::route('/{record}/choices'),
            'answers' => ManageUserPollAnswers::route('/{record}/responses'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ManagePollAnswers::class,
            ManageUserPollAnswers::class,
        ]);
    }
}
