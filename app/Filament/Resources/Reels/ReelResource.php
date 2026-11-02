<?php

namespace App\Filament\Resources\Reels;

use App\Filament\Resources\Reels\Pages\CreateReel;
use App\Filament\Resources\Reels\Pages\EditReel;
use App\Filament\Resources\Reels\Pages\ListReels;
use App\Filament\Resources\Reels\Pages\ViewReel;
use App\Filament\Resources\Reels\Pages\ViewReelAttachments;
use App\Filament\Resources\Reels\Pages\ViewReelComments;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Reel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class ReelResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = Reel::class;

    protected static UnitEnum|string|null $navigationGroup = 'Stories';

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('admin.resources.reel.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.reel.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.reel.sections.details'))
                ->columnSpanFull()
                ->description(__('admin.resources.reel.sections.details_descr'))
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label(__('admin.resources.reel.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->preload(),

                    Forms\Components\Select::make('sound_id')
                        ->label(__('admin.resources.reel.fields.sound_id'))
                        ->relationship('sound', 'title')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText(__('admin.resources.reel.help.sound_id')),

                    Forms\Components\Textarea::make('caption')
                        ->label(__('admin.resources.reel.fields.caption'))
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('admin.resources.reel.sections.settings'))
                ->columnSpanFull()
                ->description(__('admin.resources.reel.sections.settings_descr'))
                ->schema([
                    Forms\Components\Toggle::make('is_public')
                        ->label(__('admin.resources.reel.fields.is_public'))
                        ->required(),
                ])
                ->columns(2),

            Section::make(__('admin.resources.reel.sections.overlay'))
                ->columnSpanFull()
                ->description(__('admin.resources.reel.sections.overlay_descr'))
                ->schema([
                    Forms\Components\Textarea::make('overlay')
                        ->label(__('admin.resources.reel.fields.overlay'))
                        ->helperText(__('admin.resources.reel.help.overlay'))
                        ->columnSpanFull()
                        ->formatStateUsing(
                            fn ($state) => is_array($state)
                                ? json_encode($state, JSON_PRETTY_PRINT)
                                : $state
                        )
                        ->dehydrateStateUsing(function ($state) {
                            if (!is_string($state) || trim($state) === '') {
                                return null;
                            }

                            $decoded = json_decode($state, true);

                            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                        })
                        ->nullable(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.reel.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('caption')
                    ->label(__('admin.resources.reel.fields.caption'))
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label(__('admin.resources.reel.fields.is_public'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sound.title')
                    ->label(__('admin.resources.reel.fields.sound_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('views_count')
                    ->label(__('admin.resources.reel.fields.views'))
                    ->counts('views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comments_count')
                    ->label(__('admin.resources.reel.fields.comments'))
                    ->counts('comments')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reactions_count')
                    ->label(__('admin.resources.reel.fields.reactions'))
                    ->counts('reactions')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bookmarks_count')
                    ->label(__('admin.resources.reel.fields.bookmarks'))
                    ->counts('bookmarks')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        TextConstraint::make('caption')->label(__('admin.resources.reel.fields.caption')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        NumberConstraint::make('user_id')->label(__('admin.resources.reel.fields.user_id')),
                        NumberConstraint::make('sound_id')->label(__('admin.resources.reel.fields.sound_id')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                ...static::getRecordActionsForDisplay(),
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

    public static function getRecordActions(): array
    {
        return [
            Action::make('view_in_app')
                ->label(__('admin.resources.reel.actions.view_in_app'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => route('reels.get', ['reel_id' => $record->id]))
                ->openUrlInNewTab()
                ->color('info'),
            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal');
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewReelComments::class,
            ViewReelAttachments::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReels::route('/'),
            'create' => CreateReel::route('/create'),
            'edit' => EditReel::route('/{record}/edit'),
            'view' => ViewReel::route('/{record}'),
            'comments' => ViewReelComments::route('/{record}/comments'),
            'attachments' => ViewReelAttachments::route('/{record}/attachments'),
        ];
    }
}
