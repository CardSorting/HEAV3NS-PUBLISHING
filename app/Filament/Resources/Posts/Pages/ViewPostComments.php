<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\PostComments\Forms\CreatePostCommentForm;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\GifContent;
use App\Model\PostComment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Support\Facades\Gate;

class ViewPostComments extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = PostResource::class;

    protected static string $relationship = 'comments';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    public function getTitle(): string | Htmlable
    {
        return __('admin.resources.post_comment.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.post_comment.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.post_comment.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // root grid; keeps inner layout intact
            ->components(
                CreatePostCommentForm::schema((int) $this->getRecord()->getKey())
            );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('message')
                        ->label(__('admin.resources.post_comment.fields.message')),

                    GifContent::entry('post_comments'),

                    TextEntry::make('author.email')
                        ->label(__('admin.resources.post_comment.fields.author')),

                    TextEntry::make('created_at')
                        ->dateTime()
                        ->label(__('admin.common.created_at')),
                ])
                ->columns(1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.post_comment.fields.id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.resources.post_comment.fields.message'))
                    ->formatStateUsing(fn ($state, PostComment $record) => trim((string) $state) === '' && GifContent::reference($record) !== null ? __('GIF') : $state)
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author.username')
                    ->label(__('admin.resources.post_comment.fields.author'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('admin.common.created_at'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.common.create'))
                    ->mutateDataUsing(fn (array $data, Action $action) => GifContent::prepareAction($data, $action))
                    ->modalHeading(__('admin.common.create')),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('admin.common.view'))
                    ->modalHeading(__('admin.common.view')),
                EditAction::make()
                    ->label(__('admin.common.edit'))
                    ->mutateDataUsing(fn (array $data, Action $action, PostComment $record) => GifContent::prepareAction($data, $action, $record))
                    ->modalHeading(__('admin.common.edit')),
                DeleteAction::make()
                    ->label(__('admin.common.delete'))
                    ->modalHeading(__('admin.common.delete')),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
