<?php

namespace App\Filament\Resources\Stories;

use App\Filament\Resources\Stories\Pages\CreateStory;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Filament\Resources\Stories\Pages\ViewStory;
use App\Filament\Resources\Stories\Pages\ViewStoryAttachments;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Story;
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

class StoryResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = Story::class;

    protected static UnitEnum|string|null $navigationGroup = 'Stories';

    protected static ?int $navigationSort = 0;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('admin.resources.story.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.story.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.story.sections.details'))
                ->columnSpanFull()
                ->description(__('admin.resources.story.sections.details_descr'))
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label(__('admin.resources.story.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->preload(),

                    Forms\Components\Select::make('mode')
                        ->label(__('admin.resources.story.fields.mode'))
                        ->required()
                        ->options([
                            'media' => __('admin.resources.story.mode_labels.media'),
                            'text'  => __('admin.resources.story.mode_labels.text'),
                        ])
                        ->default('media'),

                    Forms\Components\Textarea::make('text')
                        ->label(__('admin.resources.story.fields.text'))
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('sound_id')
                        ->label(__('admin.resources.story.fields.sound_id'))
                        ->relationship('sound', 'title')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText(__('admin.resources.story.help.sound_id'))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('admin.resources.story.sections.settings'))
                ->columnSpanFull()
                ->description(__('admin.resources.story.sections.settings_descr'))
                ->schema([
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label(__('admin.resources.story.fields.expires_at'))
                        ->nullable()->columnSpanFull(),

                    Forms\Components\Toggle::make('is_public')
                        ->label(__('admin.resources.story.fields.is_public'))
                        ->required(),

                    Forms\Components\Toggle::make('is_highlight')
                        ->label(__('admin.resources.story.fields.is_highlight'))
                        ->required(),

                    Forms\Components\TextInput::make('bg_preset')
                        ->label(__('admin.resources.story.fields.bg_preset'))
                        ->maxLength(32)
                        ->nullable()
                    ->columnSpanFull(),

                    Forms\Components\TextInput::make('link_url')
                        ->label(__('admin.resources.story.fields.link_url'))
                        ->maxLength(2048)
                        ->nullable(),

                    Forms\Components\TextInput::make('link_text')
                        ->label(__('admin.resources.story.fields.link_text'))
                        ->maxLength(80)
                        ->nullable(),
                ])
                ->columns(2),

            Section::make(__('admin.resources.story.sections.overlay'))
                ->columnSpanFull()
                ->description(__('admin.resources.story.sections.overlay_descr'))
                ->schema([
                    Forms\Components\Textarea::make('overlay')
                        ->label(__('admin.resources.story.fields.overlay'))
                        ->helperText(__('admin.resources.story.help.overlay'))
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

                            // if valid json -> store as array; else store null (or keep raw string if you prefer)
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
                    ->label(__('admin.resources.story.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mode')
                    ->label(__('admin.resources.story.fields.mode'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'media' => 'info',
                        'text'  => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('text')
                    ->label(__('admin.resources.story.fields.text'))
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label(__('admin.resources.story.fields.is_public'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_highlight')
                    ->label(__('admin.resources.story.fields.is_highlight'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sound.title')
                    ->label(__('admin.resources.story.fields.sound_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Views count (no dedicated StoryView resource)
                Tables\Columns\TextColumn::make('views_count')
                    ->label(__('admin.resources.story.fields.views'))
                    ->counts('views') // requires Story::views() relationship (hasMany StoryView)
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('admin.resources.story.fields.expires_at'))
                    ->dateTime()
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
                        TextConstraint::make('mode')->label(__('admin.resources.story.fields.mode')),
                        TextConstraint::make('text')->label(__('admin.resources.story.fields.text')),
                        TextConstraint::make('bg_preset')->label(__('admin.resources.story.fields.bg_preset')),
                        DateConstraint::make('expires_at')->label(__('admin.resources.story.fields.expires_at')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        NumberConstraint::make('user_id')->label(__('admin.resources.story.fields.user_id')),
                        NumberConstraint::make('sound_id')->label(__('admin.resources.story.fields.sound_id')),
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
            ViewStoryAttachments::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStories::route('/'),
            'create' => CreateStory::route('/create'),
            'edit'   => EditStory::route('/{record}/edit'),
            'view'   => ViewStory::route('/{record}'),
            'attachments' => ViewStoryAttachments::route('/{record}/attachments'),
        ];
    }
}
