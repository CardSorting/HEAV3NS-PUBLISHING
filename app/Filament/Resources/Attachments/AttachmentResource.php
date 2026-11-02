<?php

namespace App\Filament\Resources\Attachments;

use App\Filament\Resources\Attachments\Forms\CreateAttachmentForm;
use App\Filament\Resources\Attachments\Pages\CreateAttachment;
use App\Filament\Resources\Attachments\Pages\EditAttachment;
use App\Filament\Resources\Attachments\Pages\ListAttachments;
use App\Filament\Resources\Attachments\Pages\ViewAttachment;
use App\Filament\Resources\Sounds\SoundResource;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Attachment;
use App\Services\Media\AttachmentLifecycleService;
use App\Services\Media\FileAttachmentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AttachmentResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Attachment::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    protected static string|UnitEnum|null $navigationGroup = 'Attachments';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.attachment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.attachment.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user',
            'sound',
            'transcoding',
        ])->withCount('placements');
    }

    public static function canEdit(Model $record): bool
    {
        return !($record instanceof Attachment && FileAttachmentService::isFile($record))
            && parent::canEdit($record);
    }

    public static function isShared(Model $record): bool
    {
        if (!$record instanceof Attachment) {
            return false;
        }

        return $record->vaulted_at !== null || $record->placements()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.attachment.sections.attachment_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.attachment.sections.attachment_details_descr'))
                ->schema(CreateAttachmentForm::schema())
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('preview')
                    ->label('Preview')
                    ->view('filament.tables.columns.attachment-preview'),
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.attachment.fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.attachment.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vault_status')
                    ->label(__('Vault status'))
                    ->state(fn (Attachment $record): string => $record->vaulted_at
                        ? ($record->vault_archived_at ? __('Archived') : __('In Vault'))
                        : __('Operational'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        __('In Vault') => 'success',
                        __('Archived') => 'warning',
                        default => 'gray',
                    })
                    ->sortable(false),
                Tables\Columns\TextColumn::make('file_link')
                    ->label(__('admin.resources.attachment.fields.file'))
                    ->state(fn (Attachment $record): string => FileAttachmentService::isFile($record) ? __('Download') : __('admin.resources.attachment.fields.open'))
                    ->url(fn (Attachment $record): ?string => AttachmentPresentation::url($record))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('driver')
                    ->label(__('admin.resources.attachment.fields.driver'))
                    ->formatStateUsing(fn ($state) => Attachment::getDriverName($state))
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.attachment.fields.type'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('placements_count')
                    ->label(__('Uses'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transcoding.status')
                    ->label('Video processing')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? str($state)->headline() : 'Not required')
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'failed', 'cancelled' => 'danger',
                        'queued', 'processing' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('vault_status')
                    ->label(__('Vault status'))
                    ->options([
                        'operational' => __('Operational'),
                        'vault' => __('In Vault'),
                        'archived' => __('Archived'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'operational' => $query->whereNull('vaulted_at'),
                            'vault' => $query->whereNotNull('vaulted_at')->whereNull('vault_archived_at'),
                            'archived' => $query->whereNotNull('vault_archived_at'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('processing')
                    ->label(__('Processing'))
                    ->options([
                        'queued' => __('Queued'),
                        'processing' => __('Processing'),
                        'completed' => __('Completed'),
                        'failed' => __('Failed'),
                        'cancelled' => __('Cancelled'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('transcoding', fn (Builder $transcoding): Builder => $transcoding->where('status', $data['value']));
                    }),
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('id')->label(__('admin.resources.attachment.fields.id')),
                        TextConstraint::make('type')->label(__('admin.resources.attachment.fields.type')),
                        TextConstraint::make('user.username')->label(__('admin.resources.attachment.fields.user')),
                        TextConstraint::make('placements.message.id')->label(__('admin.resources.attachment.fields.message')),
                        TextConstraint::make('placements.post.id')->label(__('admin.resources.attachment.fields.post')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                static::getRecordActionsGroup(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label(__('Delete files everywhere'))
                    ->modalDescription(__('The selected files will be removed from every post, message, template, story, reel, and Vault.'))
                    ->using(function (DeleteBulkAction $action, Collection $records): void {
                        $reportedException = false;
                        foreach ($records as $record) {
                            try {
                                Gate::authorize('delete', $record);
                                app(AttachmentLifecycleService::class)->deleteEverywhere($record);
                            } catch (\Throwable $exception) {
                                $action->reportBulkProcessingFailure();
                                if (!$reportedException) {
                                    report($exception);
                                    $reportedException = true;
                                }
                            }
                        }
                    }),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRecordActions(): array
    {
        return [
            Action::make('preview_file')
                ->label('Preview file')
                ->icon('heroicon-o-eye')
                ->authorize('view')
                ->modalHeading(fn (Attachment $record): string => AttachmentPresentation::data($record)['name'])
                ->modalContent(fn (Attachment $record) => view('filament.modals.attachment-preview', [
                    'attachment' => $record,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth(Width::FourExtraLarge),

            Action::make('usages')
                ->label(__('View usages'))
                ->icon('heroicon-o-link')
                ->authorize('view')
                ->modalContent(fn (Attachment $record) => AttachmentUsages::render($record))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close')),

            Action::make('open_sound')
                ->label(fn (Attachment $record): string => 'Open sound #'.$record->sound_id)
                ->icon('heroicon-o-musical-note')
                ->url(fn (Attachment $record): string => SoundResource::getUrl('view', ['record' => $record->sound_id]))
                ->visible(fn (Attachment $record): bool => (bool) $record->sound
                    && (auth()->user()?->can('view', $record->sound) ?? false)),

            Action::make('open_original')
                ->label(fn (Attachment $record): string => FileAttachmentService::isFile($record) ? __('Download file') : __('Open original file'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->authorize('view')
                ->url(fn (Attachment $record): ?string => AttachmentPresentation::url($record))
                ->openUrlInNewTab(),

            static::deleteEverywhereAction(),
        ];
    }

    public static function deleteEverywhereAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label(__('Delete file everywhere'))
            ->modalDescription(__('This removes the file from every post, message, template, story, reel, and Vault. Other files remain.'))
            ->using(function (Attachment $record): bool {
                Gate::authorize('delete', $record);
                app(AttachmentLifecycleService::class)->deleteEverywhere($record);

                return true;
            });
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->label('Attachment actions')
            ->icon('heroicon-o-ellipsis-horizontal')
            ->tooltip('Attachment actions')
            ->iconButton();
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
            'index' => ListAttachments::route('/'),
            'create' => CreateAttachment::route('/create'),
            'edit' => EditAttachment::route('/{record}/edit'),
            'view' => ViewAttachment::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewAttachment::class,
//            Pages\EditAttachment::class,
        ]);
    }
}
