<?php

namespace App\Services\Media;

use App\Http\Controllers\MessengerController;
use App\Model\Post;
use App\Model\User;
use App\Model\UserMessage;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\ProfileMonetizationServiceProvider;

class FileAttachmentAccess
{
    public static function canViewPostPublication(Post $post, ?User $viewer): bool
    {
        if ($viewer && ((int) $viewer->id === (int) $post->user_id || (int) $viewer->role_id === 1)) {
            return true;
        }

        return (int) $post->status === Post::APPROVED_STATUS
            && (!$post->release_date || $post->release_date <= now())
            && (!$post->expire_date || $post->expire_date > now());
    }

    public function canViewPost(Post $post, ?User $viewer): bool
    {
        if (!self::canViewPostPublication($post, $viewer) || !$post->user || (!$viewer && !$post->user->public_profile)) {
            return false;
        }
        if ($viewer && ((int) $viewer->id === (int) $post->user_id || (int) $viewer->role_id === 1)) {
            return true;
        }
        if ($viewer && GenericHelperServiceProvider::hasUserBlocked((int) $post->user_id, (int) $viewer->id)) {
            return false;
        }
        if ((float) $post->price > 0) {
            return $viewer && PostsHelperServiceProvider::userPaidForPost($viewer->id, $post->id);
        }

        return ProfileMonetizationServiceProvider::userHasOpenProfile($post->user)
            || ($viewer && (PostsHelperServiceProvider::hasActiveSub($viewer->id, $post->user_id)
                || (!ProfileMonetizationServiceProvider::userHasPaidProfile($post->user)
                    && ListsHelperServiceProvider::isUserFollowing($viewer->id, $post->user_id))));
    }

    public function canViewMessage(UserMessage $message, User $viewer): bool
    {
        $viewerId = (int) $viewer->id;
        if (!in_array($viewerId, [(int) $message->sender_id, (int) $message->receiver_id], true)) {
            return false;
        }
        $contactId = $viewerId === (int) $message->sender_id ? (int) $message->receiver_id : (int) $message->sender_id;
        if (!MessengerController::checkMessengerAccess($viewerId, $contactId)
            || GenericHelperServiceProvider::hasUserBlocked($contactId, $viewerId)) {
            return false;
        }

        return $viewerId === (int) $message->sender_id || (float) $message->price <= 0
            || PostsHelperServiceProvider::userPaidForMessage($viewerId, $message->id);
    }
}
