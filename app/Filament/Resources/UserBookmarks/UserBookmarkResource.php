<?php

namespace App\Filament\Resources\UserBookmarks;

use App\Filament\Resources\UserBookmarks\Pages\CreateUserBookmark;
use App\Filament\Resources\UserBookmarks\Pages\EditUserBookmark;
use App\Filament\Resources\UserBookmarks\Pages\ListUserBookmarks;
use App\Filament\Resources\UserBookmarks\Pages\ViewUserBookmark;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\UserBookmark;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
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

class UserBookmarkResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = UserBookmark::class;

    protected static ?int $navigationSort = 15;

    protected static UnitEnum|string|null $navigationGroup = 'UserBookmarks';

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_bookmark.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_bookmark.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.user_bookmark.sections.bookmark_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_bookmark.sections.bookmark_details_descr'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.resources.user_bookmark.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_bookmark.fields.user_id'))
                        ->required()
                        ->preload(true),

                    Select::make('post_id')
                        ->label(__('admin.resources.user_bookmark.fields.post_id'))
                        ->relationship('post', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_bookmark.fields.post_id'))
                        ->preload(true),

                    Select::make('reel_id')
                        ->label(__('admin.resources.user_bookmark.fields.reel_id'))
                        ->relationship('reel', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_bookmark.fields.reel_id'))
                        ->preload(true),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.user_bookmark.fields.username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('post.id')
                    ->label(__('admin.resources.user_bookmark.fields.post_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reel.id')
                    ->label(__('admin.resources.user_bookmark.fields.reel_id'))
                    ->searchable()
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
                        TextConstraint::make('user.username')->label(__('admin.resources.user_bookmark.fields.username')),
                        TextConstraint::make('post.id')->label(__('admin.resources.user_bookmark.fields.post_id')),
                        TextConstraint::make('reel.id')->label(__('admin.resources.user_bookmark.fields.reel_id')),
                        DateConstraint::make('created_at')->label(__('admin.resources.user_bookmark.fields.created_at')),
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
            'index' => ListUserBookmarks::route('/'),
            'create' => CreateUserBookmark::route('/create'),
            'edit' => EditUserBookmark::route('/{record}/edit'),
            'view' => ViewUserBookmark::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            // Pages\ViewUserBookmark::class,
            // Pages\EditUserBookmark::class,
        ]);
    }
}
