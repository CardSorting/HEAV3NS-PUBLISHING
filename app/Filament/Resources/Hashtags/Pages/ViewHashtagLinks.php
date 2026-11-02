<?php

namespace App\Filament\Resources\Hashtags\Pages;

use App\Filament\Resources\Hashtags\HashtagResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use BackedEnum;

class ViewHashtagLinks extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;
        return $parent && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = HashtagResource::class;

    protected static string $relationship = 'links';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-link';

    public function getTitle(): string|Htmlable
    {
        return __('admin.resources.hashtag_link.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.hashtag_link.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.hashtag_link.plural');
    }

    // Read-only page: no form (no create/edit)
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('post_id')
                    ->label(__('admin.resources.hashtag_link.fields.post_id'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('post_comment_id')
                    ->label(__('admin.resources.hashtag_link.fields.post_comment_id'))
                    ->sortable()
                    ->searchable(),

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
            ->filters([])
            ->headerActions([
                // No CreateAction (read-only)
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('admin.common.view'))
                    ->modalHeading(__('admin.common.view')),
                DeleteAction::make()
                    ->label(__('admin.common.delete'))
                    ->modalHeading(__('admin.common.delete')),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
