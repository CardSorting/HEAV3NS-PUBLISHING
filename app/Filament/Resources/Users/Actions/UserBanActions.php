<?php

namespace App\Filament\Resources\Users\Actions;

use App\Model\User;
use App\Model\UserReport;
use App\Model\SubscriptionCancellationRequest;
use App\Services\UserBanService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UserBanActions
{
    public static function make(): array
    {
        return [
            Action::make('ban_user')
                ->label(__('Ban user'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->authorize(fn (Model $record): bool => self::canManage($record))
                ->visible(fn (Model $record): bool => !self::target($record)?->isBanned())
                ->requiresConfirmation()
                ->modalHeading(__('Ban user'))
                ->modalDescription(__('Normal app access will be blocked.'))
                ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => [
                    __('Essential account management and support remain available.'),
                    __('Scheduled posts return to review, and active livestreams are ended.'),
                ]]))
                ->modalSubmitActionLabel(__('Ban user'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('Reason'))
                        ->helperText(__('This explanation is shown to the user.'))
                        ->required()
                        ->maxLength(2000)
                        ->rows(3),
                    TextInput::make('policy_reference')
                        ->label(__('Policy reference'))
                        ->helperText(__('Optional reference to the site rule or policy involved.'))
                        ->maxLength(255),
                    DateTimePicker::make('expires_at')
                        ->label(__('Ban expires at'))
                        ->helperText(__('Leave empty for an indefinite suspension.'))
                        ->seconds(false)
                        ->minDate(fn () => now())
                        ->after('now'),
                    Section::make(__('Payment impact'))
                        ->schema([
                            TextEntry::make('ban_payment_impact')
                                ->hiddenLabel()
                                ->state(fn (Model $record): array => self::paymentImpact(self::authorizeTarget($record, true)))
                                ->bulleted(),
                        ]),
                ])
                ->action(function (Model $record, array $data): void {
                    $target = self::authorizeTarget($record, true);
                    $data = Validator::make($data, [
                        'reason' => ['required', 'string', 'max:2000'],
                        'policy_reference' => ['nullable', 'string', 'max:255'],
                        'expires_at' => ['nullable', 'date', 'after:now'],
                    ])->validate();

                    app(UserBanService::class)->ban(
                        $target,
                        auth()->user(),
                        trim($data['reason']),
                        filled($data['policy_reference'] ?? null) ? trim($data['policy_reference']) : null,
                        filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at'])->utc() : null,
                    );
                    $record->refresh();

                    Notification::make()
                        ->title(__('User suspended'))
                        ->body(__('Renewal cancellation requests are recorded. External billing may remain pending until confirmed by the provider.'))
                        ->warning()
                        ->send();
                }),
            Action::make('unban_user')
                ->label(__('Unban user'))
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->authorize(fn (Model $record): bool => self::canManage($record))
                ->visible(fn (Model $record): bool => (bool) self::target($record)?->isBanned())
                ->requiresConfirmation()
                ->modalHeading(__('Unban user'))
                ->modalDescription(__('Normal account permissions will apply again. Canceled renewals will not restart, and pending renewal cancellations will continue.'))
                ->modalSubmitActionLabel(__('Unban user'))
                ->action(function (Model $record): void {
                    app(UserBanService::class)->unban(self::authorizeTarget($record, true), auth()->user());
                    $record->refresh();

                    Notification::make()->title(__('Suspension lifted'))->success()->send();
                }),
        ];
    }

    public static function retryCancellation(): Action
    {
        return Action::make('retry_renewal_cancellation')
            ->label(__('Retry renewal cancellation'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->authorize(fn (User $record): bool => Gate::allows('ban', $record))
            ->visible(fn (User $record): bool => self::reviewRequests($record)->exists())
            ->requiresConfirmation()
            ->modalDescription(__('Check the payment provider before retrying an uncertain cancellation.'))
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => [
                __('Only retry if recurring billing is still enabled.'),
                __('This queues another attempt; it does not confirm cancellation.'),
            ]]))
            ->schema([
                self::cancellationField([SubscriptionCancellationRequest::REVIEW]),
                Checkbox::make('provider_renewal_confirmed')
                    ->label(__('I checked the provider and confirmed recurring billing is still enabled.'))
                    ->accepted(),
            ])
            ->action(function (User $record, array $data): void {
                $target = self::authorizeTarget($record, true);
                $data = Validator::make($data, [
                    'cancellation_request_id' => ['required', 'integer'],
                    'provider_renewal_confirmed' => ['accepted'],
                ])->validate();
                $request = self::reviewRequests($target)->findOrFail($data['cancellation_request_id']);
                app(UserBanService::class)->retryCancellation($request, auth()->user());

                Notification::make()->title(__('Cancellation retry queued'))->warning()->send();
            });
    }

    public static function confirmProviderCancellation(): Action
    {
        $statuses = [SubscriptionCancellationRequest::REVIEW, SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION];

        return Action::make('confirm_provider_cancellation')
            ->label(__('Confirm billing stopped'))
            ->icon('heroicon-o-check-circle')
            ->color('warning')
            ->authorize(fn (User $record): bool => Gate::allows('ban', $record))
            ->visible(fn (User $record): bool => self::reviewRequests($record, $statuses)->exists())
            ->requiresConfirmation()
            ->modalDescription(__('Verify in the provider dashboard that recurring billing is canceled, expired, or was never activated.'))
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => [
                __('Cancel any usable pending checkout too.'),
                __('This records your confirmation and stops local renewal.'),
                __('It does not issue refunds or restore access.'),
            ]]))
            ->schema([
                self::cancellationField($statuses),
                Checkbox::make('provider_cancellation_confirmed')
                    ->label(__('I checked the provider and confirmed there is no active recurring billing agreement.'))
                    ->accepted(),
            ])
            ->action(function (User $record, array $data) use ($statuses): void {
                $target = self::authorizeTarget($record, true);
                $data = Validator::make($data, [
                    'cancellation_request_id' => ['required', 'integer'],
                    'provider_cancellation_confirmed' => ['accepted'],
                ])->validate();
                $request = self::reviewRequests($target, $statuses)->findOrFail($data['cancellation_request_id']);
                app(UserBanService::class)->confirmProviderCancellation($request, auth()->user());

                Notification::make()->title(__('Provider cancellation confirmed'))->success()->send();
            });
    }

    private static function cancellationField(array $statuses): Select
    {
        return Select::make('cancellation_request_id')
            ->label(__('Cancellation request'))
            ->required()
            ->searchable()
            ->options(fn (User $record): array => self::reviewOptions($record, null, $statuses))
            ->getSearchResultsUsing(fn (User $record, string $search): array => self::reviewOptions($record, $search, $statuses))
            ->getOptionLabelUsing(function (User $record, $value) use ($statuses): ?string {
                Gate::authorize('ban', $record);
                $request = self::reviewRequests($record, $statuses)->find($value);

                return $request ? self::reviewLabel($request) : null;
            });
    }

    /** @return Builder<SubscriptionCancellationRequest> */
    private static function reviewRequests(User $user, array $statuses = [SubscriptionCancellationRequest::REVIEW]): Builder
    {
        return SubscriptionCancellationRequest::query()
            ->whereHas('ban', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereIn('status', $statuses);
    }

    private static function reviewOptions(User $user, ?string $search = null, array $statuses = [SubscriptionCancellationRequest::REVIEW]): array
    {
        Gate::authorize('ban', $user);

        return self::reviewRequests($user, $statuses)
            ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('id', 'like', '%'.$search.'%')->orWhere('subscription_id', 'like', '%'.$search.'%')
                    ->orWhere('transaction_id', 'like', '%'.$search.'%');
            }))
            ->orderBy('id')->limit(100)->get()
            ->mapWithKeys(fn (SubscriptionCancellationRequest $request): array => [$request->id => self::reviewLabel($request)])
            ->all();
    }

    private static function reviewLabel(SubscriptionCancellationRequest $request): string
    {
        return __('Cancellation #:id', ['id' => $request->id]).' · '.($request->subscription_id
            ? __('Subscription #:id', ['id' => $request->subscription_id])
            : __('Pending checkout #:id', ['id' => $request->transaction_id]));
    }

    private static function target(Model $record, bool $refresh = false): ?User
    {
        if ($record instanceof UserReport) {
            if ($refresh) {
                $record = UserReport::query()->findOrFail($record->getKey());
            }

            return $record->reportedUser;
        }

        if ($record instanceof User) {
            return $refresh ? User::query()->findOrFail($record->getKey()) : $record;
        }

        return null;
    }

    private static function canManage(Model $record): bool
    {
        $target = self::target($record);

        return $target && (!$record instanceof UserReport || Gate::allows('view', $record))
            && Gate::allows('ban', $target);
    }

    private static function authorizeTarget(Model $record, bool $refresh = false): User
    {
        if ($record instanceof UserReport) {
            Gate::authorize('view', $refresh ? UserReport::query()->findOrFail($record->getKey()) : $record);
        }

        $target = self::target($record, $refresh);
        abort_unless($target instanceof User, 404);
        Gate::authorize('ban', $target);

        return $target;
    }

    private static function paymentImpact(User $target): array
    {
        $impact = app(UserBanService::class)->impact($target);

        return [
            __(':count outgoing subscriptions will stop renewing.', ['count' => $impact['outgoing_subscriptions']]),
            __(':count incoming subscriptions will stop renewing. New payments to this creator will be blocked.', ['count' => $impact['incoming_subscriptions']]),
            __('Existing buyers retain their valid paid access to content that remains available.'),
            __(':count pending withdrawals keep their existing processing flow. New withdrawal requests are blocked.', ['count' => $impact['pending_withdrawals']]),
            __('Balances and transaction history are preserved. No automatic refunds or payment reversals are issued.'),
            __('External renewal cancellations may remain pending until the provider confirms them.'),
            __('Lifting or expiring the ban will not restart canceled renewals.'),
        ];
    }
}
