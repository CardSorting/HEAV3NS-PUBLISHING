<?php

namespace App\Filament\Resources\ContentReviews;

use App\Filament\Resources\ContentReviews\Pages\ListContentReviews;
use App\Filament\Resources\ContentReviews\Pages\ViewContentReview;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Resources\MediaModerationRuns\MediaModerationRunResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\Users\UserResource;
use App\Model\ContentReview;
use App\Model\MediaModerationRun;
use App\Model\Post;
use App\Model\Reel;
use App\Model\Story;
use App\Model\User;
use App\Services\Moderation\PublicationReviewService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use UnitEnum;
use Throwable;

class ContentReviewResource extends Resource
{
    use DisplaysRecordActions;

    protected static ?string $model = ContentReview::class;

    protected static UnitEnum|string|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 17;

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getModelLabel(): string
    {
        return __('Content approval');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Content approvals');
    }

    public static function getNavigationBadge(): ?string
    {
        // The admin panel must load before Admin -> Update creates this table.
        if (!SchemaFacade::hasTable('content_reviews')) {
            return null;
        }

        $pending = ContentReview::query()->where('status', ContentReview::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Pending review');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['owner', 'reviewer', 'mediaRun', 'textRun']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label(__('ID'))->sortable(),
                TextColumn::make('source_type')->label(__('Content'))->badge()
                    ->formatStateUsing(fn ($state) => static::sourceLabel((string) $state)),
                TextColumn::make('source_id')->label(__('Content ID'))->sortable(),
                TextColumn::make('owner.username')->label(__('Creator'))->searchable(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn ($state) => static::statusLabel((string) $state))
                    ->color(fn ($state) => static::statusColor((string) $state)),
                TextColumn::make('mode')->label(__('Review mode'))
                    ->formatStateUsing(fn ($state) => static::modeLabel((string) $state)),
                TextColumn::make('attention_reason')->label(__('Needs attention'))
                    ->state(fn (ContentReview $record) => PublicationReviewService::attentionLabel($record))
                    ->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('created_at')->label(__('Submitted'))->dateTime()->sortable(),
                TextColumn::make('reviewed_at')->label(__('Reviewed'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source_type')->label(__('Content'))->options(static::sourceOptions()),
                SelectFilter::make('mode')->label(__('Review mode'))->options(static::modeOptions()),
            ])
            ->recordActions(static::getRecordActionsForDisplay())
            ->toolbarActions([static::bulkReviewAction(true), static::bulkReviewAction(false)])
            ->recordUrl(fn (ContentReview $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 2])->components([
            Section::make(__('Current content'))
                ->description(__('Current preview. Previous versions aren’t saved.'))
                ->schema([
                    TextEntry::make('content_missing')->label(__('Content'))->state(__('This content has been deleted.'))
                        ->visible(fn (ContentReview $record) => $record->content() === null),
                    TextEntry::make('content_text')->label(__('Text'))
                        ->state(fn (ContentReview $record) => $record->content()?->getAttribute($record->source_type === 'reel' ? 'caption' : 'text'))
                        ->placeholder(__('No text')),
                    ViewEntry::make('content_media')->label(__('Media'))
                        ->state(fn (ContentReview $record) => static::mediaItems($record))
                        ->view('filament.infolists.user-report-media')
                        ->visible(fn (ContentReview $record) => static::mediaItems($record) !== []),
                    TextEntry::make('content_link')->label(__('Link'))
                        ->state(fn (ContentReview $record) => $record->content()?->getAttribute('link_url'))
                        ->visible(fn (ContentReview $record) => filled($record->content()?->getAttribute('link_url'))),
                    TextEntry::make('content_link_text')->label(__('Link text'))
                        ->state(fn (ContentReview $record) => $record->content()?->getAttribute('link_text'))
                        ->visible(fn (ContentReview $record) => filled($record->content()?->getAttribute('link_text'))),
                    TextEntry::make('content_overlay')->label(__('Overlay'))
                        ->state(fn (ContentReview $record) => json_encode($record->content()?->getAttribute('overlay'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->visible(fn (ContentReview $record) => filled($record->content()?->getAttribute('overlay'))),
                    TextEntry::make('poll_answers')->label(__('Poll answers'))->listWithLineBreaks()->bulleted()
                        ->state(fn (ContentReview $record) => static::pollAnswers($record))
                        ->visible(fn (ContentReview $record) => static::pollAnswers($record) !== []),
                    ViewEntry::make('sound_media')->label(__('Soundtrack'))
                        ->state(fn (ContentReview $record) => static::soundItems($record))
                        ->view('filament.infolists.user-report-media')
                        ->visible(fn (ContentReview $record) => static::soundItems($record) !== []),
                ]),
            Group::make([
                Section::make(__('Submission'))
                    ->columns(['default' => 2, 'xl' => 3])
                    ->schema([
                        TextEntry::make('status')->label(__('Status'))->badge()
                            ->formatStateUsing(fn ($state) => static::statusLabel((string) $state))
                            ->color(fn ($state) => static::statusColor((string) $state)),
                        TextEntry::make('source_type')->label(__('Content'))
                            ->formatStateUsing(fn ($state) => static::sourceLabel((string) $state)),
                        TextEntry::make('source_id')->label(__('Content ID')),
                        TextEntry::make('owner.username')->label(__('Creator'))->placeholder(__('Deleted user'))
                            ->url(fn (ContentReview $record) => $record->owner ? UserResource::getUrl('view', ['record' => $record->owner]) : null),
                        TextEntry::make('created_at')->label(__('Submitted'))->dateTime(),
                        TextEntry::make('mode')->label(__('Review mode'))
                            ->formatStateUsing(fn ($state) => static::modeLabel((string) $state)),
                        TextEntry::make('attention_reason')->label(__('Needs attention'))->columnSpanFull()
                            ->state(fn (ContentReview $record) => PublicationReviewService::attentionLabel($record))
                            ->visible(fn (ContentReview $record) => filled(PublicationReviewService::attentionLabel($record))),
                    ]),
                Section::make(__('Scan results'))
                    ->description(__('AI scans provide evidence. Approving content does not dismiss its AI findings.'))
                    ->schema([
                        static::scanResultEntry('mediaRun', __('Media scan')),
                        static::scanResultEntry('textRun', __('Text scan')),
                    ]),
                Section::make(__('Decision'))
                    ->columns(2)
                    ->visible(fn (ContentReview $record) => $record->reviewed_at !== null)
                    ->schema([
                        TextEntry::make('rejection_reason')->label(__('Reason shown to creator'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('review_notes')->label(__('Private review notes'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('reviewer.username')->label(__('Reviewed by'))->placeholder('—'),
                        TextEntry::make('reviewed_at')->label(__('Reviewed'))->dateTime()->placeholder('—'),
                        TextEntry::make('decision_source')->label(__('Decision source'))->placeholder('—'),
                    ]),
                Section::make(__('Previous decisions'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('previous_decisions')
                            ->hiddenLabel()
                            ->placeholder(__('No previous decisions.'))
                            ->state(fn (ContentReview $record) => ContentReview::query()
                                ->with('reviewer')
                                ->where('source_type', $record->source_type)->where('source_id', $record->source_id)
                                ->where('id', '<>', $record->id)->latest('id')->limit(20)->get())
                            ->columns(3)
                            ->schema([
                                TextEntry::make('id')->label(__('Review')),
                                TextEntry::make('status')->label(__('Status'))->badge()
                                    ->formatStateUsing(fn ($state) => static::statusLabel((string) $state))
                                    ->color(fn ($state) => static::statusColor((string) $state)),
                                TextEntry::make('created_at')->label(__('Submitted'))->dateTime(),
                                TextEntry::make('reviewer.username')->label(__('Reviewed by'))->placeholder('—'),
                                TextEntry::make('reviewed_at')->label(__('Reviewed'))->dateTime()->placeholder('—'),
                                TextEntry::make('decision_source')->label(__('Decision source'))->placeholder('—'),
                                TextEntry::make('rejection_reason')->label(__('Reason shown to creator'))->placeholder('—')->columnSpanFull(),
                                TextEntry::make('review_notes')->label(__('Private review notes'))->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function getRecordActions(): array
    {
        return [...static::decisionActions(), ...static::contentLinkActions()];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal');
    }

    public static function decisionActions(?string $fingerprint = null): array
    {
        return [
            Action::make('approve')->label(__('Approve'))->color('success')->icon('heroicon-o-check')
                ->authorize('update')
                ->visible(fn (ContentReview $record) => static::canApproveReview($record))
                ->requiresConfirmation()
                ->modalDescription(fn (ContentReview $record) => $record->status === ContentReview::STATUS_REJECTED
                    ? __('Approve this rejected content? The rejection stays in history.') : null)
                ->form([Textarea::make('review_notes')->label(__('Private review notes'))->maxLength(4000)])
                ->action(function (ContentReview $record, array $data, ?Component $livewire = null) use ($fingerprint): void {
                    Gate::authorize('update', $record);
                    $service = app(PublicationReviewService::class);
                    $expected = $fingerprint ?? (string) $service->normalizeLegacy($record)->fingerprint;
                    $approved = static::decide(fn () => $service->approve($record, auth()->user(), $expected, $data['review_notes'] ?? null));
                    $record->refresh();
                    Notification::make()->title(__('Content approved'))->success()->send();
                    if ($livewire instanceof ViewContentReview && $approved->id !== $record->id) {
                        $livewire->redirect(static::getUrl('view', ['record' => $approved]));
                    }
                }),
            Action::make('reject')->label(__('Reject'))->color('danger')->icon('heroicon-o-x-mark')
                ->authorize('update')
                ->visible(fn (ContentReview $record) => $record->status === 'pending')
                ->form([
                    Textarea::make('reason')->label(__('Reason shown to creator'))->required()->maxLength(2000),
                    Textarea::make('review_notes')->label(__('Private review notes'))->maxLength(4000),
                ])
                ->action(function (ContentReview $record, array $data) use ($fingerprint): void {
                    Gate::authorize('update', $record);
                    $service = app(PublicationReviewService::class);
                    $expected = $fingerprint ?? (string) $service->normalizeLegacy($record)->fingerprint;
                    static::decide(fn () => $service->reject($record, auth()->user(), $data['reason'], $expected, $data['review_notes'] ?? null));
                    $record->refresh();
                    Notification::make()->title(__('Content rejected'))->success()->send();
                }),
        ];
    }

    public static function canApproveReview(ContentReview $record): bool
    {
        if ($record->status === ContentReview::STATUS_PENDING) {
            return true;
        }
        if ($record->status !== ContentReview::STATUS_REJECTED) {
            return false;
        }
        $model = match ($record->source_type) {
            'post' => Post::class, 'reel' => Reel::class, 'story' => Story::class, default => null,
        };

        return $model && $model::query()->whereKey($record->source_id)
            ->whereHas('contentReview', fn (Builder $query) => $query->whereKey($record->id))->exists();
    }

    public static function reviewActions(string $fingerprint): array
    {
        return [
            ...static::decisionActions($fingerprint),
            ActionGroup::make([
                Action::make('retry')->label(__('Retry scans'))->color('gray')->icon('heroicon-o-arrow-path')
                    ->authorize('update')
                    ->visible(fn (ContentReview $record) => $record->status === 'pending' && $record->mode !== 'manual')
                    ->requiresConfirmation()
                    ->action(function (ContentReview $record, ViewContentReview $livewire): void {
                        Gate::authorize('update', $record);
                        $retry = static::decide(fn () => app(PublicationReviewService::class)->retry($record, auth()->user()));
                        Notification::make()->title(__('Scans queued'))->success()->send();
                        $livewire->redirect(static::getUrl('view', ['record' => $retry]));
                    }),
                ...static::contentLinkActions(),
            ])->label(__('Show more'))->color('gray')
                ->icon('heroicon-o-ellipsis-horizontal')->iconButton()->dropdownPlacement('bottom-end'),
        ];
    }

    public static function contentLinkActions(): array
    {
        return [
            Action::make('openAdminContent')->label(__('View in admin'))->icon('heroicon-o-window')
                ->url(fn (ContentReview $record) => static::contentUrl($record))
                ->visible(fn (ContentReview $record) => static::contentUrl($record) !== null)
                ->openUrlInNewTab(),
            Action::make('openPublicContent')->label(__('View on site'))->icon('heroicon-o-globe-alt')
                ->url(fn (ContentReview $record) => static::publicContentUrl($record))
                ->visible(fn (ContentReview $record) => static::publicContentUrl($record) !== null)
                ->openUrlInNewTab(),
        ];
    }

    public static function bulkReviewAction(bool $approved): BulkAction
    {
        $fields = [];
        if (!$approved) {
            $fields[] = Textarea::make('reason')->label(__('Reason shown to creator'))->required()->maxLength(2000);
        }
        $fields[] = Textarea::make('review_notes')->label(__('Private review notes'))->maxLength(4000);

        return BulkAction::make($approved ? 'approveSelected' : 'rejectSelected')
            ->label($approved ? __('Approve selected') : __('Reject selected'))
            ->icon($approved ? 'heroicon-o-check' : 'heroicon-o-x-mark')
            ->color($approved ? 'success' : 'danger')
            ->authorize(fn () => auth()->user()?->can('Update:ContentReview') ?? false)
            ->requiresConfirmation()
            ->modalDescription($approved
                ? __('Approve pending or rejected submissions. Scheduled posts keep their release date.')
                : __('Reject the selected pending submissions using the same reason for every creator.'))
            ->form($fields)
            ->chunkSelectedRecords(100)
            ->databaseTransaction(false)
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(fn (BulkAction $action) => trans_choice(
                $approved ? ':count submission approved.|:count submissions approved.' : ':count submission rejected.|:count submissions rejected.',
                $action->getTotalSelectedRecordsCount(),
            ))
            ->failureNotificationTitle(fn (int $successCount, int $totalCount) => trans_choice(
                $approved ? 'Approved :success of :total submission.|Approved :success of :total submissions.' : 'Rejected :success of :total submission.|Rejected :success of :total submissions.',
                $totalCount,
                ['success' => $successCount, 'total' => $totalCount],
            ))
            ->action(function (BulkAction $action, Collection|LazyCollection $records, array $data) use ($approved): void {
                $actor = auth()->user();
                if (!$actor instanceof User || !$actor->can('Update:ContentReview')) {
                    throw new AuthorizationException();
                }
                $rules = ['review_notes' => 'nullable|string|max:4000'];
                if (!$approved) {
                    $rules['reason'] = 'required|string|max:2000';
                }
                $data = validator($data, $rules)->validate();
                $service = app(PublicationReviewService::class);
                $reportedUnexpectedFailure = false;

                foreach ($records as $record) {
                    try {
                        Gate::authorize('update', $record);
                        if ($record->status !== ContentReview::STATUS_PENDING
                            && !($approved && $record->status === ContentReview::STATUS_REJECTED)) {
                            $action->reportBulkProcessingFailure('not_pending', __('Some selected submissions are no longer pending.'));

                            continue;
                        }
                        $record = $service->normalizeLegacy($record);
                        if ($approved) {
                            $service->approve($record, $actor, $record->fingerprint, $data['review_notes'] ?? null);
                        } else {
                            $service->reject($record, $actor, $data['reason'], $record->fingerprint, $data['review_notes'] ?? null);
                        }
                    } catch (AuthorizationException) {
                        $action->reportBulkProcessingFailure('permission', __('Some selected submissions could not be reviewed because you do not have permission.'));
                    } catch (ValidationException|ModelNotFoundException) {
                        $action->reportBulkProcessingFailure('changed', __('Some submissions changed or are no longer available. Refresh the list before trying again.'));
                    } catch (Throwable $exception) {
                        $action->reportBulkProcessingFailure('failed', __('Some submissions could not be reviewed. Try again or review them individually.'));
                        if (!$reportedUnexpectedFailure) {
                            report($exception);
                            $reportedUnexpectedFailure = true;
                        }
                    }
                }
            });
    }

    public static function decide(callable $decision): ContentReview
    {
        try {
            return $decision();
        } catch (ValidationException $exception) {
            Notification::make()->title(__('Review could not be completed'))
                ->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            throw $exception;
        }
    }

    public static function mediaItems(ContentReview $review): array
    {
        $content = $review->content();

        if (!$content instanceof Post && !$content instanceof Reel && !$content instanceof Story) {
            return [];
        }

        return $content->attachments->map(fn ($attachment): array => [
            'type' => (string) $attachment->getAttribute('attachmentType'),
            'path' => (string) $attachment->getAttribute('path'),
            'thumbnail' => $attachment->getAttribute('has_thumbnail') ? (string) $attachment->getAttribute('thumbnail') : null,
            'name' => basename((string) $attachment->getAttribute('filename')),
        ])->all();
    }

    public static function scanResultEntry(string $relationship, string $label): RepeatableEntry
    {
        return RepeatableEntry::make($relationship.'_result')
            ->label($label)
            ->state(fn (ContentReview $record) => $record->{$relationship} ? [$record->{$relationship}] : [])
            ->placeholder(__('No scan recorded'))
            ->contained(false)
            ->columns(['default' => 2, 'md' => 4])
            ->schema([
                TextEntry::make('status')->hiddenLabel()->badge()->columnSpanFull()
                    ->formatStateUsing(fn ($state) => __(MediaModerationRunResource::statusLabel($state)))
                    ->color(fn ($state) => MediaModerationRunResource::statusColor($state))
                    ->url(fn (MediaModerationRun $record) => Gate::allows('view', $record)
                        ? MediaModerationRunResource::getUrl('view', ['record' => $record]) : null),
                TextEntry::make('processed_items')->label(__('Processed'))
                    ->formatStateUsing(fn (MediaModerationRun $record) => (int) $record->processed_items.' / '.(int) $record->total_items),
                TextEntry::make('flagged')->label(__('Flagged'))
                    ->state(fn (MediaModerationRun $record) => (int) $record->review_items + (int) $record->violation_items)
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextEntry::make('failed_items')->label(__('Failed'))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextEntry::make('skipped_items')->label(__('Skipped'))
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
            ]);
    }

    public static function pollAnswers(ContentReview $review): array
    {
        $content = $review->content();

        return $content instanceof Post ? ($content->poll()->first()?->answers()->orderBy('id')->pluck('answer')->all() ?? []) : [];
    }

    public static function soundItems(ContentReview $review): array
    {
        $content = $review->content();
        if (!$content instanceof Reel && !$content instanceof Story) {
            return [];
        }
        $audio = $content->sound?->audioAttachment;
        if (!$audio) {
            return [];
        }

        return [[
            'type' => 'audio', 'path' => (string) $audio->path,
            'thumbnail' => null, 'name' => (string) $content->sound->title,
        ]];
    }

    public static function sourceOptions(): array
    {
        return ['post' => __('Post'), 'reel' => __('Reel'), 'story' => __('Story')];
    }

    public static function sourceLabel(string $source): string
    {
        return static::sourceOptions()[$source] ?? $source;
    }

    public static function modeOptions(): array
    {
        return ['manual' => __('Manual review'), 'assisted' => __('AI-assisted review'), 'automatic' => __('Automatic screening')];
    }

    public static function modeLabel(string $mode): string
    {
        return static::modeOptions()[$mode] ?? $mode;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => __('Pending review'), 'approved' => __('Approved'),
            'rejected' => __('Rejected'), 'superseded' => __('Superseded'),
            'cancelled' => __('Cancelled'), default => $status,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', default => 'gray',
        };
    }

    public static function contentUrl(ContentReview $review): ?string
    {
        $resource = match ($review->source_type) {
            'post' => PostResource::class, 'reel' => ReelResource::class, 'story' => StoryResource::class, default => null,
        };
        $content = $review->content();

        return $resource && $content && $resource::canView($content)
            ? $resource::getUrl('view', ['record' => $content]) : null;
    }

    public static function publicContentUrl(ContentReview $review): ?string
    {
        $content = $review->content();
        if (!$content || (!PublicationReviewService::isApproved($content)
            && !PublicationReviewService::canPreview($content, auth()->user()))) {
            return null;
        }

        return match (true) {
            $content instanceof Post => filled($content->user?->username)
                ? route('posts.get', ['post_id' => $content->id, 'username' => $content->user->username]) : null,
            $content instanceof Reel => route('reels.get', ['reel_id' => $content->id]),
            default => !$content->expires_at || $content->expires_at->isFuture()
                ? route('stories.share', ['story' => $content->id]) : null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentReviews::route('/'),
            'view' => ViewContentReview::route('/{record}'),
        ];
    }
}
