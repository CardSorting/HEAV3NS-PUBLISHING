<?php

namespace App\Filament\Resources\UserReports;

use App\Filament\Resources\Attachments\AttachmentPresentation;
use App\Filament\Resources\UserReports\Pages\CreateUserReport;
use App\Filament\Resources\UserReports\Pages\EditUserReport;
use App\Filament\Resources\UserReports\Pages\ListUserReports;
use App\Filament\Resources\UserReports\Pages\ViewUserReport;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\Streams\StreamResource;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Actions\UserBanActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Filament\Support\GifContent;
use App\Model\Attachment;
use App\Model\UserReport;
use App\Providers\GenericHelperServiceProvider;
use App\Services\UserReportContentDeletionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

class UserReportResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = UserReport::class;

    protected static ?int $navigationSort = 16;

    protected static UnitEnum|string|null $navigationGroup = 'Moderation';

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_report.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_report.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = UserReport::where('status', UserReport::RECEIVED_STATUS)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.resources.user_report.navigation_badge_tooltip');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'reporterUser',
            'reportedUser',
            'reportedPost.attachments',
            'reportedMessage.attachments',
            'reportedStream',
            'reportedStory.attachments',
            'reportedReel.attachments',
            'reportedReelComment.reel.attachments',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.user_report.sections.reporter_reported'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_report.sections.reporter_reported_descr'))
                ->schema([
                    Select::make('from_user_id')
                        ->label(__('admin.resources.user_report.fields.from_user_id'))
                        ->relationship('reporterUser', 'username')
                        ->searchable()
                        ->required()
                        ->placeholder(__('admin.resources.user_report.fields.from_user_id'))
                        ->preload(true),

                    Select::make('user_id')
                        ->label(__('admin.resources.user_report.fields.user_id'))
                        ->relationship('reportedUser', 'username')
                        ->searchable()
                        ->required()
                        ->placeholder(__('admin.resources.user_report.fields.user_id'))
                        ->preload(true),
                ])
                ->columns(2),

            Section::make(__('admin.resources.user_report.sections.reported_content'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_report.sections.reported_content_descr'))
                ->schema([
                    Select::make('post_id')
                        ->label(__('admin.resources.user_report.fields.post_id'))
                        ->relationship('reportedPost', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_report.fields.post_id'))
                        ->preload(true),

                    Select::make('message_id')
                        ->label(__('admin.resources.user_report.fields.message_id'))
                        ->relationship('reportedMessage', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_report.fields.message_id'))
                        ->preload(true),

                    Select::make('stream_id')
                        ->label(__('admin.resources.user_report.fields.stream_id'))
                        ->relationship('reportedStream', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_report.fields.stream_id'))
                        ->preload(true),

                    Select::make('story_id')
                        ->label(__('admin.resources.user_report.fields.story_id'))
                        ->relationship('reportedStory', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_report.fields.story_id'))
                        ->preload(true),

                    Select::make('reel_id')
                        ->label(__('admin.resources.user_report.fields.reel_id'))
                        ->relationship('reportedReel', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_report.fields.reel_id'))
                        ->preload(true),

                    Select::make('reel_comment_id')
                        ->label(__('admin.resources.user_report.fields.reel_comment_id'))
                        ->relationship('reportedReelComment', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_report.fields.reel_comment_id'))
                        ->preload(true),
                ])
                ->columns(3),

            Section::make(__('admin.resources.user_report.sections.report_details'))
                ->columnSpanFull()
                ->schema([
                    Select::make('type')
                        ->label(__('admin.resources.user_report.fields.type'))
                        ->required()
                        ->options([
                            UserReport::I_DONT_LIKE_TYPE => __('admin.resources.user_report.types.i_dont_like'),
                            UserReport::SPAM_TYPE => __('admin.resources.user_report.types.spam'),
                            UserReport::DMCA_TYPE => __('admin.resources.user_report.types.dmca'),
                            UserReport::OFFENSIVE_CONTENT_TYPE => __('admin.resources.user_report.types.offensive_content'),
                            UserReport::ABUSE_TYPE => __('admin.resources.user_report.types.abuse'),
                        ])
                        ->default(UserReport::I_DONT_LIKE_TYPE),

                    Select::make('status')
                        ->label(__('admin.resources.user_report.fields.status'))
                        ->options([
                            UserReport::RECEIVED_STATUS => __('admin.resources.user_report.statuses.received'),
                            UserReport::SEEN_STATUS => __('admin.resources.user_report.statuses.seen'),
                            UserReport::SOLVED_STATUS => __('admin.resources.user_report.statuses.solved'),
                        ])
                        ->placeholder(__('admin.resources.user_report.fields.status'))
                        ->required(),

                    Textarea::make('details')
                        ->label(__('admin.resources.user_report.fields.details'))
                        ->placeholder(__('admin.resources.user_report.fields.details'))
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Report review')
                ->columns(3)
                ->schema([
                    ViewEntry::make('reported_media')
                        ->label('Reported media')
                        ->state(fn (UserReport $record) => static::mediaItems($record))
                        ->view('filament.infolists.user-report-media')
                        ->columnSpanFull()
                        ->visible(fn (UserReport $record) => static::mediaItems($record) !== []),

                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => __('admin.resources.user_report.statuses.'.$state))
                        ->color(fn ($state) => static::statusColor($state)),

                    TextEntry::make('type')
                        ->label('Report reason')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state) => static::reasonLabel($state)),

                    TextEntry::make('created_at')
                        ->label('Submitted')
                        ->dateTime(),

                    TextEntry::make('reporterUser.username')
                        ->label('Reported by')
                        ->placeholder('Unknown user')
                        ->url(fn (UserReport $record) => $record->reporterUser
                            ? UserResource::getUrl('view', ['record' => $record->reporterUser])
                            : null)
                        ->openUrlInNewTab()
                        ->icon(fn (UserReport $record) => $record->reporterUser
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),

                    TextEntry::make('reportedUser.username')
                        ->label('Reported user')
                        ->placeholder('Unknown user')
                        ->url(fn (UserReport $record) => $record->reportedUser
                            ? UserResource::getUrl('view', ['record' => $record->reportedUser])
                            : null)
                        ->openUrlInNewTab()
                        ->icon(fn (UserReport $record) => $record->reportedUser
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),

                    TextEntry::make('reported_content')
                        ->label('Reported content')
                        ->state(fn (UserReport $record) => static::contentLabel($record))
                        ->url(fn (UserReport $record) => static::contentUrl($record))
                        ->openUrlInNewTab()
                        ->icon(fn (UserReport $record) => static::contentUrl($record)
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null),

                    TextEntry::make('content_excerpt')
                        ->label('Content text')
                        ->state(fn (UserReport $record) => static::contentExcerpt($record))
                        ->placeholder('This report does not include text content.')
                        ->limit(600)
                        ->columnSpanFull(),

                    TextEntry::make('reported_gif')
                        ->label(__('GIF'))
                        ->state(fn (UserReport $record) => GifContent::reference(static::reportedGifContent($record)) !== null ? __('GIF') : null)
                        ->formatStateUsing(fn (UserReport $record) => GifContent::preview(
                            static::reportedGifContent($record),
                            $record->reel_comment_id ? 'reel_comments' : 'messenger',
                        ))
                        ->visible(fn (UserReport $record) => GifContent::reference(static::reportedGifContent($record)) !== null)
                        ->columnSpanFull(),

                    TextEntry::make('details')
                        ->label('Reporter details')
                        ->placeholder('No additional details were provided.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('reported_media_preview')
                    ->label('Preview')
                    ->state(fn (UserReport $record) => static::previewUrl($record))
                    ->square()
                    ->size(44),

                Tables\Columns\TextColumn::make('reporterUser.username')
                    ->label(__('admin.resources.user_report.fields.from_user_id'))
                    ->placeholder('Unknown')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('story_id')
                    ->label(__('admin.resources.user_report.fields.story_id'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reel_id')
                    ->label(__('admin.resources.user_report.fields.reel_id'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reel_comment_id')
                    ->label(__('admin.resources.user_report.fields.reel_comment_id'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.user_report.fields.type'))
                    ->badge()
                    ->color('gray')
                    ->wrap()
                    ->formatStateUsing(fn ($state) => static::reasonLabel($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.user_report.fields.status'))
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => __("admin.resources.user_report.statuses.".$state))
                    ->color(fn ($state) => match ($state) {
                        UserReport::SOLVED_STATUS => 'success',
                        UserReport::RECEIVED_STATUS => 'warning',
                        UserReport::SEEN_STATUS => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('reporterUser.username')->label(__('admin.resources.user_report.fields.from_user_id')),
                        TextConstraint::make('reportedUser.username')->label(__('admin.resources.user_report.fields.user_id')),
                        TextConstraint::make('reportedPost.id')->label(__('admin.resources.user_report.fields.post_id')),
                        TextConstraint::make('reportedMessage.id')->label(__('admin.resources.user_report.fields.message_id')),
                        TextConstraint::make('reportedStream.id')->label(__('admin.resources.user_report.fields.stream_id')),

                        TextConstraint::make('story_id')->label(__('admin.resources.user_report.fields.story_id')),
                        TextConstraint::make('reel_id')->label(__('admin.resources.user_report.fields.reel_id')),
                        TextConstraint::make('reel_comment_id')->label(__('admin.resources.user_report.fields.reel_comment_id')),

                        TextConstraint::make('details')->label(__('admin.resources.user_report.fields.details')),
                        SelectConstraint::make('status')
                            ->label(__('admin.resources.user_report.fields.status'))
                            ->options([
                                UserReport::RECEIVED_STATUS => __('admin.resources.user_report.statuses.received'),
                                UserReport::SEEN_STATUS => __('admin.resources.user_report.statuses.seen'),
                                UserReport::SOLVED_STATUS => __('admin.resources.user_report.statuses.solved'),
                            ]),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                ...static::getRecordActionsForDisplay(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordActions(): array
    {
        return [
            ...UserBanActions::make(),
            Action::make('view_admin')
                ->label(__('admin.resources.user_report.actions.view_admin'))
                ->icon('heroicon-o-shield-check')
                ->url(fn (UserReport $record) => static::contentUrl($record))
                ->openUrlInNewTab()
                ->color('primary')
                ->visible(fn (UserReport $record) => filled(static::contentUrl($record))),

            Action::make('view_public')
                ->label(__('admin.resources.user_report.actions.view_public'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => GenericHelperServiceProvider::getReportLinks($record)['public'] ?? '#')
                ->openUrlInNewTab()
                ->color('success')
                ->visible(fn ($record) => !empty(GenericHelperServiceProvider::getReportLinks($record)['public'])),

            Action::make('delete_reported_content')
                ->label('Delete reported content')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize(fn (UserReport $record): bool => app(UserReportContentDeletionService::class)->canDelete(
                    auth()->user(),
                    $record,
                ))
                ->visible(fn (UserReport $record): bool => (bool) app(UserReportContentDeletionService::class)->target($record))
                ->requiresConfirmation()
                ->modalHeading(fn (UserReport $record): string => 'Delete reported '.app(UserReportContentDeletionService::class)->targetLabel($record).'?')
                ->modalDescription('This permanently deletes the complete reported content and its attached media. The report will be marked solved and retained for reference.')
                ->modalSubmitActionLabel('Delete reported content')
                ->action(function (UserReport $record): void {
                    app(UserReportContentDeletionService::class)->delete($record, auth()->id());
                    $record->refresh();

                    Notification::make()
                        ->title('Reported content deleted')
                        ->body('The report was marked as solved and kept for reference.')
                        ->warning()
                        ->send();
                }),

            DeleteAction::make()->label('Delete report'),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserReports::route('/'),
            'create' => CreateUserReport::route('/create'),
            'edit' => EditUserReport::route('/{record}/edit'),
            'view' => ViewUserReport::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([]);
    }

    protected static function previewUrl(UserReport $report): ?string
    {
        $item = collect(static::mediaItems($report))->first(
            fn (array $item) => filled($item['thumbnail'] ?? null),
        );

        return $item['thumbnail'] ?? null;
    }

    /**
     * @return list<array{type: string, path: ?string, thumbnail: ?string, name: string}>
     */
    protected static function mediaItems(UserReport $report): array
    {
        if ($report->deletedContentReference()) {
            return [];
        }

        if ($report->stream_id && filled($report->reportedStream?->poster)) {
            return [[
                'type' => 'image',
                'path' => $report->reportedStream->poster,
                'thumbnail' => $report->reportedStream->poster,
                'name' => 'Stream poster',
            ]];
        }

        if (static::contentId($report) === null) {
            if (!filled($report->reportedUser?->avatar)) {
                return [];
            }

            return [[
                'type' => 'image',
                'path' => $report->reportedUser->avatar,
                'thumbnail' => $report->reportedUser->avatar,
                'name' => 'Profile image',
            ]];
        }

        $content = static::reportedContent($report);

        if ($report->reel_comment_id && $report->reportedReelComment?->reel) {
            $content = $report->reportedReelComment->reel;
        }

        if ($content instanceof Model && method_exists($content, 'attachments')) {
            $content->loadMissing('attachments');
            $attachments = $content->getRelation('attachments');

            if (!$attachments instanceof Collection) {
                return [];
            }

            $items = [];

            foreach ($attachments as $attachment) {
                if (!$attachment instanceof Attachment) {
                    continue;
                }

                $items[] = AttachmentPresentation::data($attachment);
            }

            return $items;
        }

        return [];
    }

    protected static function reportedContent(UserReport $report): mixed
    {
        return match (true) {
            (bool) $report->reel_comment_id => $report->reportedReelComment,
            (bool) $report->post_id => $report->reportedPost,
            (bool) $report->message_id => $report->reportedMessage,
            (bool) $report->stream_id => $report->reportedStream,
            (bool) $report->story_id => $report->reportedStory,
            (bool) $report->reel_id => $report->reportedReel,
            default => $report->reportedUser,
        };
    }

    protected static function reportedGifContent(UserReport $report): ?Model
    {
        if ($report->deletedContentReference()) {
            return null;
        }

        $content = static::reportedContent($report);

        return ($report->message_id || $report->reel_comment_id) && $content instanceof Model ? $content : null;
    }

    protected static function contentExcerpt(UserReport $report): ?string
    {
        if ($report->deletedContentReference()) {
            return null;
        }

        $content = static::reportedContent($report);
        $text = match (true) {
            (bool) $report->reel_comment_id => $content?->message,
            (bool) $report->post_id => $content?->text,
            (bool) $report->message_id => $content?->message,
            (bool) $report->stream_id => $content?->name,
            (bool) $report->story_id => $content?->text,
            (bool) $report->reel_id => $content?->caption,
            default => $content?->bio,
        };

        $text = trim(strip_tags((string) $text));

        return $text !== '' ? $text : null;
    }

    protected static function contentLabel(UserReport $report): string
    {
        if ($deletedContentLabel = $report->deletedContentLabel()) {
            return $deletedContentLabel;
        }

        $type = GenericHelperServiceProvider::resolveReportType($report);
        $id = static::contentId($report);

        return $id === null ? $type : $type.' #'.$id;
    }

    protected static function contentId(UserReport $report): int|string|null
    {
        return $report->reel_comment_id
            ?? $report->stream_id
            ?? $report->message_id
            ?? $report->post_id
            ?? $report->reel_id
            ?? $report->story_id;
    }

    protected static function contentUrl(UserReport $report): ?string
    {
        if ($report->deletedContentReference()) {
            return null;
        }

        return match (true) {
            (bool) $report->reel_comment_id && (bool) $report->reportedReelComment?->reel_id => ReelResource::getUrl('view', ['record' => $report->reportedReelComment->reel_id]),
            (bool) $report->stream_id && (bool) $report->reportedStream => StreamResource::getUrl('view', ['record' => $report->stream_id]),
            (bool) $report->message_id && (bool) $report->reportedMessage => UserMessageResource::getUrl('view', ['record' => $report->message_id]),
            (bool) $report->post_id && (bool) $report->reportedPost => PostResource::getUrl('view', ['record' => $report->post_id]),
            (bool) $report->reel_id && (bool) $report->reportedReel => ReelResource::getUrl('view', ['record' => $report->reel_id]),
            (bool) $report->story_id && (bool) $report->reportedStory => StoryResource::getUrl('view', ['record' => $report->story_id]),
            (bool) $report->reportedUser => UserResource::getUrl('view', ['record' => $report->reportedUser]),
            default => null,
        };
    }

    protected static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            UserReport::I_DONT_LIKE_TYPE => __('admin.resources.user_report.types.i_dont_like'),
            UserReport::SPAM_TYPE => __('admin.resources.user_report.types.spam'),
            UserReport::DMCA_TYPE => __('admin.resources.user_report.types.dmca'),
            UserReport::OFFENSIVE_CONTENT_TYPE => __('admin.resources.user_report.types.offensive_content'),
            UserReport::ABUSE_TYPE => __('admin.resources.user_report.types.abuse'),
            default => $reason ?: 'Unknown',
        };
    }

    protected static function statusColor(?string $status): string
    {
        return match ($status) {
            UserReport::SOLVED_STATUS => 'success',
            UserReport::RECEIVED_STATUS => 'warning',
            UserReport::SEEN_STATUS => 'danger',
            default => 'gray',
        };
    }
}
