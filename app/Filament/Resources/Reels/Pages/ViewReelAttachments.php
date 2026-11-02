<?php

namespace App\Filament\Resources\Reels\Pages;

use App\Filament\Resources\Attachments\Concerns\ManagesAttachmentPlacements;
use App\Filament\Resources\Reels\ReelResource;
use App\Model\Attachment;
use App\Filament\Resources\Attachments\AttachmentPresentation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ViewReelAttachments extends ManageRelatedRecords
{
    use ManagesAttachmentPlacements;

    protected static string $resource = ReelResource::class;

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
        return $schema->components([]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('file_link')
                        ->state(fn (Attachment $record): string => __('Open file'))
                        ->label(__('admin.resources.attachment.fields.open'))
                        ->url(fn (Attachment $record): ?string => AttachmentPresentation::url($record))
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
                Tables\Columns\TextColumn::make('file_link')
                    ->state(fn (Attachment $record): string => __('Open file'))
                    ->label(__('admin.resources.attachment.fields.open'))
                    ->url(fn (Attachment $record): ?string => AttachmentPresentation::url($record))
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
                $this->addAttachmentAction(),
                $this->manageMediaAction(),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('admin.common.view'))
                    ->modalHeading(__('admin.common.view')),

                $this->detachMediaAction(),
            ])
            ->bulkActions([
            ])
            ->paginated([10, 25, 50]);
    }
}
