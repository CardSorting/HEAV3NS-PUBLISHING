<?php

namespace App\Filament\Resources\Users\Actions;

use App\Model\CreatorEnrollment;
use App\Model\User;
use App\Filament\Resources\UserVerifies\UserVerifyResource;
use App\Services\CreatorAccessService;
use App\Services\CreatorEnrollmentNotifications;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserCreatorActions
{
    public static function make(): array
    {
        return [self::review(true), self::review(false), self::manage(), self::reviewIdentity(), self::revoke()];
    }

    public static function deleteEnrollment(): DeleteAction
    {
        return DeleteAction::make('delete_creator_enrollment')
            ->label(__('Delete creator approval'))
            ->modalHeading(__('Delete creator approval?'))
            ->modalDescription(self::deleteEnrollmentDescription())
            ->modalSubmitActionLabel(__('Delete'))
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => self::deleteEnrollmentConsequences()]))
            ->authorize(fn (CreatorEnrollment $record): bool => Gate::allows('update', self::user($record)))
            ->fillForm(fn (CreatorEnrollment $record): array => ['application_version' => $record->application_version])
            ->schema([Hidden::make('application_version')->rules(['required', 'integer', 'min:0'])])
            ->using(function (CreatorEnrollment $record, array $data): bool {
                $data = Validator::make($data, ['application_version' => ['required', 'integer', 'min:0']])->validate();
                app(CreatorAccessService::class)->deleteEnrollment($record, auth()->user(), (int) $data['application_version']);

                return true;
            })
            ->successNotificationTitle(__('Creator approval deleted'));
    }

    public static function bulkDeleteEnrollments(): BulkAction
    {
        return BulkAction::make('delete_creator_enrollments')
            ->label(__('Delete creator approvals'))->icon('heroicon-o-trash')->color('danger')
            ->authorize(fn (): bool => Gate::allows('update', User::class))
            ->requiresConfirmation()->modalHeading(__('Delete creator approvals?'))
            ->modalDescription(self::deleteEnrollmentDescription())
            ->modalSubmitActionLabel(__('Delete'))
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => self::deleteEnrollmentConsequences()]))
            ->fillForm(fn (Collection $records): array => [
                'application_versions' => $records->pluck('application_version', 'id')->all(),
            ])
            ->schema([Hidden::make('application_versions')->rules(['required', 'array'])])
            ->action(function (Collection $records, array $data): void {
                $data = Validator::make($data, [
                    'application_versions' => ['required', 'array'],
                    'application_versions.*' => ['required', 'integer', 'min:0'],
                ])->validate();
                if ($records->count() !== count($data['application_versions'])
                    || $records->contains(fn (CreatorEnrollment $record): bool => !array_key_exists($record->id, $data['application_versions']))) {
                    throw ValidationException::withMessages(['creator' => __('The selected creator approvals have changed. Refresh them before deleting.')]);
                }
                DB::transaction(function () use ($records, $data): void {
                    foreach ($records->sortBy('user_id') as $record) {
                        // Preserve the table's model state if the batch rolls back.
                        app(CreatorAccessService::class)->deleteEnrollment($record->withoutRelations(), auth()->user(), (int) $data['application_versions'][$record->id]);
                    }
                }, 3);
                $records->each(function (CreatorEnrollment $record): void {
                    $record->exists = false;
                });
                Notification::make()->title(__('Creator approvals deleted'))->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function deleteEnrollmentDescription(): string
    {
        return __('Permanently deletes the application and review details.');
    }

    private static function deleteEnrollmentConsequences(): array
    {
        $access = app(CreatorAccessService::class);
        $items = [
            $access->enforced()
                ? __('New publishing stops; future scheduled posts return to review.')
                : __('Everyone mode still allows publishing.'),
        ];
        if ($access->enforced()) {
            $items[] = $access->activatesInstantly()
                ? __('Users can reapply for instant approval.')
                : __('Users can reapply for admin approval.');
        }
        $items[] = __('Accounts, published content and ID verification are kept.');

        return $items;
    }

    public static function revoke(): Action
    {
        return Action::make('revoke_creator_access')
            ->label(__('Revoke creator access'))->icon('heroicon-o-no-symbol')->color('danger')
            ->authorize(fn (User|CreatorEnrollment $record): bool => Gate::allows('update', self::user($record)))
            ->visible(fn (User|CreatorEnrollment $record): bool => self::user($record)->creatorEnrollment?->status === CreatorEnrollment::APPROVED_STATUS)
            ->requiresConfirmation()->modalDescription(self::revokeDescription())
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => self::revokeConsequences()]))
            ->fillForm(fn (User|CreatorEnrollment $record): array => self::enrollmentSnapshot($record))
            ->schema([
                Hidden::make('enrollment_id')->rules(['required', 'integer', 'min:0']),
                Hidden::make('application_version')->rules(['required', 'integer', 'min:0']),
                self::revokeReason(),
            ])
            ->action(function (User|CreatorEnrollment $record, array $data): void {
                $data = Validator::make($data, [
                    'enrollment_id' => ['required', 'integer', 'min:0'],
                    'application_version' => ['required', 'integer', 'min:0'],
                    'rejection_reason' => ['required', 'string', 'max:5000'],
                ])->validate();
                $enrollment = app(CreatorAccessService::class)->setStatus(self::user($record), auth()->user(), (int) $data['application_version'], CreatorEnrollment::REVOKED_STATUS, $data['rejection_reason'], expectedEnrollmentId: (int) $data['enrollment_id']);
                if ($enrollment->wasChanged('application_version')) {
                    CreatorEnrollmentNotifications::reviewed($enrollment);
                }
                $record->refresh();
                Notification::make()->title(__('Creator access revoked'))->success()->send();
            });
    }

    public static function bulkRevoke(): BulkAction
    {
        return BulkAction::make('revoke_creator_access')
            ->label(__('Revoke creator access'))->icon('heroicon-o-no-symbol')->color('danger')
            ->authorize(fn (): bool => Gate::allows('update', User::class))
            ->requiresConfirmation()->modalDescription(self::revokeDescription())
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => self::revokeConsequences()]))
            ->fillForm(fn (Collection $records): array => [
                'application_versions' => $records->pluck('application_version', 'id')->all(),
            ])
            ->schema([
                Hidden::make('application_versions')->rules(['required', 'array']),
                self::revokeReason(),
            ])
            ->action(function (Collection $records, array $data): void {
                $data = Validator::make($data, [
                    'application_versions' => ['required', 'array'],
                    'application_versions.*' => ['required', 'integer', 'min:0'],
                    'rejection_reason' => ['required', 'string', 'max:5000'],
                ])->validate();
                // Filament can filter out records that became unselectable after
                // the dialog opened. Do not silently revoke only the remainder.
                if ($records->count() !== count($data['application_versions'])
                    || $records->contains(fn (CreatorEnrollment $record): bool => !array_key_exists($record->id, $data['application_versions']))) {
                    throw ValidationException::withMessages(['creator' => __('The selected creator approvals have changed. Refresh them before reviewing.')]);
                }
                if ($records->contains(fn (CreatorEnrollment $record): bool => $record->status !== CreatorEnrollment::APPROVED_STATUS)) {
                    throw ValidationException::withMessages(['creator' => __('Only approved creators can be revoked. Use Delete creator approvals to allow reapplication.')]);
                }
                DB::transaction(function () use ($records, $data): void {
                    foreach ($records->sortBy('user_id') as $record) {
                        $version = $data['application_versions'][$record->id] ?? -1;
                        $enrollment = app(CreatorAccessService::class)->setStatus(self::user($record), auth()->user(), (int) $version, CreatorEnrollment::REVOKED_STATUS, $data['rejection_reason'], expectedEnrollmentId: (int) $record->id);
                        if ($enrollment->wasChanged('application_version')) {
                            CreatorEnrollmentNotifications::reviewed($enrollment);
                        }
                    }
                }, 3);
                Notification::make()->title(__('Creator access revoked'))->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function revokeDescription(): string
    {
        return __('Only an administrator can restore revoked approval.');
    }

    private static function revokeConsequences(): array
    {
        return [
            app(CreatorAccessService::class)->enforced()
                ? __('New publishing stops; future scheduled posts return to review.')
                : __('Everyone mode still allows publishing.'),
            __('Approval records, published content and ID verification are kept.'),
        ];
    }

    private static function revokeReason(): Textarea
    {
        return Textarea::make('rejection_reason')->label(__('Decision reason'))
            ->helperText(__('This explanation is shown to the user.'))
            ->required()->maxLength(5000)->rows(3);
    }

    public static function reviewIdentity(string $name = 'review_identity'): Action
    {
        return Action::make($name)
            ->label(__('ID verification'))
            ->icon('heroicon-o-identification')
            ->color('gray')
            ->visible(fn (User|CreatorEnrollment $record): bool => self::identityReviewUrl($record) !== null)
            ->url(fn (User|CreatorEnrollment $record): ?string => self::identityReviewUrl($record));
    }

    private static function identityReviewUrl(User|CreatorEnrollment $record): ?string
    {
        $user = $record instanceof User ? $record : $record->user;
        if (!$user) {
            return null;
        }
        $verification = $user->verification;

        if ($verification) {
            return UserVerifyResource::resolveRecordUrl($verification);
        }

        return UserVerifyResource::canViewAny()
            ? UserVerifyResource::getUrl('index', ['filters' => ['user_id' => ['value' => $user->id]]])
            : null;
    }

    private static function review(bool $approve): Action
    {
        return Action::make($approve ? 'approve_creator' : 'reject_creator')
            ->label($approve ? __('Approve creator') : __('Reject creator'))
            ->icon($approve ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color($approve ? 'success' : 'danger')
            ->authorize(fn (User|CreatorEnrollment $record): bool => Gate::allows('update', self::user($record)))
            ->visible(fn (User|CreatorEnrollment $record): bool => self::user($record)->creatorEnrollment?->status === CreatorEnrollment::PENDING_STATUS)
            ->requiresConfirmation()
            ->modalDescription(__('ID verification is managed separately.'))
            ->fillForm(fn (User|CreatorEnrollment $record): array => self::enrollmentSnapshot($record))
            ->schema([
                Hidden::make('enrollment_id')->rules(['required', 'integer', 'min:1']),
                Hidden::make('application_version')->rules(['required', 'integer', 'min:1']),
                Textarea::make('rejection_reason')
                    ->label(__('Rejection reason'))
                    ->helperText(__('This explanation is shown to the applicant.'))
                    ->required(!$approve)
                    ->maxLength(5000)
                    ->rows(3)
                    ->visible(!$approve),
            ])
            ->action(function (User|CreatorEnrollment $record, array $data) use ($approve): void {
                $user = self::user($record);
                Gate::authorize('update', $user);
                $data = Validator::make($data, [
                    'enrollment_id' => ['required', 'integer', 'min:1'],
                    'application_version' => ['required', 'integer', 'min:1'],
                    'rejection_reason' => [$approve ? 'nullable' : 'required', 'string', 'max:5000'],
                ])->validate();
                $enrollment = $user->creatorEnrollment()->firstOrFail();
                $enrollment = app(CreatorAccessService::class)->review(
                    $enrollment,
                    auth()->user(),
                    (int) $data['application_version'],
                    $approve,
                    $approve ? null : trim($data['rejection_reason']),
                    (int) $data['enrollment_id'],
                );
                CreatorEnrollmentNotifications::reviewed($enrollment);
                $record->refresh();
                Notification::make()
                    ->title($approve ? __('Creator application approved') : __('Creator application rejected'))
                    ->success()
                    ->send();
            });
    }

    public static function manage(): Action
    {
        return Action::make('manage_creator_access')
            ->label(__('Manage creator access'))
            ->icon('heroicon-o-user-circle')
            ->authorize(fn (User|CreatorEnrollment $record): bool => Gate::allows('update', self::user($record)))
            ->modalDescription(__('ID verification is managed separately.'))
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => self::manageConsequences()]))
            ->fillForm(function (User|CreatorEnrollment $record): array {
                $enrollment = self::user($record)->creatorEnrollment()->first();
                $status = $enrollment->status ?? CreatorEnrollment::APPROVED_STATUS;

                return [
                    'enrollment_id' => $enrollment->id ?? 0,
                    'application_version' => $enrollment->application_version ?? 0,
                    'status' => $status,
                    'rejection_reason' => $enrollment?->rejection_reason,
                ];
            })
            ->schema([
                Hidden::make('enrollment_id')->rules(['required', 'integer', 'min:0']),
                Hidden::make('application_version')->rules(['required', 'integer', 'min:0']),
                Select::make('status')->label(__('Creator status'))
                    ->options(fn (User|CreatorEnrollment $record): array => app(CreatorAccessService::class)->adminStatusOptions(self::user($record)->creatorEnrollment()->first()))
                    ->required()->live(),
                Textarea::make('rejection_reason')->label(__('Decision reason'))
                    ->helperText(__('This explanation is shown to the user. Removed access can only be restored by an administrator.'))
                    ->required(fn (Get $get): bool => in_array($get('status'), [CreatorEnrollment::REJECTED_STATUS, CreatorEnrollment::REVOKED_STATUS], true))
                    ->maxLength(5000)->rows(3)
                    ->visible(fn (Get $get): bool => in_array($get('status'), [CreatorEnrollment::REJECTED_STATUS, CreatorEnrollment::REVOKED_STATUS], true)),
            ])
            ->action(function (User|CreatorEnrollment $record, array $data): void {
                $user = self::user($record);
                Gate::authorize('update', $user);
                $data = Validator::make($data, [
                    'enrollment_id' => ['required', 'integer', 'min:0'],
                    'application_version' => ['required', 'integer', 'min:0'],
                    'status' => ['required', 'string'],
                    'rejection_reason' => ['nullable', 'string', 'max:5000'],
                ])->validate();
                $enrollment = app(CreatorAccessService::class)->setStatus($user, auth()->user(), (int) $data['application_version'], $data['status'], $data['rejection_reason'] ?? null, expectedEnrollmentId: (int) $data['enrollment_id']);
                if ($enrollment->wasRecentlyCreated || $enrollment->wasChanged('application_version')) {
                    CreatorEnrollmentNotifications::reviewed($enrollment);
                }
                $record->refresh();
                Notification::make()->title(__('Creator status saved'))->success()->send();
            });
    }

    private static function manageConsequences(): array
    {
        return app(CreatorAccessService::class)->enforced() ? [
            __('Removing approval stops new publishing; future scheduled posts return to review.'),
            __('Restoring approval does not republish those posts.'),
        ] : [__('Everyone mode still allows publishing.')];
    }

    private static function enrollmentSnapshot(User|CreatorEnrollment $record): array
    {
        $enrollment = self::user($record)->creatorEnrollment()->first();

        return ['enrollment_id' => $enrollment->id ?? 0, 'application_version' => $enrollment->application_version ?? 0];
    }

    private static function user(User|CreatorEnrollment $record): User
    {
        return $record instanceof User ? $record : $record->user()->firstOrFail();
    }
}
