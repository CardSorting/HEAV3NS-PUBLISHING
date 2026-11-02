<?php

namespace App\Filament\Resources\Reels\Pages;

use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Support\GifContent;
use App\Model\ReelComment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ViewReelComments extends ManageRelatedRecords
{
    protected static string $resource = ReelResource::class;

    protected static string $relationship = 'comments';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    public function getTitle(): string|Htmlable
    {
        return __('admin.resources.reel_comment.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.reel_comment.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.reel_comment.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Forms\Components\Select::make('user_id')
                    ->label(__('admin.resources.reel_comment.fields.user_id'))
                    ->relationship('user', 'username')
                    ->searchable()
                    ->required()
                    ->preload(),

                Forms\Components\Select::make('parent_id')
                    ->label(__('admin.resources.reel_comment.fields.parent_id'))
                    ->relationship('parent', 'id')
                    ->searchable()
                    ->nullable()
                    ->preload(),

                Forms\Components\Textarea::make('message')
                    ->label(__('admin.resources.reel_comment.fields.message'))
                    ->required(fn (Get $get, ?ReelComment $record) => GifContent::reference($record) === null || $get('remove_gif'))
                    ->columnSpanFull(),

                ...GifContent::fields('reel_comments'),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('message')
                        ->label(__('admin.resources.reel_comment.fields.message')),

                    GifContent::entry('reel_comments'),

                    TextEntry::make('user.username')
                        ->label(__('admin.resources.reel_comment.fields.user_id')),

                    TextEntry::make('parent_id')
                        ->label(__('admin.resources.reel_comment.fields.parent_id'))
                        ->visible(fn ($record) => !empty($record->parent_id)),

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
                    ->label(__('admin.resources.reel_comment.fields.id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.resources.reel_comment.fields.message'))
                    ->formatStateUsing(fn ($state, ReelComment $record) => trim((string) $state) === '' && GifContent::reference($record) !== null ? __('GIF') : $state)
                    ->limit(50)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.reel_comment.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent_id')
                    ->label(__('admin.resources.reel_comment.fields.parent_id'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reactions_count')
                    ->label(__('admin.resources.reel_comment.fields.reactions'))
                    ->counts('reactions')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('admin.common.created_at'))
                    ->sortable(),
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
                    ->mutateDataUsing(fn (array $data, Action $action, ReelComment $record) => GifContent::prepareAction($data, $action, $record))
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
