<?php

namespace App\Observers;

use App\Helpers\PaymentHelper;
use App\Model\Attachment;
use App\Model\ContentReview;
use App\Model\ReferralCodeUsage;
use App\Providers\AttachmentServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Model\User;
use App\Services\Media\ProfileAssetService;
use App\Services\UserBanService;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UsersObserver
{
    /**
     * Listen to the User deleting event.
     *
     * @param User $user
     * @return void
     */
    public function deleting(User $user)
    {
        if (app(UserBanService::class)->hasPendingRecovery($user)) {
            throw ValidationException::withMessages(['user' => __('Subscription cancellation is still pending. Confirm billing has stopped before deleting this record.')]);
        }

        // Cancelling all user subscriptions
        $paymentHelper = new PaymentHelper();
        foreach ($user->activeSubscriptions()->get() as $subscription) {
            try {
                $cancelSubscription = $paymentHelper->cancelSubscription($subscription);
                if (!$cancelSubscription) {
                    Log::error("Failed cancelling subscription for id: ".$subscription->id);
                }
            } catch (\Exception $exception) {
                Log::error("Failed cancelling subscription for id: ".$subscription->id." error: ".$exception->getMessage());
            }
        }

        // Removing user avatar/cover / current driver assuming
        try {
            $data = User::where('id', $user->id)->selectRaw('cover as cleanCover, avatar as cleanAvatar')->first();
            $data = $data->getOriginal();
            $keys = array_values(array_filter([$data['cleanCover'], $data['cleanAvatar']]));
            if ($keys) {
                app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(
                    new \App\Jobs\DeleteAttachmentFilesJob(config('filesystems.defaultFilesystemDriver'), $keys)
                );
            }
        } catch (\Exception $exception) {
            Log::error("Failed deleting avatar or cover for: ".$user->id.", cover:{$user->cover}, avatar:{$user->avatar},  e: ".$exception->getMessage());
            throw $exception;
        }

        // Removing all attachments of the user / per driver
        $userAttachments = Attachment::where('user_id', $user->id)->get();
        foreach($userAttachments as $attachment){
            app(\App\Services\Media\AttachmentLifecycleService::class)->deleteEverywhere($attachment);
        }

    }

    public function deleted(User $user): void
    {
        // Account deletion cascades to content without firing its model events.
        ContentReview::on($user->getConnectionName())->where('owner_user_id', $user->id)
            ->where('status', ContentReview::STATUS_PENDING)
            ->update(['status' => ContentReview::STATUS_CANCELLED]);
    }

    /**
     * Listen to the User created event.
     *
     * @param User $user
     * @return void
     */
    public function created(User $user) {
        if ($user != null) {
            // Wallet, lists and 2FA
            GenericHelperServiceProvider::createUserWallet($user);
            ListsHelperServiceProvider::createUserDefaultLists($user->id);
            if(getSetting('security.default_2fa_on_register')) {
                AuthServiceProvider::addNewUserDevice($user->id, true);
            }

            // Default profiles to follow
            if(getSetting('profiles.default_users_to_follow')){
                $usersToFollow = getSetting('profiles.default_users_to_follow');
                if(count($usersToFollow)){
                    foreach($usersToFollow as $userID){
                        ListsHelperServiceProvider::managePredefinedUserMemberList($user->id, $userID, 'follow');
                    }
                }
            }

            // Referrals work
            if(getSetting('payments.referrals_enabled')) {
                // Saving the referral even if the case
                if(Cookie::has('referral')){
                    $referralID = User::where('referral_code', Cookie::get('referral'))->first();
                    if($referralID){
                        $existing = ReferralCodeUsage::where(['used_by' => $user->id, 'referral_code' => $referralID->referral_code])->first();
                        if(!$existing) {
                            ReferralCodeUsage::create(['used_by' => $user->id, 'referral_code' => $referralID->referral_code]);
                            Cookie::queue(Cookie::forget('referral'));
                            if(getSetting('payments.referrals_auto_follow_the_user')){
                                ListsHelperServiceProvider::managePredefinedUserMemberList($user->id, $referralID->id, 'follow');
                            }
                        }
                    }
                }
            }

            // Updating default settings
            $user->update([
                'settings' => collect([
                    'notification_email_new_sub' => 'true',
                    'notification_email_new_message' => config('settings.notification_email_new_message', 'false'),
                    'notification_email_expiring_subs' => 'true',
                    'notification_email_new_tip' => 'true',
                    'notification_email_new_comment' => 'false',
                    'notification_email_new_post_created' => getSetting('profiles.default_new_post_notification_setting') ? 'true' : 'false',
                    'locale' => getSetting('site.default_site_language'),
                    'notification_email_new_ppv_unlock' => 'true',
                    'notification_email_creator_went_live' => 'false',
                    'notification_push_enabled' => 'false',
                    'notification_toast_enabled' => 'true',
                ]),
            ]);
        }
    }

    /**
     * Listen to the User updating event.
     *
     * @param User $user
     * @return void
     */
    public function updating(User $user) {
        // fixes the problem with admin panel saving invalid paths for user avatar and cover
        if($user->isDirty('avatar') && $user->getOriginal('avatar')) {
            // make sure we don't use the same files
            if(basename($user->avatar) === basename($user->getOriginal('avatar'))) {
                unset($user->avatar);
            }

            // Presigned urls workaround
            if (!empty($user->getAttributes()['avatar']) && getSetting('storage.driver') == 's3') {
                if (getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
                    if(!AttachmentServiceProvider::getFileNameFromUrl($user->avatar)){
                        unset($user->avatar);
                    }
                }
            }

        }
        if($user->isDirty('cover') && $user->getOriginal('cover')) {

            if(basename($user->cover) === basename($user->getOriginal('cover'))) {
                unset($user->cover);
            }

            // Presigned urls workaround
            if (!empty($user->getAttributes()['cover']) && getSetting('storage.driver') == 's3') {
                if (getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
                    if(!AttachmentServiceProvider::getFileNameFromUrl($user->cover)){
                        unset($user->cover);
                    }
                }
            }

        }
    }

    public function updated(User $user): void
    {
        foreach (['avatar', 'cover'] as $field) {
            if (!$user->wasChanged($field)) {
                continue;
            }
            $oldPath = $user->getRawOriginal($field);
            if (ProfileAssetService::isManagedPath($oldPath)
                && $oldPath !== ($user->getAttributes()[$field] ?? null)) {
                $disk = (string) (config('filesystems.defaultFilesystemDriver') ?: config('filesystems.default', 'public'));
                DB::afterCommit(function () use ($disk, $oldPath): void {
                    try {
                        if (!Storage::disk($disk)->delete($oldPath)) {
                            Log::warning('Could not delete previous profile image.', ['disk' => $disk, 'path' => $oldPath]);
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }
        }
    }
}
