<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\CreatorEnrollments\CreatorEnrollmentResource;
use App\Filament\Resources\Users\Actions\UserCreatorActions;
use App\Filament\Resources\Users\UserResource;
use App\Model\CreatorEnrollment;
use App\Model\User;
use App\Model\UserVerify;
use App\Services\CreatorAccessService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserCreatorDetails
{
    public static function make(): Section
    {
        return Section::make(__('Creator access'))
            ->columnSpanFull()
            ->columns(1)
            ->hiddenOn('create')
            ->visible(fn (User|CreatorEnrollment|UserVerify|null $record): bool => (bool) $record?->exists
                && ($record instanceof User || (bool) $record->user?->exists))
            ->schema([
                Group::make([
                    Placeholder::make('creator_publishing_status')
                        ->label(__('Publishing status'))->inlineLabel()->alignEnd()
                        ->extraEntryWrapperAttributes(['class' => 'creator-access-publishing'])
                        ->content(fn (User|CreatorEnrollment|UserVerify $record): string => app(CreatorAccessService::class)->publishingRequirements(self::user($record)) === [] ? __('Ready to publish') : __('Cannot publish yet'))
                        ->badge()
                        ->color(fn (User|CreatorEnrollment|UserVerify $record): string => app(CreatorAccessService::class)->publishingRequirements(self::user($record)) === [] ? 'success' : 'warning')
                        ->helperText(fn (User|CreatorEnrollment|UserVerify $record): ?string => ($requirements = app(CreatorAccessService::class)->publishingRequirements(self::user($record)))
                            ? __('Missing: :requirements.', ['requirements' => implode(', ', $requirements)]) : null),
                    Placeholder::make('creator_enrollment_status')
                        ->label(__('Creator approval'))->inlineLabel()->alignEnd()
                        ->content(fn (User|CreatorEnrollment|UserVerify $record): string => CreatorEnrollment::statusLabels()[self::user($record)->creatorEnrollment?->status] ?? __('No application')),
                    Placeholder::make('creator_identity_status')
                        ->label(__('ID documents'))->inlineLabel()->alignEnd()
                        ->content(fn (User|CreatorEnrollment|UserVerify $record): string => match (self::user($record)->verification?->status) {
                            UserVerify::REQUESTED_STATUS => __('Pending'),
                            UserVerify::APPROVED_STATUS => __('Approved'),
                            UserVerify::REJECTED_STATUS => __('Rejected'),
                            default => __('No submission'),
                        }),
                ])->dense()->extraAttributes(['class' => 'creator-access-records']),
                Placeholder::make('creator_rejection_reason')
                    ->label(__('Decision reason'))
                    ->content(fn (User|CreatorEnrollment|UserVerify $record): string => $record instanceof User ? ($record->creatorEnrollment->rejection_reason ?? '') : '')
                    ->visible(fn (User|CreatorEnrollment|UserVerify $record): bool => $record instanceof User
                        && in_array($record->creatorEnrollment?->status, [CreatorEnrollment::REJECTED_STATUS, CreatorEnrollment::REVOKED_STATUS], true))
                    ->columnSpanFull(),
            ])
            ->headerActions([fn (User|CreatorEnrollment|UserVerify|null $record): ActionGroup => self::recordActions($record)]);
    }

    private static function recordActions(User|CreatorEnrollment|UserVerify|null $record): ActionGroup
    {
        $actions = [];
        if ($record && !$record instanceof User) {
            $actions[] = Action::make('user_record')
                ->label(__('User'))->icon('heroicon-o-user')->color('gray')
                ->visible(fn (CreatorEnrollment|UserVerify $record): bool => self::userUrl($record) !== null)
                ->url(fn (CreatorEnrollment|UserVerify $record): ?string => self::userUrl($record));
        }
        if ($record && !$record instanceof CreatorEnrollment) {
            $actions[] = Action::make('user_creator_approval')
                ->label(__('Creator approval'))->icon('heroicon-o-user-circle')->color('gray')
                ->visible(fn (User|UserVerify $record): bool => self::creatorApprovalUrl(self::user($record)) !== null)
                ->url(fn (User|UserVerify $record): ?string => self::creatorApprovalUrl(self::user($record)));
        }
        if ($record && !$record instanceof UserVerify) {
            $actions[] = UserCreatorActions::reviewIdentity('user_identity_review')->label(__('ID check'));
        }

        return ActionGroup::make($actions)
            ->label(__('View records'))
            ->icon('heroicon-o-chevron-down')->iconPosition(IconPosition::After)
            ->color('gray')->button()->size(Size::Small)
            ->dropdownPlacement('bottom-end');
    }

    private static function user(User|CreatorEnrollment|UserVerify $record): User
    {
        if ($record instanceof User) {
            return $record;
        }
        $user = $record->user;
        if (!$user) {
            throw (new ModelNotFoundException)->setModel(User::class);
        }
        $user->loadMissing(['creatorEnrollment', 'verification', 'activeBan']);
        $user = clone $user;
        if ($record instanceof CreatorEnrollment) {
            $user->setRelation('creatorEnrollment', $record);
        } elseif ($user->verification?->is($record)) {
            // Use the saved review decision without replacing a different canonical ID record.
            $user->setRelation('verification', $record);
        }

        return $user;
    }

    private static function userUrl(CreatorEnrollment|UserVerify $record): ?string
    {
        $user = self::user($record);
        if (UserResource::canView($user)) {
            return UserResource::getUrl('view', ['record' => $user]);
        }

        return UserResource::canEdit($user) ? UserResource::getUrl('edit', ['record' => $user]) : null;
    }

    private static function creatorApprovalUrl(User $user): ?string
    {
        $enrollment = $user->creatorEnrollment;

        return $enrollment && CreatorEnrollmentResource::canView($enrollment)
            ? CreatorEnrollmentResource::getUrl('view', ['record' => $enrollment]) : null;
    }
}
