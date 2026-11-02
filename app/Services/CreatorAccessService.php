<?php

namespace App\Services;

use App\Model\CreatorEnrollment;
use App\Model\Post;
use App\Model\User;
use App\Model\UserVerify;
use App\Providers\GenericHelperServiceProvider;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatorAccessService
{
    public const MODE_EVERYONE = 'everyone';
    public const MODE_INSTANT = 'instant';
    public const MODE_APPROVAL = 'approval';
    public const MODE_CLOSED = 'closed';

    public function mode(): string
    {
        $mode = getSettingFilament('profiles.creator_access_mode', self::MODE_EVERYONE);

        return in_array($mode, [self::MODE_EVERYONE, self::MODE_INSTANT, self::MODE_APPROVAL, self::MODE_CLOSED], true)
            ? $mode : self::MODE_APPROVAL;
    }

    public function enforced(): bool
    {
        return $this->mode() !== self::MODE_EVERYONE;
    }

    public function isCreator(?User $user): bool
    {
        return $user && $user->creatorEnrollment?->status === CreatorEnrollment::APPROVED_STATUS;
    }

    public function requiresApplication(): bool
    {
        return $this->enforced();
    }

    public function acceptsApplications(): bool
    {
        return in_array($this->mode(), [self::MODE_INSTANT, self::MODE_APPROVAL], true);
    }

    public function canAccessOnboarding(?User $user): bool
    {
        return !$this->enforced() || $this->acceptsApplications() || $user?->creatorEnrollment !== null;
    }

    public function activatesInstantly(): bool
    {
        return $this->mode() === self::MODE_INSTANT;
    }

    public function requiresApplicationMessage(): bool
    {
        return $this->mode() === self::MODE_APPROVAL
            && (bool) getSettingFilament('profiles.require_creator_application_message', false);
    }

    public function requiresIdentityVerification(?string $mode = null, ?bool $requireIdentity = null): bool
    {
        $mode ??= $this->mode();

        return in_array($mode, [self::MODE_INSTANT, self::MODE_APPROVAL, self::MODE_CLOSED], true)
            && ($requireIdentity ?? getSettingFilament('profiles.require_id_before_publishing', false));
    }

    public function canPublish(?User $user): bool
    {
        return $user && !$user->isBanned()
            && (!$this->enforced() || $this->isCreator($user))
            && (!$this->requiresIdentityVerification() || GenericHelperServiceProvider::isUserVerified($user));
    }

    public function publishingStatus(?User $user): string
    {
        return match (true) {
            $this->canPublish($user) => __('Ready to publish'),
            !$user => __('Unavailable'),
            $user->isBanned() => __('Suspended'),
            $this->enforced() && $user->creatorEnrollment?->status === CreatorEnrollment::REVOKED_STATUS => __('Creator access removed'),
            $this->requiresApplication() && !$this->isCreator($user) => __('Creator approval required'),
            default => __('ID required'),
        };
    }

    /**
     * Outstanding account requirements, including the separate site email gate.
     *
     * @return list<string>
     */
    public function publishingRequirements(User $user): array
    {
        $requirements = [];
        if ($user->isBanned()) {
            $requirements[] = __('Suspension lifted');
        }
        if ($this->enforced() && !$this->isCreator($user)) {
            $requirements[] = match ($user->creatorEnrollment?->status) {
                CreatorEnrollment::REVOKED_STATUS => __('Creator access restored'),
                null => $this->acceptsApplications() ? __('Creator application') : __('Creator approval'),
                default => __('Creator approval'),
            };
        }

        $requiresIdentity = $this->requiresIdentityVerification();
        if (($requiresIdentity || getSetting('site.enforce_email_validation', false)) && !$user->email_verified_at) {
            $requirements[] = __('Email confirmation');
        }
        if ($requiresIdentity) {
            if (!$user->birthdate) {
                $requirements[] = __('Birthdate');
            }
            if ($user->verification?->status !== UserVerify::APPROVED_STATUS) {
                $requirements[] = __('ID verification');
            }
        }

        return $requirements;
    }

    public function canEditPost(?User $user, Post $post): bool
    {
        if (!$user || $user->isBanned() || (int) $post->user_id !== (int) $user->id) {
            return false;
        }
        if ($this->canPublish($user)) {
            return true;
        }

        // Enrollment does not remove management of previously published content.
        return (int) $post->status === Post::APPROVED_STATUS
            && (!$post->release_date || Carbon::parse($post->release_date)->lte(now()))
            && (!$this->requiresIdentityVerification() || GenericHelperServiceProvider::isUserVerified($user));
    }

    public function showLockedPages(): bool
    {
        return $this->mode() !== self::MODE_CLOSED
            && (bool) getSettingFilament('profiles.show_locked_creation_pages', true);
    }

    public function canViewCreationPage(?User $user): bool
    {
        return $user && !$user->isBanned() && ($this->showLockedPages() || $this->canPublish($user));
    }

    public function restrictionMessage(?User $user): string
    {
        if (!$user) {
            return __('Please sign in to create content.');
        }
        if ($user->isBanned()) {
            return __('Publishing is unavailable while your account is suspended.');
        }
        if ($this->enforced() && $user->creatorEnrollment?->status === CreatorEnrollment::REVOKED_STATUS) {
            return __('Your creator access was removed. An administrator must restore it before you can publish.');
        }
        if ($this->requiresApplication() && !$this->isCreator($user)) {
            if (!$this->acceptsApplications() && in_array($user->creatorEnrollment?->status, [null, CreatorEnrollment::REJECTED_STATUS], true)) {
                return __('Creator applications are currently closed.');
            }
            return match ($user->creatorEnrollment?->status) {
                CreatorEnrollment::PENDING_STATUS => __('Your creator application is awaiting review.'),
                CreatorEnrollment::REJECTED_STATUS => __('Your creator application was rejected. Review the decision and apply again.'),
                CreatorEnrollment::REVOKED_STATUS => __('Your creator access was removed. An administrator must restore it before you can publish.'),
                default => __('Apply for creator access before publishing content.'),
            };
        }
        if ($this->requiresIdentityVerification() && !GenericHelperServiceProvider::isUserVerified($user)) {
            return __('Complete identity verification before publishing content.');
        }

        return '';
    }

    public function onboardingUrl(?User $user): string
    {
        if (!$user) {
            return route('login');
        }
        if ($user->isBanned()) {
            return route('account.suspension');
        }
        if (!$this->canAccessOnboarding($user)) {
            return route('my.settings');
        }
        $type = $this->enforced() && ($user->creatorEnrollment?->status === CreatorEnrollment::REVOKED_STATUS
            || ($this->requiresApplication() && !$this->isCreator($user))) ? 'creator' : 'verify';

        return route('my.settings', ['type' => $type]);
    }

    public function onboardingLabel(?User $user): string
    {
        if (!$user) {
            return __('Sign in');
        }
        if (!$this->canAccessOnboarding($user)) {
            return __('Creator applications closed');
        }
        if ($this->requiresApplication() && !$this->isCreator($user)) {
            return match ($user->creatorEnrollment?->status) {
                CreatorEnrollment::PENDING_STATUS => __('Application pending'),
                CreatorEnrollment::REJECTED_STATUS, CreatorEnrollment::REVOKED_STATUS => __('Creator application'),
                default => __('Become a creator'),
            };
        }

        return __('Complete ID Check');
    }

    public function agreementRequired(): bool
    {
        return GenericHelperServiceProvider::getModelAgreementPage() !== null;
    }

    public function pauseUnapprovedScheduledPosts(?string $mode = null, ?bool $requireIdentity = null): int
    {
        $mode ??= $this->mode();
        if ($mode === self::MODE_EVERYONE) {
            return 0;
        }
        $requiresIdentity = $this->requiresIdentityVerification($mode, $requireIdentity);
        // Future approved posts otherwise become public through their release-date scope.
        return Post::query()->where('status', Post::APPROVED_STATUS)->where('release_date', '>', now())
            ->whereDoesntHave('user', function ($query) use ($requiresIdentity): void {
                $query->whereHas('creatorEnrollment', fn ($enrollment) => $enrollment->where('status', CreatorEnrollment::APPROVED_STATUS));
                if ($requiresIdentity) {
                    // Match the same first verification record used by the user's has-one relation.
                    $query->whereNotNull('birthdate')->whereNotNull('email_verified_at')
                        ->where(fn ($verification) => $verification->select('status')->from('user_verifies')
                            ->whereColumn('user_verifies.user_id', 'users.id')->limit(1), UserVerify::APPROVED_STATUS);
                }
            })->update(['status' => Post::PENDING_STATUS]);
    }

    public function apply(User $user, bool $accepted, mixed $applicationMessage = null): CreatorEnrollment
    {
        if (!$this->requiresApplication()) {
            throw ValidationException::withMessages(['creator' => __('Creator applications are not available in this publishing mode.')]);
        }

        $enrollment = DB::transaction(function () use ($user, $accepted, $applicationMessage): CreatorEnrollment {
            if (!$this->acceptsApplications()) {
                throw ValidationException::withMessages(['creator' => __('Creator applications are currently closed.')]);
            }
            // Serialize first applications as well as reapplications on the owning account.
            $currentUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($currentUser->isBanned()) {
                throw new AuthorizationException;
            }
            $current = CreatorEnrollment::query()->where('user_id', $currentUser->id)->lockForUpdate()->first();
            if ($current && $current->status !== CreatorEnrollment::REJECTED_STATUS) {
                return $current;
            }
            $requiresAgreement = $this->agreementRequired();
            if ($requiresAgreement && !$accepted) {
                throw ValidationException::withMessages(['agreement_accepted' => __('Please accept the Creator Agreement.')]);
            }

            $instant = $this->activatesInstantly();
            $message = null;
            if (!$instant) {
                if ($applicationMessage !== null && !is_string($applicationMessage)) {
                    throw ValidationException::withMessages(['application_message' => __('Enter an application message of up to 5000 characters.')]);
                }
                $message = trim($applicationMessage ?? '');
                if (mb_strlen($message) > 5000) {
                    throw ValidationException::withMessages(['application_message' => __('Enter an application message of up to 5000 characters.')]);
                }
                if ($message === '' && $this->requiresApplicationMessage()) {
                    throw ValidationException::withMessages(['application_message' => __('Please tell us about your content before applying.')]);
                }
                $message = $message !== '' ? $message : null;
            }

            $current ??= new CreatorEnrollment;
            $current->forceFill([
                'user_id' => $currentUser->id,
                'status' => $instant ? CreatorEnrollment::APPROVED_STATUS : CreatorEnrollment::PENDING_STATUS,
                'application_message' => $message,
                'applied_at' => now(),
                'agreement_accepted_at' => $requiresAgreement ? now() : null,
                'reviewed_at' => $instant ? now() : null,
                'reviewed_by' => null,
                'rejection_reason' => null,
                'application_version' => (int) $current->application_version + 1,
            ])->save();

            return $current;
        }, 3);
        $user->setRelation('creatorEnrollment', $enrollment);

        return $enrollment;
    }

    public function review(CreatorEnrollment $enrollment, User $reviewer, int $expectedVersion, bool $approve, ?string $reason = null, ?int $expectedEnrollmentId = null): CreatorEnrollment
    {
        return $this->setStatus(
            User::query()->findOrFail($enrollment->user_id),
            $reviewer,
            $expectedVersion,
            $approve ? CreatorEnrollment::APPROVED_STATUS : CreatorEnrollment::REJECTED_STATUS,
            $reason,
            true,
            $expectedEnrollmentId ?? (int) $enrollment->id
        );
    }

    public function adminStatusOptions(?CreatorEnrollment $enrollment): array
    {
        $statuses = in_array($enrollment?->status, [CreatorEnrollment::APPROVED_STATUS, CreatorEnrollment::REVOKED_STATUS], true)
            ? [CreatorEnrollment::APPROVED_STATUS, CreatorEnrollment::REVOKED_STATUS]
            : [CreatorEnrollment::PENDING_STATUS, CreatorEnrollment::APPROVED_STATUS, CreatorEnrollment::REJECTED_STATUS];

        return array_intersect_key(CreatorEnrollment::statusLabels(), array_flip($statuses));
    }

    public function setStatus(User $user, User $reviewer, int $expectedVersion, string $status, ?string $reason = null, bool $pendingOnly = false, ?int $expectedEnrollmentId = null): CreatorEnrollment
    {
        $enrollment = DB::transaction(function () use ($user, $reviewer, $expectedVersion, $status, $reason, $pendingOnly, $expectedEnrollmentId): CreatorEnrollment {
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($reviewer)->authorize('update', $target);
            if ($reviewer->isBanned()) {
                throw new AuthorizationException;
            }
            $current = CreatorEnrollment::query()->where('user_id', $target->id)->lockForUpdate()->first();
            if (($current->application_version ?? 0) !== $expectedVersion
                || ($expectedEnrollmentId !== null && (int) ($current->id ?? 0) !== $expectedEnrollmentId)
                || ($pendingOnly && $current?->status !== CreatorEnrollment::PENDING_STATUS)) {
                throw ValidationException::withMessages(['creator' => __('This creator application has changed. Refresh it before reviewing.')]);
            }
            if (!array_key_exists($status, $this->adminStatusOptions($current))) {
                throw ValidationException::withMessages(['status' => __('Choose a valid creator status. Removed access can only be restored by an administrator.')]);
            }
            $reason = trim($reason ?? '');
            $needsReason = in_array($status, [CreatorEnrollment::REJECTED_STATUS, CreatorEnrollment::REVOKED_STATUS], true);
            if ($needsReason && ($reason === '' || mb_strlen($reason) > 5000)) {
                throw ValidationException::withMessages(['rejection_reason' => __('Enter a decision reason of up to 5000 characters.')]);
            }
            $reason = $needsReason ? $reason : null;
            if ($current && $current->status === $status && $current->rejection_reason === $reason) {
                return $current;
            }
            $current ??= new CreatorEnrollment;
            $current->forceFill([
                'user_id' => $target->id,
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $reason,
                'application_version' => (int) $current->application_version + 1,
            ])->save();
            if ($status === CreatorEnrollment::REVOKED_STATUS && $this->enforced()) {
                Post::query()->where('user_id', $target->id)->where('status', Post::APPROVED_STATUS)
                    ->where('release_date', '>', now())->update(['status' => Post::PENDING_STATUS]);
            }

            return $current;
        }, 3);
        $user->setRelation('creatorEnrollment', $enrollment);

        return $enrollment;
    }

    public function deleteEnrollment(CreatorEnrollment $enrollment, User $reviewer, int $expectedVersion): void
    {
        DB::transaction(function () use ($enrollment, $reviewer, $expectedVersion): void {
            $target = User::query()->whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($reviewer)->authorize('update', $target);
            if ($reviewer->isBanned()) {
                throw new AuthorizationException;
            }
            $current = CreatorEnrollment::query()->where('user_id', $target->id)->lockForUpdate()->first();
            if (!$current || (int) $current->id !== (int) $enrollment->id || $current->application_version !== $expectedVersion) {
                throw ValidationException::withMessages(['creator' => __('This creator application has changed. Refresh it before deleting.')]);
            }
            if ($this->enforced()) {
                Post::query()->where('user_id', $target->id)->where('status', Post::APPROVED_STATUS)
                    ->where('release_date', '>', now())->update(['status' => Post::PENDING_STATUS]);
            }
            if (!$current->delete()) {
                throw new \RuntimeException('The creator enrollment could not be deleted.');
            }
        }, 3);
        $enrollment->exists = false;
        if ($enrollment->relationLoaded('user')) {
            $enrollment->user?->setRelation('creatorEnrollment', null);
        }
    }
}
