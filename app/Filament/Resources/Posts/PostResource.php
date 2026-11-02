<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\ContentReviews\ContentReviewResource;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Resources\Posts\Pages\ViewPostAttachments;
use App\Filament\Resources\Posts\Pages\ViewPostComments;
use App\Filament\Resources\Posts\Pages\ViewPostTransactions;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PostResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = Post::class;

    protected static UnitEnum|string|null $navigationGroup = 'Posts';

    protected static ?int $navigationSort = 0;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('admin.resources.post.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.post.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('contentReview');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make(__('admin.resources.post.sections.details'))
                    ->columnSpanFull()
                    ->description(__('admin.resources.post.sections.details_descr'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('admin.resources.post.fields.user_id'))
                            ->relationship('user', 'username')
                            ->searchable()
                            ->required()
                            ->preload()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('text')
                            ->label(__('admin.resources.post.fields.text'))
                            ->columnSpanFull()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make(__('admin.resources.post.sections.settings'))
                    ->columnSpanFull()
                    ->description(__('admin.resources.post.sections.settings_descr'))
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label(__('admin.resources.post.fields.price'))
                            ->numeric()
                            ->default(0)
                            ->prefix('$'),

                        Forms\Components\Select::make('status')
                            ->label(__('admin.resources.post.fields.status'))
                            ->required()
                            ->disabled(fn (?Post $record) => $record?->contentReview !== null)
                            ->dehydrated(fn (?Post $record) => $record?->contentReview === null)
                            ->helperText(fn (?Post $record) => $record?->contentReview
                                ? __('Use the review actions to approve or reject this submission.') : null)
                            ->options([
                                '0' => __('admin.resources.post.status_labels.0'),
                                '1' => __('admin.resources.post.status_labels.1'),
                                '2' => __('admin.resources.post.status_labels.2'),
                            ])
                            ->default(fn (): int => getSetting('compliance.admin_approved_posts_limit')
                                ? Post::PENDING_STATUS
                                : Post::APPROVED_STATUS),

                        Forms\Components\DateTimePicker::make('release_date')
                            ->label(__('admin.resources.post.fields.release_date'))
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('expire_date')
                            ->label(__('admin.resources.post.fields.expire_date')),

                        Forms\Components\Toggle::make('is_pinned')
                            ->label(__('admin.resources.post.fields.is_pinned'))
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.post.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('text')
                    ->label(__('admin.resources.post.fields.text'))
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('admin.resources.post.fields.price'))
                    ->numeric()
                    ->money(getSetting('payments.currency_code'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.post.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(Post::getStatusName($state)))
                    ->color(fn (string $state): string => match ($state) {
                        '0' => 'gray',
                        '1' => 'success',
                        '2' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('release_date')
                    ->label(__('admin.resources.post.fields.release_date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('text')->label(__('admin.resources.post.fields.text')),
                        TextConstraint::make('status')->label(__('admin.resources.post.fields.status')),
                        NumberConstraint::make('price')->label(__('admin.resources.post.fields.price'))->icon('heroicon-m-currency-dollar'),
                        DateConstraint::make('release_date')->label(__('admin.resources.post.fields.release_date')),
                        DateConstraint::make('expire_date')->label(__('admin.resources.post.fields.expire_date')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
//                Tables\Actions\EditAction::make(),
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
            Action::make('content_review')
                ->label(__('Content approval'))
                ->icon('heroicon-o-clipboard-document-check')
                ->url(fn (Post $record) => ContentReviewResource::getUrl('view', ['record' => $record->contentReview?->id]))
                ->visible(fn (Post $record) => $record->contentReview !== null && Gate::allows('view', $record->contentReview)),
            Action::make('post_url')
                ->label(__('admin.resources.post.actions.post_url'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => route('posts.get', ['post_id' => $record->id, 'username' => $record->user->username]))
                ->openUrlInNewTab()
                ->color('info'),
            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal');
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewPostComments::class,
            ViewPostAttachments::class,
            ViewPostTransactions::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
            'view' => ViewPost::route('/{record}'),
            'comments' => ViewPostComments::route('/{record}/comments'),
            'attachments' => ViewPostAttachments::route('/{record}/attachments'),
            'transactions' => ViewPostTransactions::route('/{record}/payments'),
        ];
    }
}
