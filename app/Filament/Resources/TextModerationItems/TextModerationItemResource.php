<?php

namespace App\Filament\Resources\TextModerationItems;

use App\Filament\Resources\MediaModerationItems\MediaModerationItemResource;
use App\Filament\Resources\MediaModerationRuns\MediaModerationRunResource;
use App\Filament\Resources\PostComments\PostCommentResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\StreamMessages\StreamMessageResource;
use App\Filament\Resources\Streams\StreamResource;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\TextModerationItems\Pages\ListTextModerationItems;
use App\Filament\Resources\TextModerationItems\Pages\ViewTextModerationItem;
use App\Model\ReelComment;
use App\Model\TextModerationItem;
use App\Services\Moderation\TextModerationDeletionService;
use App\Services\Moderation\TextModerationReviewService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
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
use Throwable;

class TextModerationItemResource extends Resource
{
    protected static ?string $model = TextModerationItem::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('Text finding');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Text findings');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['owner', 'run.contentReview', 'reviewer', 'deleter']);
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
                    TextEntry::make('source_snapshot')
                        ->label('Scanned text')
                        ->columnSpanFull()
                        ->prose(),
                    TextEntry::make('decision')
                        ->badge()
                        ->formatStateUsing(fn ($state) => static::decisionLabel($state))
                        ->color(fn ($state) => MediaModerationItemResource::decisionColor($state)),
                    TextEntry::make('max_score')
                        ->label('Confidence')
                        ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 1).'%' : '—'),
                    TextEntry::make('matched_class')
                        ->label('Matched signal')
                        ->formatStateUsing(fn ($state) => static::signalLabel($state))
                        ->placeholder('—'),
                    TextEntry::make('why_flagged')
                        ->label('Why this was flagged')
                        ->state(fn (TextModerationItem $record) => static::whyFlagged($record))
                        ->columnSpanFull()
                        ->visible(fn (TextModerationItem $record) => $record->isFlagged()),
                    TextEntry::make('admin_outcome')->label('Admin decision')->badge(),
                    TextEntry::make('owner.username')
                        ->label('Owner')
                        ->placeholder('Unknown')
                        ->url(fn (TextModerationItem $record) => $record->owner
                            ? UserResource::getUrl('view', ['record' => $record->owner])
                            : null)
                        ->openUrlInNewTab()
                        ->icon(fn (TextModerationItem $record) => $record->owner
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),
                    TextEntry::make('source_type')
                        ->label('Content')
                        ->formatStateUsing(fn ($state, TextModerationItem $record) => $record->sourceLabel())
                        ->url(fn (TextModerationItem $record) => static::subjectUrl($record))
                        ->openUrlInNewTab()
                        ->icon(fn (TextModerationItem $record) => static::subjectUrl($record)
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),
                    TextEntry::make('scan_status')
                        ->label('Scan status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => MediaModerationItemResource::scanStatusLabel($state))
                        ->color(fn ($state) => MediaModerationItemResource::scanStatusColor($state)),
                    TextEntry::make('is_cached')
                        ->label('Saved result')
                        ->formatStateUsing(fn ($state) => $state ? 'Reused' : 'Fresh provider result')
                        ->badge(),
                    TextEntry::make('scanned_at')->label('Scanned')->dateTime()->placeholder('—'),
                    TextEntry::make('reviewer.username')
                        ->label('Decision by')
                        ->placeholder('Administrator no longer available')
                        ->visible(fn (TextModerationItem $record) => (bool) $record->reviewed_at),
                    TextEntry::make('reviewed_at')
                        ->label('Reviewed')
                        ->dateTime()
                        ->visible(fn (TextModerationItem $record) => (bool) $record->reviewed_at),
                    TextEntry::make('review_notes')
                        ->label('Review notes')
                        ->columnSpanFull()
                        ->visible(fn (TextModerationItem $record) => filled($record->review_notes)),
                    TextEntry::make('deleter.username')
                        ->label('Deleted by')
                        ->placeholder('Administrator no longer available')
                        ->visible(fn (TextModerationItem $record) => (bool) $record->content_deleted_at),
                    TextEntry::make('content_deleted_at')
                        ->label('Deleted')
                        ->dateTime()
                        ->visible(fn (TextModerationItem $record) => (bool) $record->content_deleted_at),
                    TextEntry::make('deletion_notes')
                        ->label('Deletion notes')
                        ->columnSpanFull()
                        ->visible(fn (TextModerationItem $record) => filled($record->deletion_notes)),
                ]),

            Section::make('Scan issue')
                ->icon('heroicon-o-exclamation-triangle')
                ->columns(3)
                ->visible(fn (TextModerationItem $record) => filled($record->error_message))
                ->schema([
                    TextEntry::make('error_type')
                        ->label('Error type')
                        ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->headline()),
                    TextEntry::make('error_code')->label('Provider code')->placeholder('—'),
                    TextEntry::make('retryable')->label('Retry available')->badge()->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                    TextEntry::make('error_message')->label('Error details')->columnSpanFull(),
                ]),

            Section::make('Provider details')
                ->columns(2)
                ->schema([
                    TextEntry::make('provider'),
                    TextEntry::make('operations'),
                    TextEntry::make('provider_request_id')->label('Request ID')->placeholder('—'),
                    TextEntry::make('scores')
                        ->columnSpanFull()
                        ->state(fn (TextModerationItem $record) => static::formatJson($record->scores)),
                    TextEntry::make('provider_response')
                        ->label('Sanitized provider response')
                        ->columnSpanFull()
                        ->state(fn (TextModerationItem $record) => static::formatJson($record->provider_response)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source_snapshot')
                    ->label('Text')
                    ->limit(72)
                    ->wrap()
                    ->description(fn (TextModerationItem $record) => implode(' · ', array_filter([
                        $record->sourceLabel(),
                        $record->owner?->username ? '@'.$record->owner->username : null,
                    ])))
                    ->searchable(),
                Tables\Columns\TextColumn::make('finding')
                    ->label('Finding')
                    ->state(fn (TextModerationItem $record) => static::findingLabel($record))
                    ->badge()
                    ->color(fn (TextModerationItem $record) => MediaModerationItemResource::decisionColor($record->decision))
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true)),
                Tables\Columns\TextColumn::make('matched_class')
                    ->label('Signal')
                    ->formatStateUsing(fn ($state, TextModerationItem $record) => $record->decision === TextModerationItem::DECISION_SAFE
                        ? '—'
                        : static::signalLabel($state))
                    ->placeholder('—')
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true)),
                Tables\Columns\TextColumn::make('admin_outcome')
                    ->label('Outcome')
                    ->badge()
                    ->visible(fn ($livewire) => !in_array($livewire->activeTab, ['errors', 'processing'], true)),
                Tables\Columns\TextColumn::make('scan_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MediaModerationItemResource::scanStatusLabel($state))
                    ->color(fn ($state) => MediaModerationItemResource::scanStatusColor($state))
                    ->visible(fn ($livewire) => in_array($livewire->activeTab, ['errors', 'processing'], true)),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Stored error')
                    ->limit(60)
                    ->tooltip(fn ($state) => $state)
                    ->visible(fn ($livewire) => $livewire->activeTab === 'errors'),
                Tables\Columns\IconColumn::make('retryable')
                    ->label('Retry')
                    ->boolean()
                    ->visible(fn ($livewire) => $livewire->activeTab === 'errors'),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn ($state, TextModerationItem $record) => $record->sourceLabel())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('owner.username')
                    ->label('Owner')
                    ->searchable()
                    ->placeholder('Unknown')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_cached')
                    ->label('Saved')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('scanned_at')
                    ->label('Scanned')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source_type')->label('Text source')->options(static::sourceOptions()),
                SelectFilter::make('decision')->options([
                    TextModerationItem::DECISION_SAFE => 'No action',
                    TextModerationItem::DECISION_REVIEW => 'Review',
                    TextModerationItem::DECISION_VIOLATION => 'Likely violation',
                ]),
                SelectFilter::make('matched_class')->label('Signal')->options(static::signalOptions()),
                SelectFilter::make('admin_outcome')->label('Admin decision')->options([
                    TextModerationItem::OUTCOME_UNREVIEWED => 'Unreviewed',
                    TextModerationItem::OUTCOME_DISMISSED => 'Dismissed',
                    TextModerationItem::OUTCOME_CONFIRMED => 'Confirmed',
                    TextModerationItem::OUTCOME_DELETED => 'Deleted',
                    TextModerationItem::OUTCOME_SUPERSEDED => 'Superseded by new policy',
                ]),
            ])
            ->actions([
                ActionGroup::make(static::tableActions())->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->toolbarActions([
                BulkAction::make('confirmSelected')
                    ->label('Confirm selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->authorize(fn () => auth()->user()?->can('Update:TextModerationItem') ?? false)
                    ->visible(fn ($livewire) => $livewire->activeTab !== 'confirmed')
                    ->requiresConfirmation()
                    ->form([static::reviewNotesField()])
                    ->action(function (Collection $records, array $data): void {
                        $records->filter->isFlagged()->each(fn (TextModerationItem $item) => app(TextModerationReviewService::class)
                            ->confirm($item, auth()->id(), $data['review_notes'] ?? null));
                        Notification::make()->title('Selected text findings confirmed')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('dismissSelected')
                    ->label('Dismiss selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->authorize(fn () => auth()->user()?->can('Update:TextModerationItem') ?? false)
                    ->requiresConfirmation()
                    ->form([static::reviewNotesField()])
                    ->action(function (Collection $records, array $data): void {
                        $records->filter->isFlagged()->each(fn (TextModerationItem $item) => app(TextModerationReviewService::class)
                            ->dismiss($item, auth()->id(), $data['review_notes'] ?? null));
                        Notification::make()->title('Selected text findings dismissed')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('deleteSelected')
                    ->label('Delete selected content')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->authorize(fn () => auth()->user()?->can('Update:TextModerationItem') ?? false)
                    ->visible(fn ($livewire) => $livewire->activeTab === 'confirmed')
                    ->requiresConfirmation()
                    ->form([static::deletionNotesField()])
                    ->action(fn (Collection $records, array $data) => static::deleteSelected($records, $data['deletion_notes'] ?? null))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->striped()
            ->poll('10s')
            ->defaultSort('scanned_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function reviewActions(): array
    {
        return [
            Action::make('openRun')
                ->label('Open scan')
                ->icon('heroicon-o-rectangle-stack')
                ->url(fn (TextModerationItem $record) => MediaModerationRunResource::getUrl('view', ['record' => $record->run_id])),
            Action::make('dismiss')
                ->label('Dismiss finding')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->authorize('update')
                ->requiresConfirmation()
                ->form([static::reviewNotesField()])
                ->visible(fn (TextModerationItem $record) => $record->isFlagged()
                    && !$record->content_deleted_at
                    && $record->admin_outcome !== TextModerationItem::OUTCOME_DISMISSED)
                ->action(function (TextModerationItem $record, array $data): void {
                    app(TextModerationReviewService::class)->dismiss($record, auth()->id(), $data['review_notes'] ?? null);
                    Notification::make()->title('Finding dismissed')->success()->send();
                }),
            Action::make('confirm')
                ->label('Confirm violation')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->authorize('update')
                ->requiresConfirmation()
                ->form([static::reviewNotesField()])
                ->visible(fn (TextModerationItem $record) => $record->isFlagged()
                    && !$record->content_deleted_at
                    && $record->admin_outcome !== TextModerationItem::OUTCOME_CONFIRMED)
                ->action(function (TextModerationItem $record, array $data): void {
                    app(TextModerationReviewService::class)->confirm($record, auth()->id(), $data['review_notes'] ?? null);
                    Notification::make()->title('Finding confirmed')->success()->send();
                }),
            Action::make('deleteContent')
                ->label(fn (TextModerationItem $record) => match ($record->source_type) {
                    TextModerationItem::SOURCE_PROFILE_BIO => 'Clear profile bio',
                    TextModerationItem::SOURCE_STREAM_TITLE => 'Clear stream title',
                    default => 'Delete content',
                })
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading('Delete the content containing this text?')
                ->modalDescription('The current content is permanently removed. If the text was edited after scanning, deletion is safely refused.')
                ->form([static::deletionNotesField()])
                ->visible(fn (TextModerationItem $record) => $record->admin_outcome === TextModerationItem::OUTCOME_CONFIRMED
                    && !$record->content_deleted_at)
                ->action(fn (TextModerationItem $record, array $data) => static::delete($record, $data['deletion_notes'] ?? null)),
        ];
    }

    public static function tableActions(): array
    {
        return array_values(array_filter(
            static::reviewActions(),
            fn (Action $action): bool => $action->getName() !== 'openRun',
        ));
    }

    public static function headerActions(): array
    {
        $primary = [];
        $secondary = [];

        foreach (static::reviewActions() as $action) {
            if (in_array($action->getName(), ['dismiss', 'confirm', 'deleteContent'], true)) {
                $primary[] = $action;
            } else {
                $secondary[] = $action;
            }
        }

        return [
            ...$primary,
            ActionGroup::make($secondary)
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->tooltip('More actions')
                ->dropdownPlacement('bottom-end')
                ->iconButton(),
        ];
    }

    public static function sourceOptions(): array
    {
        return [
            TextModerationItem::SOURCE_POST => 'Posts',
            TextModerationItem::SOURCE_REEL => 'Reels',
            TextModerationItem::SOURCE_STORY => 'Stories',
            TextModerationItem::SOURCE_POST_COMMENT => 'Post comments',
            TextModerationItem::SOURCE_REEL_COMMENT => 'Reel comments',
            TextModerationItem::SOURCE_PROFILE_BIO => 'Profile bios',
            TextModerationItem::SOURCE_STREAM_TITLE => 'Stream titles',
            TextModerationItem::SOURCE_STREAM_CHAT => 'Stream chat',
            TextModerationItem::SOURCE_MESSAGE => 'Private messages',
        ];
    }

    public static function signalOptions(): array
    {
        return [
            'sexual' => 'Sexual content',
            'discriminatory' => 'Discriminatory content',
            'insulting' => 'Insulting content',
            'violent' => 'Violent content',
            'toxic' => 'Toxic content',
            'self-harm' => 'Self-harm',
        ];
    }

    protected static function findingLabel(TextModerationItem $record): string
    {
        $confidence = $record->max_score !== null ? ' · '.number_format($record->max_score * 100).'%' : '';

        return static::decisionLabel($record->decision).$confidence;
    }

    protected static function decisionLabel(?string $decision): string
    {
        return match ($decision) {
            TextModerationItem::DECISION_SAFE => 'No action',
            TextModerationItem::DECISION_REVIEW => 'Review',
            TextModerationItem::DECISION_VIOLATION => 'Likely violation',
            default => (string) str($decision ?: 'unknown')->headline(),
        };
    }

    protected static function signalLabel(?string $signal): string
    {
        return static::signalOptions()[$signal] ?? (string) str($signal ?: 'unknown')->replace('_', ' ')->headline();
    }

    protected static function whyFlagged(TextModerationItem $record): string
    {
        $review = (float) data_get($record->run?->policy, 'review_threshold', 0.65);
        $violation = (float) data_get($record->run?->policy, 'violation_threshold', 0.90);

        return static::signalLabel($record->matched_class).' scored '
            .number_format(((float) $record->max_score) * 100, 1)
            .'%. Review begins at '.number_format($review * 100, 1)
            .'%; likely violation begins at '
            .number_format($violation * 100, 1)
            .'%.';
    }

    protected static function subjectUrl(TextModerationItem $record): ?string
    {
        if (!$record->subject_id || $record->content_deleted_at) {
            return null;
        }

        return match ($record->subject_type) {
            'post' => PostResource::getUrl('view', ['record' => $record->subject_id]),
            'reel' => ReelResource::getUrl('view', ['record' => $record->subject_id]),
            'story' => StoryResource::getUrl('view', ['record' => $record->subject_id]),
            'post_comment' => PostCommentResource::getUrl('view', ['record' => $record->subject_id]),
            'reel_comment' => static::reelCommentUrl($record),
            'user' => UserResource::getUrl('view', ['record' => $record->subject_id]),
            'stream' => StreamResource::getUrl('view', ['record' => $record->subject_id]),
            'stream_message' => StreamMessageResource::getUrl('view', ['record' => $record->subject_id]),
            'message' => UserMessageResource::getUrl('view', ['record' => $record->subject_id]),
            default => null,
        };
    }

    protected static function reelCommentUrl(TextModerationItem $record): ?string
    {
        $reelId = ReelComment::query()->whereKey($record->subject_id)->value('reel_id');

        return $reelId ? ReelResource::getUrl('view', ['record' => $reelId]) : null;
    }

    protected static function formatJson(mixed $state): HtmlString
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new HtmlString('<pre class="admin-json-viewer"><code>'.e($json === false ? 'JSON could not be displayed.' : $json).'</code></pre>');
    }

    protected static function reviewNotesField(): Textarea
    {
        return Textarea::make('review_notes')
            ->label('Internal review notes')
            ->maxLength(2000)
            ->rows(3);
    }

    protected static function deletionNotesField(): Textarea
    {
        return Textarea::make('deletion_notes')
            ->label('Internal deletion notes')
            ->maxLength(2000)
            ->rows(3);
    }

    protected static function delete(TextModerationItem $record, ?string $notes): void
    {
        try {
            app(TextModerationDeletionService::class)->deleteConfirmed($record, auth()->id(), $notes);
            Notification::make()->title('Content deleted')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Content could not be deleted')->body($exception->getMessage())->danger()->send();
        }
    }

    protected static function deleteSelected(Collection $records, ?string $notes): void
    {
        $deleted = 0;
        $failed = 0;

        foreach ($records->unique(fn (TextModerationItem $item) => $item->source_type.':'.$item->source_id) as $record) {
            if ($record->admin_outcome !== TextModerationItem::OUTCOME_CONFIRMED || $record->content_deleted_at) {
                continue;
            }

            try {
                app(TextModerationDeletionService::class)->deleteConfirmed($record, auth()->id(), $notes);
                $deleted++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $notification = Notification::make()->title($deleted.' content '.($deleted === 1 ? 'item' : 'items').' deleted');

        if ($failed > 0) {
            $notification->body($failed.' could not be deleted.')->warning();
        } elseif ($deleted > 0) {
            $notification->success();
        } else {
            $notification->title('No eligible selected content was deleted')->warning();
        }

        $notification->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTextModerationItems::route('/'),
            'view' => ViewTextModerationItem::route('/{record}'),
        ];
    }
}
