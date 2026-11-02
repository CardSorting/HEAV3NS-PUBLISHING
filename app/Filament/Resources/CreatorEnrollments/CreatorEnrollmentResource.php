<?php

namespace App\Filament\Resources\CreatorEnrollments;

use App\Filament\Resources\CreatorEnrollments\Pages\ListCreatorEnrollments;
use App\Filament\Resources\CreatorEnrollments\Pages\ViewCreatorEnrollment;
use App\Filament\Resources\Users\Actions\UserCreatorActions;
use App\Filament\Resources\Users\Schemas\UserCreatorDetails;
use App\Model\CreatorEnrollment;
use App\Model\User;
use App\Services\CreatorAccessService;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CreatorEnrollmentResource extends Resource
{
    protected static ?string $model = CreatorEnrollment::class;

    protected static UnitEnum|string|null $navigationGroup = 'Users';

    public static function getModelLabel(): string
    {
        return __('Creator approval');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Creator approvals');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', User::class);
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof CreatorEnrollment && $record->user
            && (Gate::allows('view', $record->user) || Gate::allows('update', $record->user));
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

    public static function getNavigationBadge(): ?string
    {
        // The admin panel must load before Admin -> Update creates this table.
        if (!SchemaFacade::hasTable('creator_enrollments')) {
            return null;
        }

        $pending = CreatorEnrollment::query()->where('status', CreatorEnrollment::PENDING_STATUS)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Pending creator applications');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            UserCreatorDetails::make(),
            Section::make(__('Creator approval details'))->columns(2)->columnSpanFull()->schema([
                Placeholder::make('creator_user')->label(__('User'))
                    ->content(fn (CreatorEnrollment $record): string => $record->user?->username ?: $record->user?->name ?: '-'),
                Placeholder::make('creator_applied_at')->label(__('Application date'))
                    ->content(fn (CreatorEnrollment $record): string => $record->applied_at?->toDateTimeString() ?? __('No user application recorded')),
                Placeholder::make('creator_agreement_at')->label(__('Agreement accepted'))
                    ->content(fn (CreatorEnrollment $record): string => $record->agreement_accepted_at?->toDateTimeString() ?? __('No acceptance recorded')),
                Placeholder::make('creator_reviewed_at')->label(__('Decision date'))
                    ->content(fn (CreatorEnrollment $record): string => $record->reviewed_at?->toDateTimeString() ?? '-'),
                Placeholder::make('creator_reviewer')->label(__('Reviewed by'))
                    ->content(fn (CreatorEnrollment $record): string => $record->reviewer?->username ?: $record->reviewer?->name ?: '-'),
                Placeholder::make('creator_reason')->label(__('Decision reason'))
                    ->content(fn (CreatorEnrollment $record): string => $record->rejection_reason ?? '-')->columnSpanFull(),
            ]),
            Section::make(__('Application message'))->columnSpanFull()->schema([
                Placeholder::make('application_message')->hiddenLabel()
                    ->extraAttributes(['class' => 'creator-application-message'])
                    ->content(fn (CreatorEnrollment $record): HtmlString => new HtmlString(nl2br(e($record->application_message ?? __('No application message recorded'))))),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.username')->label(__('User'))->searchable()->sortable(),
            TextColumn::make('status')->label(__('Creator approval'))->badge()
                ->formatStateUsing(fn (string $state): string => CreatorEnrollment::statusLabels()[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    CreatorEnrollment::APPROVED_STATUS => 'success',
                    CreatorEnrollment::PENDING_STATUS => 'warning',
                    default => 'danger',
                }),
            TextColumn::make('publishing_access')->label(__('Publishing access'))->badge()
                ->getStateUsing(fn (CreatorEnrollment $record): string => app(CreatorAccessService::class)->publishingStatus($record->user))
                ->color(fn (CreatorEnrollment $record): string => app(CreatorAccessService::class)->canPublish($record->user) ? 'success' : 'warning')
                ->tooltip(fn (CreatorEnrollment $record): ?string => app(CreatorAccessService::class)->restrictionMessage($record->user) ?: null)
                ->toggleable(),
            TextColumn::make('applied_at')->label(__('Application date'))->dateTime()->sortable()->placeholder('-'),
            TextColumn::make('reviewed_at')->label(__('Decision date'))->dateTime()->sortable()->placeholder('-'),
            TextColumn::make('reviewer.username')->label(__('Reviewed by'))->placeholder('-'),
            TextColumn::make('rejection_reason')->label(__('Decision reason'))->limit(50)->wrap()->toggleable(),
        ])->recordActions([ActionGroup::make([...UserCreatorActions::make(), UserCreatorActions::deleteEnrollment()])->icon('heroicon-o-ellipsis-horizontal')])
            ->toolbarActions([UserCreatorActions::bulkRevoke(), UserCreatorActions::bulkDeleteEnrollments()])
            ->checkIfRecordIsSelectableUsing(fn (CreatorEnrollment $record): bool => Gate::allows('update', $record->user))
            ->recordUrl(fn (CreatorEnrollment $record): ?string => static::canView($record) ? static::getUrl('view', ['record' => $record]) : null)
            ->defaultSort('updated_at', 'desc')->paginated([10, 25, 50]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user.creatorEnrollment',
            'user.activeBan',
            'user.verification' => fn ($query) => $query->select(['id', 'user_id', 'status']),
            'reviewer',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreatorEnrollments::route('/'),
            'view' => ViewCreatorEnrollment::route('/{record}'),
        ];
    }
}
