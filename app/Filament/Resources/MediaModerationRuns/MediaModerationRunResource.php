<?php

namespace App\Filament\Resources\MediaModerationRuns;

use App\Filament\Pages\Settings\ManageComplianceSettings;
use App\Filament\Resources\ContentReviews\ContentReviewResource;
use App\Filament\Resources\MediaModerationItems\MediaModerationItemResource;
use App\Filament\Resources\MediaModerationRuns\Pages\ListMediaModerationRuns;
use App\Filament\Resources\MediaModerationRuns\Pages\ViewMediaModerationRun;
use App\Filament\Resources\TextModerationItems\TextModerationItemResource;
use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Model\ContentReview;
use App\Model\TextModerationItem;
use App\Services\Moderation\ModerationAttachmentQuery;
use App\Services\Moderation\ModerationJobDispatcher;
use App\Services\Moderation\ModerationRunService;
use App\Services\Moderation\ModerationRunProgressService;
use App\Services\Moderation\ModerationConfiguration;
use App\Services\Moderation\TextModerationRunService;
use App\Services\Moderation\TextModerationSourceService;
use App\Services\Queue\QueueRuntimeService;
use App\Settings\ModerationSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as RepeatableTableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

class MediaModerationRunResource extends Resource
{
    protected static ?string $model = MediaModerationRun::class;

    protected static UnitEnum|string|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 17;

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getModelLabel(): string
    {
        return __('AI scan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('AI scans');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('contentReview')->withExists([
            'items as has_retryable_failed_media_items' => fn (Builder $query) => $query
                ->where('scan_status', MediaModerationItem::STATUS_FAILED)
                ->where('retryable', true),
        ]);

