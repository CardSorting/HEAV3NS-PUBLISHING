<?php

namespace App\Observers;

use App\Model\MessageTemplate;
use App\Model\UserList;
use App\Model\UserListMember;
use App\Providers\PostsHelperServiceProvider;
use App\Services\MessageTemplateDispatchService;

class UserListMembersObserver
{
    public function created(UserListMember $userListMember): void
    {
        $userListMember->loadMissing('userList');

        if (!$userListMember->userList || $userListMember->userList->type !== UserList::FOLLOWING_TYPE) {
            return;
        }

        $creatorId = (int) $userListMember->user_id;
        $followerId = (int) $userListMember->userList->user_id;

        if (PostsHelperServiceProvider::hasActiveSub($followerId, $creatorId)) {
            return;
        }

        dispatch(function () use ($creatorId, $followerId) {
            app(MessageTemplateDispatchService::class)->dispatchForTrigger(
                $creatorId,
                $followerId,
                MessageTemplate::TRIGGER_FOLLOWER_CREATED
            );
        })->afterResponse();
    }
}
