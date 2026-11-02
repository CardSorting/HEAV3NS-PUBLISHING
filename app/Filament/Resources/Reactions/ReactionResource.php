<?php

namespace App\Filament\Resources\Reactions;

use App\Filament\Resources\Reactions\Pages\CreateReaction;
use App\Filament\Resources\Reactions\Pages\EditReaction;
use App\Filament\Resources\Reactions\Pages\ListReactions;
use App\Filament\Resources\Reactions\Pages\ViewReaction;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Reaction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class ReactionResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Reaction::class;

    protected static ?int $navigationSort = 12;

    protected static UnitEnum|string|null $navigationGroup = 'Reactions';

    public static function getModelLabel(): string
    {
        return __('admin.resources.reaction.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.reaction.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.reaction.sections.reaction_info'))
                ->columnSpanFull()
                ->description(__('admin.resources.reaction.sections.reaction_info_descr'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.resources.reaction.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),

                    Select::make('reaction_type')
                        ->label(__('admin.resources.reaction.fields.reaction_type'))
                        ->options([
                            Reaction::LIKE_TYPE => __('admin.resources.reaction.types.like'),
                        ])
                        ->default(Reaction::LIKE_TYPE)
                        ->required(),
                ])
                ->columns(2),

            Section::make(__('admin.resources.reaction.sections.target_content'))
                ->columnSpanFull()
                ->description(__('admin.resources.reaction.sections.target_content_descr'))
                ->schema([
                    Select::make('post_id')
                        ->label(__('admin.resources.reaction.fields.post_id'))
                        ->relationship('post', 'id')
                        ->searchable()
                        ->nullable()
                        ->preload(true),

                    Select::make('post_comment_id')
                        ->label(__('admin.resources.reaction.fields.post_comment_id'))
                        ->relationship('comment', 'id')
                        ->searchable()
                        ->nullable()
                        ->preload(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.reaction.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reaction_type')
                    ->label(__('admin.resources.reaction.fields.reaction_type'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => __("admin.resources.reaction.types.".$state)),
                Tables\Columns\TextColumn::make('post.id')
                    ->label(__('admin.resources.reaction.fields.post_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment.id')
                    ->label(__('admin.resources.reaction.fields.post_comment_id'))
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
                        TextConstraint::make('user.username')->label(__('admin.resources.reaction.fields.user_id')),
                        SelectConstraint::make('reaction_type')
                            ->label(__('admin.resources.reaction.fields.reaction_type'))
                            ->options([
                                Reaction::LIKE_TYPE => __('admin.resources.reaction.types.like'),
                            ]),                        TextConstraint::make('post.id')->label(__('admin.resources.reaction.fields.post_id')),
                        TextConstraint::make('comment.id')->label(__('admin.resources.reaction.fields.post_comment_id')),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReactions::route('/'),
            'create' => CreateReaction::route('/create'),
            'edit' => EditReaction::route('/{record}/edit'),
            'view' => ViewReaction::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            // Pages\ViewReaction::class,
            // Pages\EditReaction::class,
        ]);
    }
}