        if (SchemaFacade::hasTable('text_moderation_items')) {
            $query->withExists([
                'textItems as has_retryable_failed_text_items' => fn (Builder $query) => $query
                    ->where('scan_status', TextModerationItem::STATUS_FAILED)
                    ->where('retryable', true),
            ]);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Scan summary')
                ->poll('10s')
                ->icon('heroicon-o-chart-bar-square')
                ->columns([
                    'default' => 2,
                    'md' => 4,
                ])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => static::statusLabel($state))
                        ->color(fn ($state) => static::statusColor($state)),
                    TextEntry::make('scan_type')
                        ->label('Type')
                        ->badge()
                        ->formatStateUsing(fn ($state) => str($state)->headline()),
                    TextEntry::make('origin')->label(__('Origin'))
                        ->state(fn (MediaModerationRun $record) => static::originLabel($record)),
                    static::contentApprovalEntry(),
                    TextEntry::make('execution_mode')->label('Queue mode')->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->headline()),
                    TextEntry::make('processed_items')
                        ->label('Items')
                        ->formatStateUsing(fn (MediaModerationRun $record) => static::itemProgressLabel($record)),
                    TextEntry::make('provider_video_queue')
                        ->label('Provider video queue')
                        ->state(function (MediaModerationRun $record): string {
                            $processing = $record->items()
                                ->where('scan_status', MediaModerationItem::STATUS_AWAITING_PROVIDER)
                                ->where('provider_processing_mode', 'async')
                                ->count();
                            $waiting = $record->items()
                                ->where('scan_status', MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY)
                                ->count();

                            return number_format($processing).' processing · '.number_format($waiting).' waiting';
                        })
                        ->visible(fn (MediaModerationRun $record): bool => $record->isMedia() && $record->items()
                            ->where('scan_status', MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY)
                            ->exists()),
                    TextEntry::make('operations_used')
                        ->label('Operations')
                        ->formatStateUsing(fn ($state, $record) => $state.' / '.$record->max_operations),
                    TextEntry::make('safe_items')->label('No action'),
                    TextEntry::make('review_items')->label('Review'),
                    TextEntry::make('violation_items')->label('Likely violations'),
                    TextEntry::make('failed_items')->label('Failed'),
                    TextEntry::make('reused_items')->label('Reused results'),
                    TextEntry::make('skipped_items')->label('Skipped'),
                    TextEntry::make('initiator.username')->label('Started by')->placeholder('System'),
                    TextEntry::make('created_at')->label('Created')->dateTime(),
                    TextEntry::make('error')->label('Scan error')->columnSpanFull()->visible(fn ($record) => filled($record->error)),
                ]),

            Section::make('Scan issues')
                ->description('The scanner stores a public-safe error for every affected item. Open an item for its complete scan details.')
                ->icon('heroicon-o-exclamation-triangle')
                ->columnSpanFull()
                ->visible(fn (MediaModerationRun $record) => filled($record->error)
                    || $record->failed_items > 0
                    || $record->skipped_items > 0
                    || in_array($record->status, [
                        MediaModerationRun::STATUS_QUOTA_PAUSED,
                        MediaModerationRun::STATUS_ACTION_REQUIRED,
                    ], true))
                ->schema([
                    RepeatableEntry::make('issue_items')
                        ->label('Affected items')
                        ->state(fn (MediaModerationRun $record) => ($record->isText() ? $record->textItems() : $record->items())
                            ->whereIn('scan_status', [
                                MediaModerationItem::STATUS_FAILED,
                                MediaModerationItem::STATUS_QUOTA_PAUSED,
                                MediaModerationItem::STATUS_ACTION_REQUIRED,
                                MediaModerationItem::STATUS_SKIPPED,
                            ])
                            ->latest('updated_at')
                            ->limit(10)
                            ->get()
                            ->map(fn ($item): array => [
                                'scan_status' => $item->scan_status,
                                'error_type' => $item->error_type,
                                'error_message' => $item->error_message,
                                'retryable' => $item->retryable,
                                'item_url' => $record->isText()
                                    ? TextModerationItemResource::getUrl('view', ['record' => $item->id])
                                    : MediaModerationItemResource::getUrl('view', ['record' => $item->id]),
                            ]))
                        ->table([
                            RepeatableTableColumn::make('Status')->width('8rem'),
                            RepeatableTableColumn::make('Type')->width('10rem'),
                            RepeatableTableColumn::make('Stored error'),
                            RepeatableTableColumn::make('Retry')->width('6rem'),
                            RepeatableTableColumn::make('Item')->width('5rem'),
                        ])
                        ->schema([
                            TextEntry::make('scan_status')
                                ->hiddenLabel()
                                ->badge()
                                ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->headline())
                                ->color(fn ($state) => MediaModerationItemResource::scanStatusColor($state)),
                            TextEntry::make('error_type')
                                ->hiddenLabel()
                                ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->headline())
                                ->placeholder('Unknown'),
                            TextEntry::make('error_message')
                                ->hiddenLabel()
                                ->placeholder('No additional error details were stored.'),
                            TextEntry::make('retryable')
                                ->hiddenLabel()
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state ? 'Available' : 'No')
                                ->color(fn ($state) => $state ? 'warning' : 'gray'),
                            TextEntry::make('item_url')
                                ->hiddenLabel()
                                ->formatStateUsing(fn () => 'Open')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('primary')
                                ->url(fn ($state) => $state),
                        ])
                        ->columnSpanFull(),
                ]),

            Section::make('Configuration snapshot')
                ->columns(2)
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('models')->formatStateUsing(fn ($state) => implode(', ', (array) $state)),
                    TextEntry::make('scope.sources')->label('Sources')->formatStateUsing(fn ($state) => implode(', ', (array) $state)),
                    TextEntry::make('scope.media_types')
                        ->label('Media types')
                        ->formatStateUsing(fn ($state) => implode(', ', (array) $state))
                        ->visible(fn (MediaModerationRun $record) => $record->isMedia()),
                    TextEntry::make('policy.languages')
                        ->label('Languages')
                        ->formatStateUsing(fn ($state) => implode(', ', (array) $state))
                        ->visible(fn (MediaModerationRun $record) => $record->isText()),
                    TextEntry::make('policy.flag_classes')
                        ->label('Signals')
                        ->formatStateUsing(fn ($state) => implode(', ', (array) $state)),
                    TextEntry::make('policy.video_processing_mode')
                        ->label('Video processing')
                        ->formatStateUsing(fn ($state) => str($state)->headline())
                        ->visible(fn (MediaModerationRun $record) => $record->isMedia()),
                    TextEntry::make('policy.video_interval_seconds')
                        ->label('Sampling interval')
                        ->formatStateUsing(fn ($state) => is_numeric($state) && (float) $state > 0
                            ? 'Every '.rtrim(rtrim(number_format((float) $state, 1), '0'), '.').' seconds (custom)'
                            : 'Provider default (~2 seconds)')
                        ->visible(fn (MediaModerationRun $record) => $record->isMedia()),
                    TextEntry::make('policy.max_video_duration_seconds')
                        ->label('Maximum video duration')
                        ->suffix(' seconds')
                        ->visible(fn (MediaModerationRun $record) => $record->isMedia()),
                    TextEntry::make('policy.max_concurrent_video_jobs')
                        ->label('Video capacity')
                        ->formatStateUsing(fn ($state): string => max(1, (int) $state).' at a time')
                        ->visible(fn (MediaModerationRun $record) => $record->isMedia()),
                    TextEntry::make('policy.review_threshold')->label('Review threshold'),
                    TextEntry::make('policy.violation_threshold')->label('Likely violation threshold'),
                ]),
        ]);
    }

    public static function originLabel(MediaModerationRun $run): string
    {
        return $run->content_review_id ? __('Publication review') : __('Admin-started scan');
    }

    public static function contentApproval(MediaModerationRun|MediaModerationItem|TextModerationItem $record): ?ContentReview
    {
        $run = $record instanceof MediaModerationRun ? $record : $record->run;
        $review = $run?->contentReview;

        return $review && Gate::allows('view', $review) ? $review : null;
    }

    public static function contentApprovalEntry(): TextEntry
    {
        return TextEntry::make('content_approval')->label(__('Content approval'))
            ->state(fn ($record) => ($review = static::contentApproval($record))
                ? ContentReviewResource::statusLabel($review->status) : null)
            ->url(fn ($record) => ($review = static::contentApproval($record))
                ? ContentReviewResource::getUrl('view', ['record' => $review]) : null)
            ->badge()->color(fn ($record) => ($review = static::contentApproval($record))
                ? ContentReviewResource::statusColor($review->status) : 'gray')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->openUrlInNewTab()
            ->visible(fn ($record) => static::contentApproval($record) !== null);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => static::statusColor($state))
                    ->formatStateUsing(fn ($state) => static::statusLabel($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('scan_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str($state)->headline())
                    ->sortable(),
                Tables\Columns\TextColumn::make('origin')->label(__('Origin'))
                    ->state(fn (MediaModerationRun $record) => static::originLabel($record))
                    ->badge()->color('gray'),
                Tables\Columns\TextColumn::make('processed_items')
                    ->label('Items')
                    ->formatStateUsing(fn (MediaModerationRun $record) => static::itemProgressLabel($record)),
                Tables\Columns\TextColumn::make('review_items')->label('Review')->sortable(),
                Tables\Columns\TextColumn::make('violation_items')->label('Likely violations')->sortable(),
                Tables\Columns\TextColumn::make('failed_items')->label('Failed')->sortable(),
                Tables\Columns\TextColumn::make('operations_used')
                    ->label('Operations')
                    ->formatStateUsing(fn ($state, $record) => $state.' / '.$record->max_operations),
                Tables\Columns\TextColumn::make('execution_mode')
                    ->label('Queue mode')
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', $state))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('initiator.username')->label('Started by')->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Started')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('origin')->label(__('Origin'))
                    ->options(['publication' => __('Publication review'), 'admin' => __('Admin-started scan')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'publication' => $query->whereNotNull('scope->content_review_id'),
                        'admin' => $query->whereNull('scope->content_review_id'),
                        default => $query,
                    }),
                SelectFilter::make('scan_type')->label('Type')->options([
                    MediaModerationRun::TYPE_MEDIA => 'Media',
                    MediaModerationRun::TYPE_TEXT => 'Text',
                ]),
                SelectFilter::make('status')->options([
                    MediaModerationRun::STATUS_QUEUED => 'Queued',
                    MediaModerationRun::STATUS_PREPARING => 'Preparing',
                    MediaModerationRun::STATUS_RUNNING => 'Running',
                    MediaModerationRun::STATUS_WAITING_PROVIDER_CAPACITY => 'Waiting for provider capacity',
                    MediaModerationRun::STATUS_QUOTA_PAUSED => 'Budget paused',
                    MediaModerationRun::STATUS_ACTION_REQUIRED => 'Action required',
                    MediaModerationRun::STATUS_COMPLETED => 'Completed',
                    MediaModerationRun::STATUS_COMPLETED_WITH_ERRORS => 'Completed with errors',
                    MediaModerationRun::STATUS_CANCELLED => 'Cancelled',
                    MediaModerationRun::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->actions([
                ActionGroup::make(static::recordActions())
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->poll('10s')
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function startScanAction(): Action
    {
        return Action::make('startMediaModerationScan')
            ->label('Start scan')
            ->icon('heroicon-o-shield-check')
            ->color('primary')
            ->visible(fn (): bool => app(ModerationConfiguration::class)->enabled())
            ->authorize('create', MediaModerationRun::class)
            ->modalHeading('Start an AI moderation scan')
            ->modalDescription('Choose media or text, review the live estimate, and set a safe limit. Unchanged items are skipped by default.')
            ->modalSubmitActionLabel('Start scan')
            ->form([
                Tabs::make('Scan type')
                    ->livewireProperty('scanType')
                    ->contained(false)
                    ->tabs([
                        'media' => Tab::make('Media')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                static::estimatePlaceholder(MediaModerationRun::TYPE_MEDIA),
                                CheckboxList::make('media_sources')
                                    ->label('Content sources')
                                    ->options([
                                        ModerationAttachmentQuery::SOURCE_POSTS => 'Posts',
                                        ModerationAttachmentQuery::SOURCE_STORIES => 'Stories',
                                        ModerationAttachmentQuery::SOURCE_REELS => 'Reels',
                                        ModerationAttachmentQuery::SOURCE_MESSAGES => 'Messages',
                                        ModerationAttachmentQuery::SOURCE_STANDALONE => 'Standalone attachments',
                                        ModerationAttachmentQuery::SOURCE_PROFILE_AVATARS => 'Profile avatars',
                                        ModerationAttachmentQuery::SOURCE_PROFILE_COVERS => 'Profile covers',
                                        ModerationAttachmentQuery::SOURCE_STREAM_POSTERS => 'Stream posters',
                                    ])
                                    ->default([])
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->extraFieldWrapperAttributes(['class' => 'moderation-scan-checkbox-group'])
                                    ->required(fn (?ListMediaModerationRuns $livewire): bool => $livewire?->scanType === MediaModerationRun::TYPE_MEDIA)
                                    ->live(),
                                CheckboxList::make('media_types')
                                    ->label('Media types')
                                    ->options(fn (): array => [
                                        'image' => 'Images',
                                        'video' => app(ModerationSettings::class)->video_enabled
                                            ? 'Videos'
                                            : 'Videos — disabled in settings',
                                    ])
                                    ->disableOptionWhen(fn (string $value): bool => $value === 'video'
                                        && !app(ModerationSettings::class)->video_enabled)
                                    ->default([])
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->extraFieldWrapperAttributes(['class' => 'moderation-scan-checkbox-group'])
                                    ->required(fn (?ListMediaModerationRuns $livewire): bool => $livewire?->scanType === MediaModerationRun::TYPE_MEDIA)
                                    ->live(),
                                Toggle::make('media_force_rescan')
                                    ->label('Force a fresh provider scan')
                                    ->helperText('Danger: this ignores saved results and spends operations again.')
                                    ->live(),
                            ]),
                        'text' => Tab::make('Text')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                static::estimatePlaceholder(MediaModerationRun::TYPE_TEXT),
                                Placeholder::make('text_disabled_notice')
                                    ->hiddenLabel()
                                    ->content('Enable text moderation under Compliance → Moderation before starting a text scan.')
                                    ->visible(fn (): bool => !app(ModerationConfiguration::class)->textEnabled()),
                                CheckboxList::make('text_sources')
                                    ->label('Text sources')
                                    ->options([
                                        TextModerationSourceService::SOURCE_POSTS => 'Posts',
                                        TextModerationSourceService::SOURCE_REELS => 'Reels',
                                        TextModerationSourceService::SOURCE_STORIES => 'Stories',
                                        TextModerationSourceService::SOURCE_POST_COMMENTS => 'Post comments',
                                        TextModerationSourceService::SOURCE_REEL_COMMENTS => 'Reel comments',
                                        TextModerationSourceService::SOURCE_PROFILE_BIOS => 'Profile bios',
                                        TextModerationSourceService::SOURCE_STREAM_TITLES => 'Stream titles',
                                        TextModerationSourceService::SOURCE_STREAM_CHAT => 'Stream chat',
                                        TextModerationSourceService::SOURCE_MESSAGES => 'Private messages',
                                    ])
                                    ->default([])
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->extraFieldWrapperAttributes(['class' => 'moderation-scan-checkbox-group'])
                                    ->required(fn (?ListMediaModerationRuns $livewire): bool => $livewire?->scanType === MediaModerationRun::TYPE_TEXT)
                                    ->disabled(fn (): bool => !app(ModerationConfiguration::class)->textEnabled())
                                    ->live(),
                                Toggle::make('text_force_rescan')
                                    ->label('Force a fresh provider scan')
                                    ->helperText('Danger: this ignores saved results and spends operations again.')
                                    ->disabled(fn (): bool => !app(ModerationConfiguration::class)->textEnabled())
                                    ->live(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    TextInput::make('max_items')
                        ->label('Item limit for this run')
                        ->helperText('Maximum eligible items added to this run.')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => app(ModerationConfiguration::class)->itemLimitForMode(
                            app(QueueRuntimeService::class)->mode(),
                        ))
                        ->required()
                        ->live(debounce: 500),

                    TextInput::make('max_operations')
                        ->label('Provider operation budget')
                        ->helperText('Stops paid checks at this cap; saved results still work.')
                        ->numeric()
                        ->minValue(1)
                        ->default(100)
                        ->required()
                        ->live(debounce: 500),
                ]),

            ])
            ->action(function (array $data, $livewire) {
                try {
                    $isText = $livewire->scanType === MediaModerationRun::TYPE_TEXT;
                    $run = $isText
                        ? app(TextModerationRunService::class)->start(
                            [
                                'sources' => $data['text_sources'] ?? [],
                                'force_rescan' => $data['text_force_rescan'] ?? false,
                            ],
                            Auth::id(),
                            (int) ($data['max_items'] ?? 1),
                            (int) ($data['max_operations'] ?? 1),
                        )
                        : app(ModerationRunService::class)->start(
                            [
                                'sources' => $data['media_sources'] ?? [],
                                'media_types' => $data['media_types'] ?? [],
                                'force_rescan' => $data['media_force_rescan'] ?? false,
                            ],
                            Auth::id(),
                            (int) ($data['max_items'] ?? 1),
                            (int) ($data['max_operations'] ?? 1),
                        );

                    Notification::make()
                        ->title(($isText ? 'Text' : 'Media').' moderation scan started')
                        ->body('Open the scan to follow progress and review results.')
                        ->success()
                        ->send();

                    return redirect(static::getUrl('view', ['record' => $run]));
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('The scan could not be started')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected static function estimatePlaceholder(string $scanType): Placeholder
    {
        return Placeholder::make($scanType.'_estimate')
            ->hiddenLabel()
            ->content(function (Get $get) use ($scanType): HtmlString {
                try {
                    $forceRescan = (bool) $get($scanType.'_force_rescan');
                    $mediaSources = array_values(array_filter((array) $get('media_sources')));
                    $mediaTypes = array_values(array_filter((array) $get('media_types')));
                    $textSources = array_values(array_filter((array) $get('text_sources')));
                    $hasSelection = $scanType === MediaModerationRun::TYPE_TEXT
                        ? $textSources !== []
                        : $mediaSources !== [] && $mediaTypes !== [];
                    $estimate = $scanType === MediaModerationRun::TYPE_TEXT
                        ? app(TextModerationRunService::class)->estimate([
                            'sources' => $textSources ?: app(TextModerationSourceService::class)->defaultSources(),
                            'force_rescan' => $forceRescan,
                        ])
                        : app(ModerationRunService::class)->estimate([
                            'sources' => $mediaSources ?: [
                                ModerationAttachmentQuery::SOURCE_POSTS,
                                ModerationAttachmentQuery::SOURCE_STORIES,
                                ModerationAttachmentQuery::SOURCE_REELS,
                            ],
                            'media_types' => $mediaTypes ?: ['image'],
                            'force_rescan' => $forceRescan,
                        ]);
                    $requestedItems = max(1, (int) ($get('max_items') ?: $estimate['mode_item_limit']));
                    $requestedOperations = max(1, (int) ($get('max_operations') ?: 100));
                    $itemCap = $hasSelection
                        ? min((int) $estimate['items'], (int) $estimate['mode_item_limit'], $requestedItems)
                        : 0;
                    $operationCap = min(
                        $requestedOperations,
                        (int) $estimate['daily_remaining'],
                        (int) $estimate['monthly_remaining'],
                    );
                    $estimatedRunOperations = $hasSelection && $estimate['items'] > 0
                        ? (int) ceil($estimate['estimated_operations'] * ($itemCap / $estimate['items']))
                        : 0;

                    return new HtmlString(view('filament.partials.media-moderation-estimate', [
                        'estimate' => $estimate,
                        'forceRescan' => $forceRescan,
                        'requestedItems' => $requestedItems,
                        'requestedOperations' => $requestedOperations,
                        'fileCap' => $itemCap,
                        'operationCap' => $operationCap,
                        'estimatedRunOperations' => $estimatedRunOperations,
                        'budgetShortfall' => $estimatedRunOperations > $operationCap,
                        'hasSelection' => $hasSelection,
                        'error' => null,
                    ])->render());
                } catch (Throwable $exception) {
                    return new HtmlString(view('filament.partials.media-moderation-estimate', [
                        'estimate' => null,
                        'forceRescan' => false,
                        'error' => $scanType === MediaModerationRun::TYPE_TEXT
                            ? 'Enable and save text moderation settings before estimating a text scan.'
                            : 'Save moderation settings before estimating a media scan.',
                    ])->render());
                }
            })
            ->extraAttributes([
                'class' => 'media-moderation-estimate-wrap',
                'wire:loading.class' => 'is-updating',
            ])
            ->columnSpanFull();
    }

    public static function recordActions(): array
    {
        return [
            Action::make('reviewItems')
                ->label(__('View findings'))
                ->icon('heroicon-o-magnifying-glass')
                ->url(fn (MediaModerationRun $record) => $record->isText()
                    ? TextModerationItemResource::getUrl('index', ['run' => $record->id])
                    : MediaModerationItemResource::getUrl('index', ['run' => $record->id])),
            Action::make('viewIssues')
                ->label('View scan issues')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn (MediaModerationRun $record) => $record->failed_items > 0
                    || $record->skipped_items > 0
                    || in_array($record->status, [
                        MediaModerationRun::STATUS_QUOTA_PAUSED,
                        MediaModerationRun::STATUS_ACTION_REQUIRED,
                    ], true))
                ->url(fn (MediaModerationRun $record) => $record->isText()
                    ? TextModerationItemResource::getUrl('index', ['run' => $record->id, 'tab' => 'errors'])
                    : MediaModerationItemResource::getUrl('index', ['run' => $record->id, 'tab' => 'errors'])),
            Action::make('cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (MediaModerationRun $record) => !$record->isTerminal() && !$record->cancel_requested_at)
                ->action(fn (MediaModerationRun $record) => $record->isText()
                    ? app(TextModerationRunService::class)->cancel($record)
                    : app(ModerationRunService::class)->cancel($record)),
            Action::make('resume')
                ->label('Resume paused items')
                ->icon('heroicon-o-play')
                ->authorize('update')
                ->visible(fn (MediaModerationRun $record) => $record->status === MediaModerationRun::STATUS_QUOTA_PAUSED)
                ->action(function (MediaModerationRun $record): void {
                    $record->isText()
                        ? app(TextModerationRunService::class)->resume($record)
                        : app(ModerationRunService::class)->resume($record);
                    Notification::make()->title('Paused items queued again')->success()->send();
                }),
            Action::make('openModerationSettings')
                ->label('Open moderation settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('warning')
                ->visible(fn (MediaModerationRun $record) => $record->status === MediaModerationRun::STATUS_ACTION_REQUIRED
                    && ManageComplianceSettings::canAccess())
                ->url(fn () => ManageComplianceSettings::getMediaModerationUrl()),
            Action::make('retryFailed')
                ->label('Retry failed items')
                ->icon('heroicon-o-arrow-path')
                ->authorize('update')
                ->visible(fn (MediaModerationRun $record) => static::hasRetryableFailedItems($record))
                ->action(function (MediaModerationRun $record): void {
                    $record->isText()
                        ? app(TextModerationRunService::class)->resume($record, true)
                        : app(ModerationRunService::class)->resume($record, true);
                    Notification::make()->title('Failed items queued again')->success()->send();
                }),
            Action::make('recoverInterrupted')
                ->label('Recover interrupted items')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('gray')
                ->authorize('update')
                ->visible(fn (MediaModerationRun $record) => !$record->isTerminal()
                    && $record->status !== MediaModerationRun::STATUS_ACTION_REQUIRED)
                ->action(function (MediaModerationRun $record): void {
                    $count = app(ModerationRunProgressService::class)->recoverStale($record);
                    Notification::make()
                        ->title($count > 0 ? $count.' interrupted item(s) recovered' : 'No stale processing items found')
                        ->{ $count > 0 ? 'warning' : 'success' }()
                        ->send();
                }),
            Action::make('deleteConfirmedMedia')
                ->label('Delete confirmed media')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading('Delete every confirmed media file in this scan?')
                ->modalDescription('Only items explicitly confirmed by an administrator are included. Files are permanently removed; moderation history is kept.')
                ->form([static::deletionNotesField()])
                ->visible(fn (MediaModerationRun $record) => $record->isMedia()
                    && $record->items()->where('admin_outcome', MediaModerationItem::OUTCOME_CONFIRMED)->exists())
                ->action(function (MediaModerationRun $record, array $data): void {
                    app(ModerationJobDispatcher::class)->deleteConfirmed(
                        $record,
                        'media',
                        Auth::id(),
                        $data['deletion_notes'] ?? null,
                    );
                    Notification::make()->title('Confirmed media deletion queued')->warning()->send();
                }),
            Action::make('deleteConfirmedParents')
                ->label('Delete parent content for confirmed items')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading('Delete complete parent content?')
                ->modalDescription('Danger: this deletes the entire post, message, story, or reel for every confirmed item—not only the flagged file.')
                ->form([static::deletionNotesField()])
                ->visible(fn (MediaModerationRun $record) => $record->isMedia() && $record->items()
                    ->where('admin_outcome', MediaModerationItem::OUTCOME_CONFIRMED)
                    ->whereIn('subject_type', ['post', 'message', 'story', 'reel'])
                    ->exists())
                ->action(function (MediaModerationRun $record, array $data): void {
                    app(ModerationJobDispatcher::class)->deleteConfirmed(
                        $record,
                        'parent',
                        Auth::id(),
                        $data['deletion_notes'] ?? null,
                    );
                    Notification::make()->title('Confirmed parent deletion queued')->warning()->send();
                }),
        ];
    }

    public static function headerActions(): array
    {
        $actions = static::recordActions();
        $reviewItemsAction = array_shift($actions);

        return [
            $reviewItemsAction,
            ActionGroup::make($actions)
                ->label('More actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->tooltip('More actions')
                ->dropdownPlacement('bottom-end')
                ->iconButton(),
        ];
    }

    protected static function deletionNotesField(): Textarea
    {
        return Textarea::make('deletion_notes')
            ->label('Internal deletion notes')
            ->helperText('Optional. Saved with every affected moderation item for audit purposes.')
            ->maxLength(2000)
            ->rows(3);
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            MediaModerationRun::STATUS_COMPLETED => 'success',
            MediaModerationRun::STATUS_COMPLETED_WITH_ERRORS,
            MediaModerationRun::STATUS_QUOTA_PAUSED,
            MediaModerationRun::STATUS_ACTION_REQUIRED => 'warning',
            MediaModerationRun::STATUS_FAILED => 'danger',
            MediaModerationRun::STATUS_CANCELLED => 'gray',
            default => 'info',
        };
    }

    public static function statusLabel(?string $status): string
    {
        if ($status === MediaModerationRun::STATUS_ACTION_REQUIRED) {
            return 'Action required';
        }

        if ($status === MediaModerationRun::STATUS_WAITING_PROVIDER_CAPACITY) {
            return 'Waiting for provider capacity';
        }

        return (string) str($status ?: 'unknown')->replace('_', ' ')->headline();
    }

    public static function itemProgressLabel(MediaModerationRun $run): string
    {
        if ($run->status === MediaModerationRun::STATUS_CANCELLED) {
            $checked = (int) $run->safe_items + (int) $run->review_items + (int) $run->violation_items;

            return number_format($checked).' checked · '.number_format((int) $run->skipped_items).' skipped';
        }

        return number_format((int) $run->processed_items).' / '.number_format((int) $run->total_items);
    }

    protected static function hasRetryableFailedItems(MediaModerationRun $run): bool
    {
        return $run->isText()
            ? (bool) $run->has_retryable_failed_text_items
            : (bool) $run->has_retryable_failed_media_items;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaModerationRuns::route('/'),
            'view' => ViewMediaModerationRun::route('/{record}'),
        ];
    }
}
