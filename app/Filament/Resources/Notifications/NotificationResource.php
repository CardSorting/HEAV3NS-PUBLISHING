<?php

namespace App\Filament\Resources\Notifications;

use App\Filament\Resources\Notifications\Pages\CreateNotification;
use App\Filament\Resources\Notifications\Pages\EditNotification;
use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Filament\Resources\Notifications\Pages\ViewNotification;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Notification;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\BooleanConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class NotificationResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Notification::class;

    protected static ?int $navigationSort = 11;

    protected static string|UnitEnum|null $navigationGroup = 'Notifications';

    public static function getModelLabel(): string
    {
        return __('admin.resources.notification.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.notification.plural');
    }

    public static function getTypes()
    {
        return [
            Notification::PPV_UNLOCK => __('admin.resources.notification.types.ppv_unlock'),
            Notification::EXPIRING_STREAM => __('admin.resources.notification.types.expiring_stream'),
            Notification::NEW_MESSAGE => __('admin.resources.notification.types.new_message'),
            Notification::WITHDRAWAL_ACTION => __('admin.resources.notification.types.withdrawal_action'),
            Notification::NEW_SUBSCRIPTION => __('admin.resources.notification.types.new_subscription'),
            Notification::NEW_COMMENT => __('admin.resources.notification.types.new_comment'),
            Notification::COMMENT_REPLY => __('admin.resources.notification.types.comment_reply'),
            Notification::NEW_REACTION => __('admin.resources.notification.types.new_reaction'),
            Notification::NEW_TIP => __('admin.resources.notification.types.new_tip'),
            Notification::MENTION => __('admin.resources.notification.types.mention'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $uuid = Str::uuid()->toString();
        return $schema->components([
            Section::make(__('admin.resources.notification.sections.general_info'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('id')
                            ->label(__('admin.resources.notification.fields.id'))
                            ->helperText(__('admin.resources.notification.helper_texts.id'))
                            ->required()
                            ->default($uuid)->columnSpanFull(),

                        Forms\Components\Select::make('from_user_id')
                            ->label(__('admin.resources.notification.fields.from_user_id'))
                            ->relationship('fromUser', 'username')
                            ->searchable()
                            ->preload(true)
                            ->required(),

                        Forms\Components\Select::make('to_user_id')
                            ->label(__('admin.resources.notification.fields.to_user_id'))
                            ->relationship('toUser', 'username')
                            ->searchable()
                            ->preload(true)
                            ->required(),
                    ]),
                ]),

            Section::make(__('admin.resources.notification.sections.notification_details'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Select::make('type')
                            ->label(__('admin.resources.notification.fields.type'))
                            ->required()
                            ->options(self::getTypes())
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Toggle::make('read')
                            ->label(__('admin.resources.notification.fields.read'))
                            ->helperText(__('admin.resources.notification.helper_texts.read'))
                            ->onIcon('heroicon-m-check-circle')
                            ->offIcon('heroicon-m-eye-slash')
                            ->onColor('success')
                            ->offColor('gray')
                            ->inline(), // or remove this line if it's still visually off
                    ]),
                ]),

            Section::make(__('admin.resources.notification.sections.linked_models'))
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(2)->schema([

                        Forms\Components\Select::make('post_id')
                            ->label(__('admin.resources.notification.fields.post_id'))
                            ->relationship('post', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('post_comment_id')
                            ->label(__('admin.resources.notification.fields.post_comment_id'))
                            ->relationship('postComment', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('subscription_id')
                            ->label(__('admin.resources.notification.fields.subscription_id'))
                            ->relationship('subscription', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('transaction_id')
                            ->label(__('admin.resources.notification.fields.transaction_id'))
                            ->relationship('transaction', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('reaction_id')
                            ->label(__('admin.resources.notification.fields.reaction_id'))
                            ->relationship('reaction', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('withdrawal_id')
                            ->label(__('admin.resources.notification.fields.withdrawal_id'))
                            ->relationship('withdrawal', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('user_message_id')
                            ->label(__('admin.resources.notification.fields.user_message_id'))
                            ->relationship('userMessage', 'id')
                            ->searchable(),

                        Forms\Components\Select::make('stream_id')
                            ->label(__('admin.resources.notification.fields.stream_id'))
                            ->relationship('stream', 'id')
                            ->searchable(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fromUser.username')
                    ->label(__('admin.resources.notification.fields.from_user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('toUser.username')
                    ->label(__('admin.resources.notification.fields.to_user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.notification.fields.type'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return self::getTypes()[$state] ?? ucfirst(str_replace('-', ' ', $state));
                    }),

                Tables\Columns\IconColumn::make('read')
                    ->label(__('admin.resources.notification.fields.read'))
                    ->boolean(),
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
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('id')
                            ->label(__('admin.resources.notification.fields.id')),
                        TextConstraint::make('fromUser.username')
                            ->label(__('admin.resources.notification.fields.from_user_id')),
                        TextConstraint::make('toUser.username')
                            ->label(__('admin.resources.notification.fields.to_user_id')),
                        SelectConstraint::make('type')
                            ->options(self::getTypes())
                            ->label(__('admin.resources.notification.fields.type')),
                        TextConstraint::make('post.id')
                            ->label(__('admin.resources.notification.fields.post_id')),
                        TextConstraint::make('postComment.id')
                            ->label(__('admin.resources.notification.fields.post_comment_id')),
                        TextConstraint::make('subscription.id')
                            ->label(__('admin.resources.notification.fields.subscription_id')),
                        TextConstraint::make('transaction.id')
                            ->label(__('admin.resources.notification.fields.transaction_id')),
                        TextConstraint::make('reaction.id')
                            ->label(__('admin.resources.notification.fields.reaction_id')),
                        TextConstraint::make('withdrawal.id')
                            ->label(__('admin.resources.notification.fields.withdrawal_id')),
                        TextConstraint::make('userMessage.id')
                            ->label(__('admin.resources.notification.fields.user_message_id')),
                        TextConstraint::make('stream.id')
                            ->label(__('admin.resources.notification.fields.stream_id')),
                        BooleanConstraint::make('read')
                            ->label(__('admin.resources.notification.fields.read')),
                        DateConstraint::make('created_at')
                            ->label(__('admin.common.updated_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                DeleteAction::make(),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
            'create' => CreateNotification::route('/create'),
            'edit' => EditNotification::route('/{record}/edit'),
            'view' => ViewNotification::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewNotification::class,
//            Pages\EditNotification::class,
        ]);
    }
}
