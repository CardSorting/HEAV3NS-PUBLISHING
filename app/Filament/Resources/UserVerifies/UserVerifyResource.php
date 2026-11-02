<?php

namespace App\Filament\Resources\UserVerifies;

use App\Filament\Resources\Users\Schemas\UserCreatorDetails;
use App\Filament\Resources\UserVerifies\Pages\CreateUserVerify;
use App\Filament\Resources\UserVerifies\Pages\EditUserVerify;
use App\Filament\Resources\UserVerifies\Pages\ListUserVerifies;
use App\Filament\Resources\UserVerifies\Pages\ViewUserVerify;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\UserVerify;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;
use Filament\Schemas\Components\View as ViewComponent;

class UserVerifyResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = UserVerify::class;

    protected static ?int $navigationSort = 9;

    protected static UnitEnum|string|null $navigationGroup = 'UserVerifies';

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_verify.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_verify.plural');
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.resources.user_verify.navigation_badge_tooltip');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = UserVerify::where('status', UserVerify::REQUESTED_STATUS)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                UserCreatorDetails::make(),
                Section::make(__('admin.resources.user_verify.sections.verification_details'))
                    ->columnSpanFull()
                    ->description(__('admin.resources.user_verify.sections.verification_details_descr'))
                    ->schema([

                        ViewComponent::make('filament.partials.file-preview-wrapper')
                            ->columnSpanFull()
                            ->hiddenOn('create')
                            ->viewData(fn (?UserVerify $record): array => [
                                'record' => $record?->fresh(),
                            ]),

                        Select::make('user_id')
                            ->label(__('admin.resources.user_verify.fields.user_id'))
                            ->relationship('user', 'username')
                            ->searchable()
                            ->required()
                            ->preload(true),
                        Select::make('status')
                            ->label(__('admin.resources.user_verify.fields.status'))
                            ->required()
                            ->options([
                                UserVerify::REQUESTED_STATUS => ucfirst(UserVerify::REQUESTED_STATUS),
                                UserVerify::REJECTED_STATUS => ucfirst(UserVerify::REJECTED_STATUS),
                                UserVerify::APPROVED_STATUS => ucfirst(UserVerify::APPROVED_STATUS),
                            ])
                            ->default(UserVerify::REQUESTED_STATUS),
                        TextInput::make('rejectionReason')
                            ->label(__('admin.resources.user_verify.fields.rejectionReason'))
                            ->maxLength(191)
                            ->columnSpanFull()
                            ->default(null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.user_verify.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.user_verify.fields.status'))
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        UserVerify::APPROVED_STATUS => 'success',
                        UserVerify::REQUESTED_STATUS => 'warning',
                        UserVerify::REJECTED_STATUS => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('rejectionReason')
                    ->label(__('admin.resources.user_verify.fields.rejectionReason'))
                    ->searchable()
                    ->limit(50),
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
                SelectFilter::make('user_id')
                    ->label(__('admin.resources.user_verify.fields.user_id'))
                    ->relationship('user', 'username')
                    ->searchable(),
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('user.username')->label(__('admin.resources.user_verify.fields.user_id')),
                        TextConstraint::make('status')->label(__('admin.resources.user_verify.fields.status')),
                        TextConstraint::make('rejectionReason')->label(__('admin.resources.user_verify.fields.rejectionReason')),
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
        return [
            //
        ];
    }

    public static function getRecordActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('admin.resources.user_verify.actions.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize('update')
                ->visible(fn (UserVerify $record) => static::canReview($record) && $record->status !== UserVerify::APPROVED_STATUS)
                ->action(fn (UserVerify $record) => $record->update([
                    'status' => UserVerify::APPROVED_STATUS,
                    'rejectionReason' => null,
                ]))
                ->after(fn ($livewire) => self::refreshReviewStatus($livewire)),
            Action::make('reject')
                ->label(__('admin.resources.user_verify.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->authorize('update')
                ->visible(fn (UserVerify $record) => static::canReview($record) && $record->status !== UserVerify::REJECTED_STATUS)
                ->form([
                    TextInput::make('rejectionReason')
                        ->label(__('admin.resources.user_verify.fields.rejectionReason'))
                        ->required()
                        ->maxLength(191),
                ])
                ->action(fn (UserVerify $record, array $data) => $record->update([
                    'status' => UserVerify::REJECTED_STATUS,
                    'rejectionReason' => $data['rejectionReason'],
                ]))
                ->after(fn ($livewire) => self::refreshReviewStatus($livewire)),
            Action::make('profile_url')
                ->label(__('admin.resources.user_verify.actions.profile_url'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => route('profile', ['username' => $record->user->username]))
                ->openUrlInNewTab()
                ->color('info'),
            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal')
            ->iconSize('lg');
    }

    public static function getReviewHeaderActions(bool $includeEdit = false): array
    {
        [$reviewActions, $otherActions] = collect(static::getRecordActions())
            ->partition(fn (Action $action): bool => in_array($action->getName(), ['approve', 'reject'], true));

        return [
            ...$reviewActions->map(fn (Action $action): Action => $action->button())->all(),
            ActionGroup::make([
                ...($includeEdit ? [EditAction::make()] : []),
                ...$otherActions->all(),
            ])
                ->label(__('More'))
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')->button()
                ->dropdownPlacement('bottom-end'),
        ];
    }

    private static function refreshReviewStatus($livewire): void
    {
        if ($livewire instanceof EditUserVerify || $livewire instanceof ViewUserVerify) {
            $livewire->refreshFormData(['status', 'rejectionReason']);
        }
    }

    protected static function canReview(UserVerify $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserVerifies::route('/'),
            'create' => CreateUserVerify::route('/create'),
            'edit' => EditUserVerify::route('/{record}/edit'),
            'view' => ViewUserVerify::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewUserVerify::class,
//            Pages\EditUserVerify::class,
//            Pages\ViewUserVerifyAttachments::class,
        ]);
    }
}
