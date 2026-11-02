<?php

namespace App\Observers;

use App\Model\Attachment;
use App\Model\ReleaseForm;
use App\Model\User;
use App\Providers\EmailsServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReleaseFormsObserver
{
    public function saving(ReleaseForm $releaseForm): void
    {
        if (
            $releaseForm->isDirty('status') &&
            $releaseForm->status !== ReleaseForm::PENDING_STATUS &&
            !$releaseForm->reviewed_at
        ) {
            $releaseForm->reviewed_at = now();
            $releaseForm->reviewed_by = Auth::id();
        }
    }

    public function saved(ReleaseForm $releaseForm): void
    {
        if (
            $releaseForm->getOriginal('status') === ReleaseForm::PENDING_STATUS &&
            $releaseForm->status !== ReleaseForm::PENDING_STATUS
        ) {
            $this->sendReviewEmail($releaseForm);
        }
    }

    public function deleting(ReleaseForm $releaseForm): void
    {
        $this->deleteFiles((int) $releaseForm->user_id, $this->decodeFiles($releaseForm->files));
    }

    private function decodeFiles(?array $files): array
    {
        if (!$files) {
            return [];
        }

        return array_values(array_filter($files));
    }

    private function deleteFiles(int $userId, array $files): void
    {
        if (empty($files)) {
            return;
        }

        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $attachments = Attachment::query()
            ->where('user_id', $userId)
            ->whereIn('filename', $files)
            ->get()
            ->keyBy('filename');

        foreach ($files as $file) {
            try {
                $attachment = $attachments->get($file);

                if ($attachment) {
                    $attachment->delete();
                    continue;
                }

                if ($storage->exists($file)) {
                    $storage->delete($file);
                }
            } catch (\Exception $exception) {
                Log::error("Failed deleting release form file: {$file}, e: ".$exception->getMessage());
            }
        }
    }

    private function sendReviewEmail(ReleaseForm $releaseForm): void
    {
        $user = User::find($releaseForm->user_id);

        if (!$user) {
            return;
        }

        try {
            App::setLocale($user->settings['locale'] ?? 'en');
        } catch (\Exception $exception) {
            App::setLocale('en');
        }

        $emailSubject = $releaseForm->status === ReleaseForm::APPROVED_STATUS
            ? __('Your release form was approved.')
            : __('Your release form was rejected.');

        EmailsServiceProvider::sendGenericEmail([
            'email' => $user->email,
            'subject' => $emailSubject,
            'title' => __('Hello, :name,', ['name' => $user->name]),
            'content' => $releaseForm->status === ReleaseForm::APPROVED_STATUS
                ? __('Your release form on :siteName has been approved.', ['siteName' => getSetting('site.name')])
                : __('Your release form on :siteName was rejected. Please review the rejection reason and submit a new form if needed.', ['siteName' => getSetting('site.name')]),
            'button' => [
                'text' => __('View release forms'),
                'url' => route('my.settings', ['type' => 'release-forms']),
            ],
        ]);
    }
}
