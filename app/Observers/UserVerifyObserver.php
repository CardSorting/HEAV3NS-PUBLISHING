<?php

namespace App\Observers;

use App\Jobs\DeleteAttachmentFilesJob;
use App\Model\Attachment;
use App\Model\User;
use App\Model\UserVerify;
use App\Providers\EmailsServiceProvider;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class UserVerifyObserver
{
    /**
     * Listen to the User updating event.
     *
     * @param  UserVerify  $userVerify
     * @return void
     */
    public function saving(UserVerify $userVerify)
    {
        if (
            $userVerify->getOriginal('status') === UserVerify::REQUESTED_STATUS &&
            $userVerify->status !== UserVerify::REQUESTED_STATUS
        ) {
            if ($userVerify->status === UserVerify::REJECTED_STATUS) {
                $emailSubject = __('Your identity check failed.');
                $button = [
                    'text' => __('Try again'),
                    'url' => route('my.settings', ['type' => 'verify']),
                ];
            } elseif ($userVerify->status === UserVerify::APPROVED_STATUS) {
                $emailSubject = __('Your identity check passed.');
                $button = [
                    'text' => __('View identity verification'),
                    'url' => route('my.settings', ['type' => 'verify']),
                ];
            } else {
                return;
            }

            $user = User::find($userVerify->user_id);

            try {
                App::setLocale($user->settings['locale'] ?? 'en');
            } catch (\Exception $e) {
                App::setLocale('en');
            }

            EmailsServiceProvider::sendGenericEmail([
                'email' => $user->email,
                'subject' => $emailSubject,
                'title' => __('Hello, :name,', ['name' => $user->name]),
                'content' => __('Email identity checked', [
                    'siteName' => getSetting('site.name'),
                    'status' => __($userVerify->status),
                ]),
                'button' => $button,
            ]);
        }
    }

    public function updated(UserVerify $userVerify): void
    {
        if (!$userVerify->wasChanged('status')
            || !in_array($userVerify->status, [UserVerify::APPROVED_STATUS, UserVerify::REJECTED_STATUS], true)
            || !getSetting('compliance.delete_verification_assets_after_review', false)
            || !$this->decodeFiles($userVerify->files)) {
            return;
        }

        $reviewed = clone $userVerify;
        $cleanup = function () use ($reviewed): void {
            try {
                $reviewed->getConnection()->transaction(function () use ($reviewed): void {
                    $current = $reviewed->newQuery()->lockForUpdate()->find($reviewed->id);
                    // A resubmission or another decision may have replaced this review before commit.
                    if (!$current || $current->status !== $reviewed->status || $current->files !== $reviewed->files) {
                        return;
                    }

                    $remaining = $this->deleteVerificationFiles($current->user_id, $this->decodeFiles($current->files));
                    $current->updateQuietly(['files' => json_encode($remaining)]);
                });
            } catch (\Throwable $exception) {
                Log::error('Failed cleaning reviewed verification assets.', [
                    'verification_id' => $reviewed->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        };

        if ($userVerify->getConnection()->transactionLevel() > 0) {
            $userVerify->getConnection()->afterCommit($cleanup);
        } else {
            $cleanup();
        }
    }

    public function deleting(UserVerify $userVerify): void
    {
        $this->deleteVerificationFiles($userVerify->user_id, $this->decodeFiles($userVerify->files));
    }

    private function decodeFiles(?string $files): array
    {
        if (!$files) {
            return [];
        }

        $decoded = json_decode($files, true);

        return is_array($decoded)
            ? array_values(array_unique(array_filter($decoded, fn ($file) => is_string($file) && $file !== '')))
            : [];
    }

    /** @return list<string> Files whose cleanup could not be scheduled. */
    private function deleteVerificationFiles(int $userId, array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $remaining = [];
        $attachments = Attachment::query()
            ->where('user_id', $userId)
            ->whereIn('filename', $files)
            ->get()
            ->keyBy('filename');

        foreach ($files as $file) {
            try {
                $attachment = $attachments->get($file);

                if ($attachment) {
                    if ($attachment->delete() === false) {
                        throw new \RuntimeException('Verification attachment deletion was cancelled.');
                    }
                    continue;
                }

                // Legacy verification files may have no attachment row; use the same retryable cleanup queue.
                app(Dispatcher::class)->dispatch(new DeleteAttachmentFilesJob(
                    config('filesystems.defaultFilesystemDriver') ?: config('filesystems.default'),
                    [$file],
                ));
            } catch (\Throwable $exception) {
                $remaining[] = $file;
                Log::error("Failed deleting verification file: {$file}, e: ".$exception->getMessage());
            }
        }

        return $remaining;
    }
}
