<?php

namespace App\Filament\Resources\UserDeletionRequests;

use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Resources\UserDeletionRequests\Pages\EditUserDeletionRequest;
use App\Filament\Resources\UserDeletionRequests\Pages\ListUserDeletionRequests;
use App\Filament\Resources\UserDeletionRequests\Pages\ViewUserDeletionRequest;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\UserDeletionRequest;
use App\Services\UserDeletionRequestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class UserDeletionRequestResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = UserDeletionRequest::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-trash';

    protected static UnitEnum|string|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_deletion_request.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_deletion_request.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (!$record instanceof UserDeletionRequest) {
            return '-';
        }

        return $record->requested_username ?: (string) $record->id;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return SchemaFacade::hasTable('user_deletion_requests');
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.resources.user_deletion_request.navigation_badge_tooltip');
    }

    public static function getNavigationBadge(): ?string
    {
        if (!SchemaFacade::hasTable('user_deletion_requests')) {
            return null;
        }

        $count = UserDeletionRequest::query()
            ->whereIn('status', [UserDeletionRequest::STATUS_PENDING, UserDeletionRequest::STATUS_BLOCKED])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.user_deletion_request.sections.request_details'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.resources.user_deletion_request.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->preload(true)
                        ->disabled(),

                    Select::make('status')
                        ->label(__('admin.resources.user_deletion_request.fields.status'))
                        ->options(UserDeletionRequest::statusLabels())
                        ->required(),

                    Select::make('mode')
                        ->label(__('admin.resources.user_deletion_request.fields.mode'))
                        ->options(UserDeletionRequest::modeLabels())
                        ->required(),

                    Select::make('reviewed_by')
                        ->label(__('admin.resources.user_deletion_request.fields.reviewed_by'))
                        ->relationship('reviewer', 'username')
                        ->searchable()
                        ->preload(true)
                        ->disabled(),

                    Textarea::make('reason')
                        ->label(__('admin.resources.user_deletion_request.fields.reason'))
                        ->columnSpanFull(),

                    Textarea::make('admin_notes')
                        ->label(__('admin.resources.user_deletion_request.fields.admin_notes'))
                        ->columnSpanFull(),

                    Textarea::make('rejection_reason')
                        ->label(__('admin.resources.user_deletion_request.fields.rejection_reason'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.user_deletion_request.fields.user_id'))
                    ->placeholder(fn (UserDeletionRequest $record) => $record->requested_username ?: '-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_email')
                    ->label(__('admin.resources.user_deletion_request.fields.requested_email'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.user_deletion_request.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => UserDeletionRequest::statusLabels()[$state] ?? ucfirst((string) $state))
                    ->color(fn ($state) => match ($state) {
                        UserDeletionRequest::STATUS_PENDING => 'warning',
                        UserDeletionRequest::STATUS_APPROVED => 'success',
                        UserDeletionRequest::STATUS_REJECTED,
                        UserDeletionRequest::STATUS_BLOCKED => 'danger',
                        UserDeletionRequest::STATUS_CANCELED => 'gray',
                        UserDeletionRequest::STATUS_COMPLETED => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('mode')
                    ->label(__('admin.resources.user_deletion_request.fields.mode'))
                    ->formatStateUsing(fn ($state) => UserDeletionRequest::modeLabels()[$state] ?? ucfirst((string) $state))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label(__('admin.resources.user_deletion_request.fields.requested_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('eligible_for_deletion_at')
                    ->label(__('admin.resources.user_deletion_request.fields.eligible_for_deletion_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewer.username')
                    ->label(__('admin.resources.user_deletion_request.fields.reviewed_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('admin.resources.user_deletion_request.fields.completed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('requested_username')->label(__('admin.resources.user_deletion_request.fields.requested_username')),
                        TextConstraint::make('requested_email')->label(__('admin.resources.user_deletion_request.fields.requested_email')),
                        SelectConstraint::make('status')
                            ->label(__('admin.resources.user_deletion_request.fields.status'))
                            ->options(UserDeletionRequest::statusLabels()),
                        SelectConstraint::make('mode')
                            ->label(__('admin.resources.user_deletion_request.fields.mode'))
                            ->options(UserDeletionRequest::modeLabels()),
                        DateConstraint::make('requested_at')->label(__('admin.resources.user_deletion_request.fields.requested_at')),
                        DateConstraint::make('eligible_for_deletion_at')->label(__('admin.resources.user_deletion_request.fields.eligible_for_deletion_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                ...static::getRecordActionsForDisplay(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('requested_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRecordActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('admin.resources.user_deletion_request.actions.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (UserDeletionRequest $record) => $record->isActive() && Auth::user()?->can('Update:UserDeletionRequest'))
                ->requiresConfirmation()
                ->modalHeading(__('admin.resources.user_deletion_request.actions.approve_modal_heading'))
                ->modalDescription(fn (UserDeletionRequest $record) => $record->mode === UserDeletionRequest::MODE_MANUAL_REVIEW
                    ? __('admin.resources.user_deletion_request.actions.approve_modal_description_manual_review')
                    : __('admin.resources.user_deletion_request.actions.approve_modal_description'))
                ->modalSubmitActionLabel(__('admin.resources.user_deletion_request.actions.approve_modal_submit'))
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->form([
                    Textarea::make('admin_notes')
                        ->label(__('admin.resources.user_deletion_request.fields.admin_notes'))
                        ->maxLength(2000),
                ])
                ->action(function (UserDeletionRequest $record, array $data) {
                    $response = app(UserDeletionRequestService::class)->approve(
                        $record,
                        Auth::user(),
                        $data['admin_notes'] ?? null
                    );

                    Notification::make()
                        ->title($response['message'])
                        ->{ $response['success'] ? 'success' : 'danger' }()
                        ->send();
                }),
            Action::make('reject')
                ->label(__('admin.resources.user_deletion_request.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (UserDeletionRequest $record) => $record->isActive() && Auth::user()?->can('Update:UserDeletionRequest'))
                ->form([
                    Textarea::make('rejection_reason')
                        ->label(__('admin.resources.user_deletion_request.fields.rejection_reason'))
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (UserDeletionRequest $record, array $data) {
                    $response = app(UserDeletionRequestService::class)->reject(
                        $record,
                        Auth::user(),
                        $data['rejection_reason']
                    );

                    Notification::make()
                        ->title($response['message'])
                        ->{ $response['success'] ? 'success' : 'danger' }()
                        ->send();
                }),
            Action::make('cancel')
                ->label(__('admin.resources.user_deletion_request.actions.cancel'))
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->visible(fn (UserDeletionRequest $record) => $record->isActive() && Auth::user()?->can('Update:UserDeletionRequest'))
                ->requiresConfirmation()
                ->action(function (UserDeletionRequest $record) {
                    $response = app(UserDeletionRequestService::class)->cancel($record, Auth::user());

                    Notification::make()
                        ->title($response['message'])
                        ->{ $response['success'] ? 'success' : 'danger' }()
                        ->send();
                }),
            Action::make('profile_url')
                ->label(__('admin.resources.user.actions.profile_url'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn (UserDeletionRequest $record) => $record->user ? route('profile', ['username' => $record->user->username]) : null)
                ->openUrlInNewTab()
                ->color('info')
                ->visible(fn (UserDeletionRequest $record) => (bool) $record->user),
            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal')
            ->iconSize('lg');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserDeletionRequests::route('/'),
            'edit' => EditUserDeletionRequest::route('/{record}/edit'),
            'view' => ViewUserDeletionRequest::route('/{record}'),
        ];
    }
}
