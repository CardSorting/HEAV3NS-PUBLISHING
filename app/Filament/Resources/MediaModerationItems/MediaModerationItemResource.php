<?php

namespace App\Filament\Resources\MediaModerationItems;

use App\Filament\Resources\Attachments\AttachmentUsages;
use App\Filament\Resources\Attachments\AttachmentPresentation;
use App\Services\Media\FileAttachmentService;
use App\Filament\Resources\MediaModerationItems\Pages\ListMediaModerationItems;
use App\Filament\Resources\MediaModerationItems\Pages\ViewMediaModerationItem;
use App\Filament\Resources\MediaModerationRuns\MediaModerationRunResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\Streams\StreamResource;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Resources\Users\UserResource;
use App\Model\MediaModerationItem;
use App\Model\TextModerationItem;
use App\Services\Moderation\ModerationDeletionService;
use App\Services\Moderation\AttachmentMediaService;
use App\Services\Moderation\ModerationReviewService;
use App\Services\Moderation\ModerationScanService;
use App\Services\Moderation\ProfileMediaSourceService;
use App\Services\Moderation\StreamPosterSourceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

class MediaModerationItemResource extends Resource
{
    protected static ?string $model = MediaModerationItem::class;

    protected static UnitEnum|string|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 18;

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getModelLabel(): string
    {
        return __('Media finding');
    }

