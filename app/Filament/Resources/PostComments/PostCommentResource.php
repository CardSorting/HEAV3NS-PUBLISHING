<?php

namespace App\Filament\Resources\PostComments;

use App\Filament\Resources\PostComments\Forms\CreatePostCommentForm;
use App\Filament\Resources\PostComments\Pages\CreatePostComment;
use App\Filament\Resources\PostComments\Pages\EditPostComment;
use App\Filament\Resources\PostComments\Pages\ListPostComments;
use App\Filament\Resources\PostComments\Pages\ViewPostComment;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Filament\Support\GifContent;
use App\Model\PostComment;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class PostCommentResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = PostComment::class;

    protected static UnitEnum|string|null $navigationGroup = 'Attachments';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.post_comment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.post_comment.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.post_comment.sections.post_comment_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.post_comment.sections.post_comment_details_descr'))
                ->schema(CreatePostCommentForm::schema()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('author.username')
                    ->label(__('admin.resources.post_comment.fields.author'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.resources.post_comment.fields.message'))
                    ->formatStateUsing(fn ($state, PostComment $record) => trim((string) $state) === '' && GifContent::reference($record) !== null ? __('GIF') : $state)
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('post_id')
                    ->label(__('admin.resources.post_comment.fields.post_id'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('id')->label(__('admin.resources.post_comment.fields.id')),
                        TextConstraint::make('author.username')->label(__('admin.resources.post_comment.fields.author')),
                        TextConstraint::make('message')->label(__('admin.resources.post_comment.fields.message')),
                        TextConstraint::make('post_id')->label(__('admin.resources.post_comment.fields.post_id')),
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
            'index' => ListPostComments::route('/'),
            'create' => CreatePostComment::route('/create'),
            'edit' => EditPostComment::route('/{record}/edit'),
            'view' => ViewPostComment::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewPostComment::class,
//            Pages\EditPostComment::class,
        ]);
    }
}
