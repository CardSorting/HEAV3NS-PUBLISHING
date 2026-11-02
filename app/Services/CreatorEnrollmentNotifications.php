<?php

namespace App\Services;

use App\Model\CreatorEnrollment;
use App\Model\User;
use App\Providers\EmailsServiceProvider;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatorEnrollmentNotifications
{
    public static function submitted(CreatorEnrollment $enrollment): void
    {
        self::afterCommit($enrollment, function () use ($enrollment): void {
            if ($enrollment->status === CreatorEnrollment::APPROVED_STATUS) {
                self::reviewed($enrollment);

                return;
            }

            foreach (User::query()->where('role_id', 1)->get(['id', 'name', 'email', 'settings']) as $admin) {
                self::send($enrollment, $admin, fn (): array => [
                    'subject' => __('New creator application'),
                    'content' => __('A creator application is ready for review. Open Creator approvals in the admin panel to review it.'),
                    'button' => ['text' => __('Creator approvals'), 'url' => route('filament.admin.resources.creator-enrollments.index')],
                ]);
            }
        });
    }

    public static function reviewed(CreatorEnrollment $enrollment): void
    {
        if (!array_key_exists($enrollment->status, CreatorEnrollment::statusLabels())) {
            return;
        }

        self::afterCommit($enrollment, function () use ($enrollment): void {
            $access = app(CreatorAccessService::class);
            if (!$access->enforced()) {
                return;
            }
            $requiresIdentity = $access->requiresIdentityVerification();
            $user = $enrollment->user;
            if (!$user) {
                return;
            }

            self::send($enrollment, $user, fn (): array => [
                'subject' => match ($enrollment->status) {
                    CreatorEnrollment::APPROVED_STATUS => $enrollment->applied_at ? __('Your creator application was approved.') : __('Your creator access is active.'),
                    CreatorEnrollment::REJECTED_STATUS => __('Your creator application was rejected.'),
                    CreatorEnrollment::REVOKED_STATUS => __('Your creator access was removed.'),
                    default => __('Your creator application is pending review.'),
                },
                'content' => match ($enrollment->status) {
                    CreatorEnrollment::APPROVED_STATUS => $requiresIdentity
                        ? __('Your creator access is approved. Complete identity verification is required before publishing.')
                        : __('Your creator access is active. Any required identity verification remains a separate step before publishing.'),
                    CreatorEnrollment::REVOKED_STATUS => __('Open your creator settings to read the explanation. An administrator must restore your creator access.'),
                    CreatorEnrollment::REJECTED_STATUS => __('Open your creator settings to read the explanation and apply again.'),
                    default => __('An administrator will review your creator application.'),
                },
                'button' => ['text' => __('View creator application'), 'url' => route('my.settings', ['type' => 'creator'])],
            ]);
        });
    }

    private static function afterCommit(CreatorEnrollment $enrollment, Closure $notification): void
    {
        $deliver = function () use ($enrollment, $notification): void {
            try {
                $notification();
            } catch (Throwable $exception) {
                Log::warning('Creator application notification failed.', ['enrollment_id' => $enrollment->id]);
            }
        };
        if ($enrollment->getConnection()->transactionLevel() > 0) {
            $enrollment->getConnection()->afterCommit($deliver);
        } else {
            $deliver();
        }
    }

    private static function send(CreatorEnrollment $enrollment, User $recipient, Closure $message): void
    {
        $locale = App::getLocale();
        try {
            App::setLocale($recipient->settings['locale'] ?? 'en');
            $message = $message();
            EmailsServiceProvider::sendGenericEmail([
                'email' => $recipient->email,
                'subject' => $message['subject'],
                'title' => __('Hello, :name,', ['name' => $recipient->name]),
                'content' => $message['content'],
                'button' => $message['button'],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Creator application notification failed.', [
                'enrollment_id' => $enrollment->id,
                'recipient_id' => $recipient->id,
            ]);
        } finally {
            App::setLocale($locale);
        }
    }
}