    public static function getPluralModelLabel(): string
    {
        return __('AI findings');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = MediaModerationItem::query()
            ->whereIn('decision', [MediaModerationItem::DECISION_REVIEW, MediaModerationItem::DECISION_VIOLATION])
            ->where('admin_outcome', MediaModerationItem::OUTCOME_UNREVIEWED)
            ->withAvailableSource()
            ->count();

        if (SchemaFacade::hasTable('text_moderation_items')) {
            $count += TextModerationItem::query()
                ->whereIn('decision', [TextModerationItem::DECISION_REVIEW, TextModerationItem::DECISION_VIOLATION])
                ->where('admin_outcome', TextModerationItem::OUTCOME_UNREVIEWED)
                ->withAvailableSource()
                ->count();
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['attachment', 'owner', 'run.contentReview', 'reviewer', 'deleter']);
        $runId = request()->query('run');

        if (is_string($runId) && $runId !== '') {
            $query->where('run_id', $runId);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Review')
                ->description(__('Confirming or dismissing a finding does not approve or reject publication.'))
                ->columns(3)
                ->schema([
                    MediaModerationRunResource::contentApprovalEntry(),
                    ImageEntry::make('media_preview')
                        ->label('Preview')
                        ->state(fn (MediaModerationItem $record) => static::previewUrl($record))
                        ->square()
                        ->columnSpanFull()
                        ->visible(fn (MediaModerationItem $record) => $record->media_type === 'image'
                            && filled(static::previewUrl($record))),
                    TextEntry::make('media_file')
                        ->label('Media file')
                        ->state(fn (MediaModerationItem $record) => $record->attachment && FileAttachmentService::isFile($record->attachment)
                            ? __('Download file')
                            : ($record->isManagedAsset() ? 'Open '.$record->sourceLabel() : 'Open media'))
                        ->url(fn (MediaModerationItem $record) => static::mediaUrl($record))
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->visible(fn (MediaModerationItem $record) => filled(static::mediaUrl($record))),
                    TextEntry::make('file_information')
                        ->label(__('File attachment'))
                        ->state(function (MediaModerationItem $record): string {
                            $media = AttachmentPresentation::data($record->attachment);

                            return $media['name'].' · '.$media['format'].' · '.$media['size'];
                        })
                        ->visible(fn (MediaModerationItem $record): bool => $record->attachment && FileAttachmentService::isFile($record->attachment))
                        ->columnSpanFull(),
                    TextEntry::make('decision')->badge()->color(fn ($state) => static::decisionColor($state)),
                    TextEntry::make('admin_outcome')->label('Admin decision')->badge(),
                    TextEntry::make('max_score')->label('Confidence')->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 1).'%' : '—'),
                    TextEntry::make('matched_class')->label('Matched signal')->formatStateUsing(fn ($state) => str_replace('_', ' ', (string) $state))->placeholder('—'),
                    TextEntry::make('matched_at_ms')->label('Video position')->formatStateUsing(fn ($state) => $state !== null ? number_format($state / 1000, 1).'s' : '—'),
                    TextEntry::make('owner.username')
                        ->label('Owner')
                        ->placeholder('Unknown')
                        ->url(fn (MediaModerationItem $record) => $record->owner
                            ? UserResource::getUrl('view', ['record' => $record->owner])
                            : null)
                        ->openUrlInNewTab()
                        ->icon(fn (MediaModerationItem $record) => $record->owner
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),
                    TextEntry::make('subject_type')
                        ->label('Scanned context')
                        ->formatStateUsing(fn ($state, MediaModerationItem $record) => $record->sourceLabel())
                        ->url(fn (MediaModerationItem $record) => static::subjectUrl($record))
                        ->openUrlInNewTab()
                        ->icon(fn (MediaModerationItem $record) => static::subjectUrl($record)
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),
                    TextEntry::make('subject_id')->label('Historical content ID')->placeholder('File scan'),
                    TextEntry::make('current_usages')
                        ->label('Current usages')
                        ->state(fn (MediaModerationItem $record) => $record->attachment ? AttachmentUsages::render($record->attachment) : null)
                        ->html()
                        ->columnSpanFull()
                        ->visible(fn (MediaModerationItem $record): bool => (bool) $record->attachment),
                    TextEntry::make('scan_status')
                        ->label('Scan status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => static::scanStatusLabel($state))
                        ->color(fn ($state) => static::scanStatusColor($state)),
                    TextEntry::make('is_cached')->label('Saved result')->formatStateUsing(fn ($state) => $state ? 'Reused' : 'Fresh provider result')->badge(),
                    TextEntry::make('scanned_at')->label('Scanned')->dateTime()->placeholder('—'),
                    TextEntry::make('review_actor')
                        ->label('Decision by')
                        ->state(fn (MediaModerationItem $record) => $record->reviewer->username
                            ?? ($record->admin_outcome === MediaModerationItem::OUTCOME_SUPERSEDED
                                ? 'Automatic policy update'
                                : 'Administrator no longer available'))
                        ->visible(fn (MediaModerationItem $record) => (bool) $record->reviewed_at),
                    TextEntry::make('reviewed_at')
                        ->label('Reviewed')
                        ->dateTime()
                        ->visible(fn (MediaModerationItem $record) => (bool) $record->reviewed_at),
                    TextEntry::make('review_notes')
                        ->label('Review notes')
                        ->columnSpanFull()
                        ->visible(fn (MediaModerationItem $record) => filled($record->review_notes)),
                    TextEntry::make('deleter.username')
                        ->label('Deleted by')
                        ->placeholder('Administrator no longer available')
                        ->visible(fn (MediaModerationItem $record) => (bool) $record->content_deleted_at),
                    TextEntry::make('content_deleted_at')
                        ->label('Deleted')
                        ->dateTime()
                        ->visible(fn (MediaModerationItem $record) => (bool) $record->content_deleted_at),
                    TextEntry::make('deletion_notes')
                        ->label('Deletion notes')
                        ->columnSpanFull()
                        ->visible(fn (MediaModerationItem $record) => filled($record->deletion_notes)),
                ]),

            Section::make('Scan issue')
                ->description('This is the public-safe scanner or provider error stored for this file.')
                ->icon('heroicon-o-exclamation-triangle')
                ->columns(3)
                ->visible(fn (MediaModerationItem $record) => filled($record->error_message))
                ->schema([
                    TextEntry::make('scan_status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => static::scanStatusLabel($state))
                        ->color(fn ($state) => static::scanStatusColor($state)),
                    TextEntry::make('error_type')
                        ->label('Error type')
                        ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->headline())
                        ->placeholder('Unknown'),
                    TextEntry::make('error_code')->label('Provider code')->placeholder('—'),
                    TextEntry::make('retryable')
                        ->label('Retry available')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                        ->color(fn ($state) => $state ? 'warning' : 'gray'),
                    TextEntry::make('attempts')->label('Attempts'),
                    TextEntry::make('operations')->label('Operations spent'),
                    TextEntry::make('error_message')->label('Error details')->columnSpanFull(),
                ]),

            Section::make('Provider details')
                ->columns(2)
                ->schema([
                    TextEntry::make('provider'),
                    TextEntry::make('operations'),
                    TextEntry::make('provider_processing_mode')
                        ->label('Processing mode')
                        ->badge()
                        ->formatStateUsing(fn ($state) => str($state)->headline())
                        ->placeholder('—'),
                    TextEntry::make('provider_progress')
                        ->label('Provider progress')
                        ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 1).'%' : '—'),
                    TextEntry::make('provider_request_id')->label('Request ID')->placeholder('—'),
                    TextEntry::make('provider_media_id')->label('Media ID')->placeholder('—'),
                    TextEntry::make('provider_submitted_at')->label('Submitted')->dateTime()->placeholder('—'),
                    TextEntry::make('provider_last_update_at')->label('Provider updated')->dateTime()->placeholder('—'),
                    TextEntry::make('provider_poll_attempts')
                        ->label('Automatic status checks')
                        ->visible(fn (MediaModerationItem $record) => $record->provider_processing_mode === 'async'),
                    TextEntry::make('provider_next_poll_at')
                        ->label('Next status check')
                        ->dateTime()
                        ->placeholder('—')
                        ->visible(fn (MediaModerationItem $record) => $record->provider_processing_mode === 'async'),
                    TextEntry::make('provider_poll_error')
                        ->label('Last status-check issue')
                        ->columnSpanFull()
                        ->visible(fn (MediaModerationItem $record) => filled($record->provider_poll_error)),
                    TextEntry::make('scores')
                        ->columnSpanFull()
                        ->state(fn (MediaModerationItem $record) => static::formatJson($record->scores)),
                    TextEntry::make('provider_response')
                        ->label('Sanitized provider response')
                        ->columnSpanFull()
                        ->state(fn (MediaModerationItem $record) => static::formatJson($record->provider_response)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('media_preview')
                    ->label('Preview')
                    ->view('filament.tables.columns.media-moderation-preview'),
                Tables\Columns\TextColumn::make('media_label')
                    ->label('Media')
                    ->state(fn (MediaModerationItem $record) => static::mediaLabel($record))
                    ->limit(24)
                    ->tooltip(fn (MediaModerationItem $record) => $record->isManagedAsset()
                        ? $record->sourceLabel()
                        : $record->attachment?->filename)
                    ->url(fn (MediaModerationItem $record) => static::mediaUrl($record))
                    ->openUrlInNewTab()
                    ->placeholder('Deleted'),
                Tables\Columns\TextColumn::make('decision')
                    ->badge()
                    ->color(fn ($state) => static::decisionColor($state))
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true))
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_score')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 1).'%' : '—')
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true))
                    ->sortable(),
                Tables\Columns\TextColumn::make('matched_class')
                    ->label('Signal')
                    ->formatStateUsing(fn ($state, MediaModerationItem $record) => $record->decision === MediaModerationItem::DECISION_SAFE
                        ? '—'
                        : str_replace('_', ' ', (string) $state))
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('admin_outcome')
                    ->label('Outcome')
                    ->badge()
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true))
                    ->sortable(),
                Tables\Columns\TextColumn::make('scan_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => static::scanStatusLabel($state))
                    ->color(fn ($state) => static::scanStatusColor($state))
                    ->visible(fn ($livewire) => in_array($livewire->activeTab, ['errors', 'processing'], true)),
                Tables\Columns\TextColumn::make('provider_progress')
                    ->label('Provider progress')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 1).'%' : 'Waiting')
                    ->visible(fn ($livewire) => $livewire->activeTab === 'processing'),
                Tables\Columns\TextColumn::make('error_type')
                    ->label('Issue type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->headline())
                    ->visible(fn ($livewire) => $livewire->activeTab === 'errors'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Stored error')
                    ->limit(55)
                    ->tooltip(fn ($state) => $state)
                    ->visible(fn ($livewire) => $livewire->activeTab === 'errors'),
                Tables\Columns\IconColumn::make('retryable')
                    ->label('Retry')
                    ->boolean()
                    ->visible(fn ($livewire) => $livewire->activeTab === 'errors'),
                Tables\Columns\TextColumn::make('owner.username')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unknown')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Source')
                    ->formatStateUsing(fn ($state, MediaModerationItem $record) => $record->sourceLabel())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_cached')->label('Saved')->boolean()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last activity')
                    ->state(fn (MediaModerationItem $record) => $record->scanned_at
                        ?? $record->failed_at
                        ?? $record->updated_at)
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('decision')->options([
                    MediaModerationItem::DECISION_SAFE => 'No action',
                    MediaModerationItem::DECISION_REVIEW => 'Review',
                    MediaModerationItem::DECISION_VIOLATION => 'Likely violation',
                ]),
                SelectFilter::make('matched_class')
                    ->label('Signal')
                    ->options([
                        'sexual_activity' => 'Explicit activity',
                        'sexual_display' => 'Explicit display',
                        'erotica' => 'Erotic imagery',
                    ]),
                SelectFilter::make('admin_outcome')->label('Admin decision')->options([
                    MediaModerationItem::OUTCOME_UNREVIEWED => 'Unreviewed',
                    MediaModerationItem::OUTCOME_DISMISSED => 'Dismissed',
                    MediaModerationItem::OUTCOME_CONFIRMED => 'Confirmed',
                    MediaModerationItem::OUTCOME_DELETED => 'Deleted',
                    MediaModerationItem::OUTCOME_SUPERSEDED => 'Superseded by new policy',
                ]),
                SelectFilter::make('media_type')->options(['image' => 'Image', 'video' => 'Video']),
                SelectFilter::make('source_type')->label('Media source')->options([
                    MediaModerationItem::SOURCE_ATTACHMENT => 'Attachments',
                    MediaModerationItem::SOURCE_PROFILE_AVATAR => 'Profile avatars',
                    MediaModerationItem::SOURCE_PROFILE_COVER => 'Profile covers',
                    MediaModerationItem::SOURCE_STREAM_POSTER => 'Stream posters',
                ]),
                SelectFilter::make('scan_status')->label('Scan status')->options([
                    MediaModerationItem::STATUS_QUEUED => 'Queued',
                    MediaModerationItem::STATUS_PROCESSING => 'Processing',
                    MediaModerationItem::STATUS_AWAITING_PROVIDER => 'Waiting for provider',
                    MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY => 'Waiting for provider capacity',
                    MediaModerationItem::STATUS_RETRYING => 'Retrying',
                    MediaModerationItem::STATUS_COMPLETED => 'Completed',
                    MediaModerationItem::STATUS_QUOTA_PAUSED => 'Budget paused',
                    MediaModerationItem::STATUS_ACTION_REQUIRED => 'Action required',
                    MediaModerationItem::STATUS_SKIPPED => 'Skipped',
                    MediaModerationItem::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->actions([
                ActionGroup::make(static::tableActions())
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->toolbarActions([
                BulkAction::make('confirmSelected')
                    ->label('Confirm selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->authorize(fn () => auth()->user()?->can('Update:MediaModerationItem') ?? false)
                    ->visible(fn ($livewire) => $livewire->activeTab !== 'confirmed')
                    ->requiresConfirmation()
                    ->form([static::reviewNotesField()])
                    ->action(function (Collection $records, array $data): void {
                        $records->filter->isFlagged()->each(
                            fn (MediaModerationItem $item) => app(ModerationReviewService::class)->confirm(
                                $item,
                                auth()->id(),
                                $data['review_notes'] ?? null,
                            ),
                        );
                        Notification::make()->title('Selected flagged items confirmed')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('dismissSelected')
                    ->label('Dismiss selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->authorize(fn () => auth()->user()?->can('Update:MediaModerationItem') ?? false)
                    ->requiresConfirmation()
                    ->form([static::reviewNotesField()])
                    ->action(function (Collection $records, array $data): void {
                        $records->filter->isFlagged()->each(
                            fn (MediaModerationItem $item) => app(ModerationReviewService::class)->dismiss(
                                $item,
                                auth()->id(),
                                $data['review_notes'] ?? null,
                            ),
                        );
                        Notification::make()->title('Selected flagged items dismissed')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('deleteSelectedMedia')
                    ->label('Delete selected media')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->authorize(fn () => auth()->user()?->can('Update:MediaModerationItem') ?? false)
                    ->visible(fn ($livewire) => $livewire->activeTab === 'confirmed')
                    ->requiresConfirmation()
                    ->modalHeading('Permanently delete the selected media files?')
                    ->modalDescription('Only selected, confirmed media is deleted. Moderation history remains available.')
                    ->form([static::deletionNotesField()])
                    ->action(fn (Collection $records, array $data) => static::deleteSelected(
                        $records,
                        'media',
                        $data['deletion_notes'] ?? null,
                    ))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('deleteSelectedParents')
                    ->label('Delete selected parent content')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->authorize(fn () => auth()->user()?->can('Update:MediaModerationItem') ?? false)
                    ->visible(fn ($livewire) => $livewire->activeTab === 'confirmed')
                    ->requiresConfirmation()
                    ->modalHeading('Delete complete parent content for the selected items?')
                    ->modalDescription('Choose the complete posts, messages, stories, or reels to delete. Shared files and their other uses remain.')
                    ->form([
                        Select::make('parent_targets')->label('Content to delete')->multiple()->required()
                            ->options(fn (Collection $records): array => $records->flatMap(fn (MediaModerationItem $record): array => static::parentOptions($record))->all()),
                        static::deletionNotesField(),
                    ])
                    ->action(fn (Collection $records, array $data) => static::deleteSelectedParents($records, $data))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->striped()
            ->poll('10s')
            ->defaultSort('scanned_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function tableActions(): array
    {
        return array_values(array_filter(
            static::reviewActions(),
            fn (Action $action): bool => $action->getName() !== 'openRun',
        ));
    }

    public static function reviewActions(): array
    {
        return [
            Action::make('openRun')
                ->label('Open scan')
                ->icon('heroicon-o-rectangle-stack')
                ->url(fn ($record) => MediaModerationRunResource::getUrl('view', ['record' => $record->run_id])),
            Action::make('refreshProvider')
                ->label('Check provider status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->authorize('update')
                ->visible(fn (MediaModerationItem $record) => $record->scan_status === MediaModerationItem::STATUS_AWAITING_PROVIDER)
                ->action(function (MediaModerationItem $record): void {
                    try {
                        $record = app(ModerationScanService::class)->refreshAsyncResult($record);
                        $notification = Notification::make()->title(
                            $record->scan_status === MediaModerationItem::STATUS_AWAITING_PROVIDER
                                ? 'Sightengine is still processing this video'
                                : 'The provider result has been received',
                        );

                        if ($record->scan_status === MediaModerationItem::STATUS_AWAITING_PROVIDER) {
                            $notification->info();
                        } else {
                            $notification->success();
                        }

                        $notification->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Provider status could not be refreshed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('dismiss')
                ->label('Dismiss finding')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->authorize('update')
                ->requiresConfirmation()
                ->form([static::reviewNotesField()])
                ->visible(fn (MediaModerationItem $record) => $record->isFlagged()
                    && !$record->content_deleted_at
                    && $record->admin_outcome !== MediaModerationItem::OUTCOME_DISMISSED)
                ->action(function (MediaModerationItem $record, array $data): void {
                    app(ModerationReviewService::class)->dismiss(
                        $record,
                        auth()->id(),
                        $data['review_notes'] ?? null,
                    );
                    Notification::make()->title('Finding dismissed')->success()->send();
                }),
            Action::make('confirm')
                ->label('Confirm violation')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->authorize('update')
                ->requiresConfirmation()
                ->form([static::reviewNotesField()])
                ->visible(fn (MediaModerationItem $record) => $record->isFlagged()
                    && !$record->content_deleted_at
                    && $record->admin_outcome !== MediaModerationItem::OUTCOME_CONFIRMED)
                ->action(function (MediaModerationItem $record, array $data): void {
                    app(ModerationReviewService::class)->confirm(
                        $record,
                        auth()->id(),
                        $data['review_notes'] ?? null,
                    );
                    Notification::make()->title('Finding confirmed')->success()->send();
                }),
            Action::make('deleteMedia')
                ->label(fn (MediaModerationItem $record) => match ($record->source_type) {
                    MediaModerationItem::SOURCE_PROFILE_AVATAR => 'Delete avatar',
                    MediaModerationItem::SOURCE_PROFILE_COVER => 'Delete cover',
                    MediaModerationItem::SOURCE_STREAM_POSTER => 'Delete poster',
                    default => 'Delete this media file',
                })
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading(fn (MediaModerationItem $record) => match ($record->source_type) {
                    MediaModerationItem::SOURCE_PROFILE_AVATAR => 'Delete this profile avatar?',
                    MediaModerationItem::SOURCE_PROFILE_COVER => 'Delete this profile cover?',
                    MediaModerationItem::SOURCE_STREAM_POSTER => 'Delete this stream poster?',
                    default => 'Permanently delete this media file?',
                })
                ->modalDescription(fn (MediaModerationItem $record) => match (true) {
                    $record->isProfileAsset() => 'The exact scanned profile image will be removed and replaced by the site default. Moderation history will remain.',
                    $record->isStreamPoster() => 'The exact scanned stream poster will be removed. Moderation history will remain.',
                    default => 'The file and its generated previews will be removed from every post, message, template, story, reel, and Vault. Moderation history will remain.',
                })
                ->form([static::deletionNotesField()])
                ->visible(fn (MediaModerationItem $record) => $record->admin_outcome === MediaModerationItem::OUTCOME_CONFIRMED
                    && !$record->content_deleted_at
                    && ((bool) $record->attachment
                        || ($record->isProfileAsset()
                            && app(ProfileMediaSourceService::class)->isCurrent($record))
                        || ($record->isStreamPoster()
                            && app(StreamPosterSourceService::class)->isCurrent($record))))
                ->action(fn (MediaModerationItem $record, array $data) => static::delete(
                    $record,
                    'media',
                    $data['deletion_notes'] ?? null,
                )),
            Action::make('deleteParent')
                ->label('Delete parent content')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading('Delete the complete parent content?')
                ->modalDescription('The chosen post, message, story, or reel will be deleted. Shared files, the Vault, and other uses remain.')
                ->form([
                    Select::make('parent_target')->label('Content to delete')->required()
                        ->options(fn (MediaModerationItem $record): array => static::parentOptions($record)),
                    static::deletionNotesField(),
                ])
                ->visible(fn (MediaModerationItem $record) => $record->admin_outcome === MediaModerationItem::OUTCOME_CONFIRMED
                    && !$record->content_deleted_at
                    && static::parentOptions($record) !== [])
                ->action(fn (MediaModerationItem $record, array $data) => static::delete(
                    $record,
                    'parent',
                    $data['deletion_notes'] ?? null,
                    $data['parent_target'] ?? null,
                )),
        ];
    }

    public static function headerActions(): array
    {
        $primaryActions = [];
        $secondaryActions = [];

        foreach (static::reviewActions() as $action) {
            if (in_array($action->getName(), ['dismiss', 'confirm', 'deleteMedia'], true)) {
                $primaryActions[] = $action;

                continue;
            }

            $secondaryActions[] = $action;
        }

        return [
            ...$primaryActions,
            ActionGroup::make($secondaryActions)
                ->label('More actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->tooltip('More actions')
                ->dropdownPlacement('bottom-end')
                ->iconButton(),
        ];
    }

    protected static function formatJson(mixed $state): HtmlString
    {
        $json = json_encode(
            $state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return new HtmlString(
            '<pre class="admin-json-viewer"><code>'.e($json === false ? 'JSON could not be displayed.' : $json).'</code></pre>',
        );
    }

    protected static function subjectUrl(MediaModerationItem $record): ?string
    {
        if ($record->content_deleted_at) {
            return null;
        }

        if ($record->isProfileAsset()) {
            $userId = $record->subject_id ?: $record->source_id;

            return $userId ? UserResource::getUrl('view', ['record' => $userId]) : null;
        }

        if ($record->isStreamPoster()) {
            $streamId = $record->subject_id ?: $record->source_id;

            return $streamId ? StreamResource::getUrl('view', ['record' => $streamId]) : null;
        }

        if (!$record->subject_id) {
            return null;
        }

        return match ($record->subject_type) {
            'post' => PostResource::getUrl('view', ['record' => $record->subject_id]),
            'message' => UserMessageResource::getUrl('view', ['record' => $record->subject_id]),
            'story' => StoryResource::getUrl('view', ['record' => $record->subject_id]),
            'reel' => ReelResource::getUrl('view', ['record' => $record->subject_id]),
            'stream' => StreamResource::getUrl('view', ['record' => $record->subject_id]),
            default => null,
        };
    }

    protected static function previewUrl(MediaModerationItem $record): ?string
    {
        if ($record->isProfileAsset()) {
            return app(ProfileMediaSourceService::class)->previewUrl($record);
        }

        if ($record->isStreamPoster()) {
            return app(StreamPosterSourceService::class)->previewUrl($record);
        }

        return $record->attachment && FileAttachmentService::isFile($record->attachment)
            ? null
            : ($record->media_type === 'image'
            ? $record->attachment?->thumbnail
            : null);
    }

    protected static function mediaUrl(MediaModerationItem $record): ?string
    {
        if ($record->isProfileAsset()) {
            return app(ProfileMediaSourceService::class)->previewUrl($record);
        }

        if ($record->isStreamPoster()) {
            return app(StreamPosterSourceService::class)->previewUrl($record);
        }

        return $record->attachment ? AttachmentPresentation::url($record->attachment) : null;
    }

    protected static function mediaLabel(MediaModerationItem $record): ?string
    {
        return match ($record->source_type) {
            MediaModerationItem::SOURCE_PROFILE_AVATAR => 'Avatar',
            MediaModerationItem::SOURCE_PROFILE_COVER => 'Cover',
            MediaModerationItem::SOURCE_STREAM_POSTER => 'Stream poster',
            default => $record->attachment ? AttachmentPresentation::data($record->attachment)['name'] : null,
        };
    }

    protected static function reviewNotesField(): Textarea
    {
        return Textarea::make('review_notes')
            ->label('Internal review notes')
            ->helperText('Optional. Visible only to administrators in the moderation history.')
            ->maxLength(2000)
            ->rows(3);
    }

    protected static function deletionNotesField(): Textarea
    {
        return Textarea::make('deletion_notes')
            ->label('Internal deletion notes')
            ->helperText('Optional. Saved with the deletion audit trail.')
            ->maxLength(2000)
            ->rows(3);
    }

    protected static function delete(MediaModerationItem $record, string $target, ?string $notes = null, ?string $parentTarget = null): void
    {
        try {
            app(ModerationDeletionService::class)->deleteConfirmed(
                $record,
                $target,
                auth()->id(),
                $notes,
                $parentTarget,
            );
            $title = $target === 'parent'
                ? 'Parent content deleted'
                : match ($record->source_type) {
                    MediaModerationItem::SOURCE_PROFILE_AVATAR => 'Profile avatar deleted',
                    MediaModerationItem::SOURCE_PROFILE_COVER => 'Profile cover deleted',
                    MediaModerationItem::SOURCE_STREAM_POSTER => 'Stream poster deleted',
                    default => 'Media file deleted',
                };
            Notification::make()->title($title)->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Content could not be deleted')->body($exception->getMessage())->danger()->send();
        }
    }

    protected static function parentOptions(MediaModerationItem $record): array
    {
        if (!$record->attachment || $record->admin_outcome !== MediaModerationItem::OUTCOME_CONFIRMED) {
            return [];
        }

        return app(AttachmentMediaService::class)->usages($record->attachment)
            ->filter(fn (array $usage): bool => in_array($usage['type'], ['post', 'message', 'story', 'reel'], true)
                && Gate::allows('delete', $usage['parent']))
            ->mapWithKeys(fn (array $usage): array => [$usage['type'].':'.$usage['id'] => ucfirst($usage['type']).' #'.$usage['id']])
            ->all();
    }

    protected static function deleteSelectedParents(Collection $records, array $data): void
    {
        $deleted = 0;
        $failed = 0;

        foreach (array_unique($data['parent_targets'] ?? []) as $target) {
            try {
                $item = $records->first(fn (MediaModerationItem $record): bool => array_key_exists($target, static::parentOptions($record)));

                if (!$item) {
                    throw new \DomainException('Selected content is no longer available for deletion.');
                }

                app(ModerationDeletionService::class)->deleteConfirmed($item, 'parent', auth()->id(), $data['deletion_notes'] ?? null, $target);
                $deleted++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $notification = Notification::make()->title($deleted.' content items deleted')
            ->body($failed ? $failed.' selections could not be deleted. Refresh the findings.' : 'Shared media and other uses were retained.');
        $failed ? $notification->warning() : $notification->success();
        $notification->send();
    }

    protected static function deleteSelected(Collection $records, string $target, ?string $notes = null): void
    {
        $targets = $records
            ->filter(fn (MediaModerationItem $item): bool => $item->admin_outcome === MediaModerationItem::OUTCOME_CONFIRMED
                && !$item->content_deleted_at
                && ($target !== 'parent' || ($item->subject_id
                    && in_array($item->subject_type, ['post', 'message', 'story', 'reel'], true))))
            ->unique(fn (MediaModerationItem $item): string => $target === 'parent'
                ? $item->subject_type.':'.$item->subject_id
                : ($item->attachment_id
                    ?: implode(':', [
                        (string) $item->source_type,
                        (string) $item->source_id,
                        (string) $item->source_path,
                    ])))
            ->values();
        $deleted = 0;
        $failed = 0;

        foreach ($targets as $item) {
            $item = $item->fresh();

            if (!$item
                || $item->admin_outcome !== MediaModerationItem::OUTCOME_CONFIRMED
                || $item->content_deleted_at) {
                continue;
            }

            try {
                app(ModerationDeletionService::class)->deleteConfirmed(
                    $item,
                    $target,
                    auth()->id(),
                    $notes,
                );
                $deleted++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $covered = max(0, $records->count() - $deleted - $failed);
        $targetLabel = $target === 'parent' ? 'parent content item' : 'media file';
        $notification = Notification::make()
            ->title($deleted > 0
                ? $deleted.' selected '.Str::plural($targetLabel, $deleted).' deleted'
                : 'No eligible selected content was deleted');
        $details = array_filter([
            $covered > 0 ? $covered.' already covered or ineligible' : null,
            $failed > 0 ? $failed.' failed' : null,
        ]);

        if ($details !== []) {
            $notification->body(implode(' · ', $details));
        }

        if ($failed > 0) {
            $notification->warning();
        } elseif ($deleted > 0) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }

    public static function decisionColor(?string $decision): string
    {
        return match ($decision) {
            MediaModerationItem::DECISION_SAFE => 'success',
            MediaModerationItem::DECISION_REVIEW => 'warning',
            MediaModerationItem::DECISION_VIOLATION => 'danger',
            default => 'gray',
        };
    }

    public static function scanStatusColor(?string $status): string
    {
        return match ($status) {
            MediaModerationItem::STATUS_COMPLETED => 'success',
            MediaModerationItem::STATUS_QUOTA_PAUSED,
            MediaModerationItem::STATUS_ACTION_REQUIRED,
            MediaModerationItem::STATUS_RETRYING => 'warning',
            MediaModerationItem::STATUS_FAILED => 'danger',
            MediaModerationItem::STATUS_SKIPPED => 'gray',
            default => 'info',
        };
    }

    public static function scanStatusLabel(?string $status): string
    {
        return $status === MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY
            ? 'Waiting for provider capacity'
            : (string) str($status ?: 'unknown')->replace('_', ' ')->headline();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaModerationItems::route('/'),
            'view' => ViewMediaModerationItem::route('/{record}'),
        ];
    }
}
