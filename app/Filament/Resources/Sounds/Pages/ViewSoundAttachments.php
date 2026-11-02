<?php

namespace App\Filament\Resources\Sounds\Pages;

use App\Filament\Resources\Attachments\Forms\CreateAttachmentForm;
use App\Filament\Resources\Sounds\SoundResource;
use App\Model\Attachment;
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
use Illuminate\Support\Facades\Auth;

class ViewSoundAttachments extends ManageRelatedRecords
{
    protected static string $resource = SoundResource::class;

    protected static string $relationship = 'attachments';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-clip';

    public function getTitle(): string|Htmlable
    {
        return __('admin.resources.attachment.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.attachment.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.attachment.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(
                CreateAttachmentForm::schema(
                    postId: null,
                    userMessageId: null,
                    storyId: null,
                    soundId: (int) $this->getRecord()->getKey(),
                    userId: Auth::id(),
                )
            );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('path')
                        ->label(__('admin.resources.attachment.fields.open'))
                        ->url(fn ($record) => $record->path)
                        ->openUrlInNewTab()
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->formatStateUsing(fn () => __('admin.resources.attachment.fields.open')),

                    TextEntry::make('user.username')
                        ->label(__('admin.resources.attachment.fields.user_id')),

                    TextEntry::make('driver')
                        ->label(__('admin.resources.attachment.fields.driver'))
                        ->formatStateUsing(fn ($state) => Attachment::getDriverName($state))
                        ->badge()
                        ->color('success'),

                    TextEntry::make('type')
                        ->label(__('admin.resources.attachment.fields.type')),

                    TextEntry::make('length')
                        ->label(__('admin.resources.attachment.fields.length'))
                        ->numeric()
                        ->visible(fn ($record) => !empty($record->length)),

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
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->label(__('admin.resources.attachment.fields.open'))
                    ->url(fn ($record) => $record->path)
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => __('admin.resources.attachment.fields.open')),

                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.attachment.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('driver')
                    ->label(__('admin.resources.attachment.fields.driver'))
                    ->formatStateUsing(fn ($state) => Attachment::getDriverName($state))
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.attachment.fields.type'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('length')
                    ->label(__('admin.resources.attachment.fields.length'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('admin.common.created_at'))
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => CreateAttachmentForm::prepareCreateData(
                        array_replace($data, ['sound_id' => $this->getRecord()->getKey()]),
                        allowMedia: true
                    ))
                    ->label(__('admin.common.create'))
                    ->modalHeading(__('admin.common.create')),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('admin.common.view'))
                    ->modalHeading(__('admin.common.view')),

                EditAction::make()
                    ->label(__('admin.common.edit'))
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
