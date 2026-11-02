<?php

namespace App\Providers;

use App\Model\UserList;
use App\Model\UserListMember;
use App\Model\UserReport;
use App\Model\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class ListsHelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Check if an user is part of a list.
     *
     * @param $lists
     * @param $user_id
     * @return bool
     */
    public static function isMemberList($lists, $user_id)
    {
        foreach ($lists as $list) {
            if ($user_id == $list->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns list details (posts and members counts).
     *
     * @param $list_id
     * @param $user_id
     * @return array
     */
    public static function getListDetails($list_id, $user_id)
    {
        $list = UserList::with(['members.user.posts'])->where('id', $list_id)->first();
        $postsCount = 0;
        foreach ($list->members as $member) {
            $postsCount += count($member->user->posts);
        }

        return [
            'posts_count' => $postsCount,
            'members_count' => count($list->members),
        ];
    }

    /**
     * Returns users within a list.
     * @param $listID
     * @return mixed
     */
    public static function getListMembers($listID)
    {
        $users = UserListMember::where('list_id', $listID)->pluck('user_id')->toArray();
        return $users;
    }

    /**
     * Returns all the lists of an user.
     * @return \Illuminate\Support\Collection
     */
    public static function getUserLists()
    {
        $lists = UserList::with(['members.user.posts'])
            ->where('user_id', Auth::user()->id)
            ->get()
            ->each(function ($item, $key) {
                $item->setAttribute('posts_count', 0);
                foreach ($item->members as $member) {
                    $item->setAttribute('posts_count', $item->getAttribute('posts_count') + count($member->user->posts));
                }
                $item->setRelation('members', self::getUsersForListMembers($item->members));
                return $item;
            });

        // Take the first two, reverse the rest, then merge back together
        $firstTwo = $lists->take(2);
        $rest = $lists->skip(2)->reverse();

        // Concatenate the first two with the reversed remainder
        $lists = $firstTwo->concat($rest);

        return $lists;
    }

    /**
     * Returns all the lists of an user.
     * @return array
     */
    public static function getUserListTrimmed()
    {
        $lists = self::getUserLists();
        $filteredLists = [];
        foreach($lists as $list){
            $filteredLists[$list->name] = $list->id;
        }
        return $filteredLists;
    }

    /**
     * Creates a "virtual" list, holding all of the user followers.
     * @return UserList
     */
    public static function getUserFollowersList() {
        $followersList = new UserList();
        $followersList->name = __("Followers");
        $followersList->type = UserList::FOLLOWERS_TYPE;
        $followersList->user_id = Auth::user()->id;
        $followersList->setAttribute('posts_count', 0);
        $followers = self::getUserFollowers(Auth::user()->id);
        $followers = collect($followers)->pluck('user_id');
        $followers = User::whereIn('id', $followers)->withCount('posts')->get();
        $followersList->setAttribute('posts_count', 0);
        foreach($followers as $follower){
            $followersList->setAttribute('posts_count', $followersList->getAttribute('posts_count') + $follower->posts_count);
        }
        $followersList->setRelation('members', $followers);
        return $followersList;
    }

    public static function getUsersForListMembers($members) {
        $filteredUsers = [];
        foreach ($members as $member) {
            $filteredUsers[] = $member->user;
        }
        $members = collect($filteredUsers)->reverse();
        return $members;
    }

    /**
     * Creates default followers and blocked lists for an user.
     *
     * @param $user_id
     */
    public static function createUserDefaultLists($user_id)
    {
        $lists = [
            [
                'type' => 'following',
                'name' => 'Following',
            ],
            [
                'type' => 'blocked',
                'name' => 'Blocked',
            ],
        ];

        foreach ($lists as $list) {
            UserList::firstOrCreate(
                [
                    'user_id' => $user_id,
                    'type'    => $list['type'],
                ],
                [
                    'name'       => $list['name'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Add/Remove users out of following/block lists easily.
     *
     * @param $user_id
     * @param $recipient_id
     * @param $type
     * @return \Illuminate\Http\JsonResponse
     */
    public static function managePredefinedUserMemberList($user_id, $recipient_id, $type)
    {
        $listFollowID = UserList::where('user_id', $user_id);
        if ($type == 'follow') {
            $listFollowID = $listFollowID->where('type', 'following')->select('id')->first();
            return self::addListMember($listFollowID->id, $recipient_id, false);
        } elseif ($type == 'unfollow') {
            $listFollowID = $listFollowID->where('type', 'following')->select('id')->first();
            return self::deleteListMember($listFollowID->id, $recipient_id, false);
        }

        return response()->json(['success' => false, 'errors' => [__('Invalid list action.')], 'message'=> __('Invalid list action.')], 400);
    }

    /**
     * Adds a member to a list.
     *
     * @param $listID
     * @param $userID
     * @param bool $returnData
     * @return \Illuminate\Http\JsonResponse
     */
    public static function addListMember($listID, $userID, $returnData = true)
    {
        try {
            $data = [
                'list_id' => $listID,
                'user_id' => $userID,
            ];
            UserListMember::create($data);
            if ($returnData) {
                return response()->json(['success' => true, 'message' => __('Member added to list.'), 'data' => self::getListDetails($listID, $userID)]);
            } else {
                return response()->json(['success' => true, 'message' => __('Member added to list.')]);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.')], 'message'=>$exception->getMessage()], 500);
        }
    }

    /**
     * Deletes a member out a list.
     *
     * @param $listID
     * @param $userID
     * @param bool $returnData
     * @return \Illuminate\Http\JsonResponse
     */
    public static function deleteListMember($listID, $userID, $returnData = true)
    {
        try {
            $data = [
                'list_id' => $listID,
                'user_id' => $userID,
            ];
            $userListMember = UserListMember::where($data)->first();
            if($userListMember){
                $userListMember->delete();
            }
            else{
                return response()->json(['success' => false, 'errors' => [__('Member not found.')], 'message'=> __('Member not found.')]);
            }

            if ($returnData) {
                return response()->json(['success' => true, 'message' => __('Member removed from list.'), 'data' => self::getListDetails($listID, $userID)]);
            } else {
                return response()->json(['success' => true, 'message' => __('Member removed from list.')]);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.')], 'message'=>$exception->getMessage()]);
        }
    }

    /**
     * Returns list of report types.
     * @return array
     */
    public static function getReportTypes()
    {
        return UserReport::$typesMap;
    }

    /**
     * Check if current logged user is following specific user.
     * @param $userId
     * @return bool
     */
    public static function loggedUserIsFollowingUser($userId)
    {
        if(Auth::user()){
            $loggedUserId = Auth::user()->id;
            $userFollowersListId = UserList::query()->where(['user_id' => $loggedUserId, 'type' => 'following'])->select('id')->first();
            if ($userFollowersListId != null) {
                $userListMember = UserListMember::query()->where(['user_id' => $userId, 'list_id' => $userFollowersListId->id])->select('id')->first();
                if ($userListMember != null) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks follow relation between two users.
     * @param $fromUserID
     * @param $toUserID
     * @return bool
     */
    public static function isUserFollowing($fromUserID, $toUserID) {
        $loggedUserId = $fromUserID;
        $userId = $toUserID;
        $userFollowersListId = UserList::query()->where(['user_id' => $loggedUserId, 'type' => 'following'])->select('id')->first();
        if ($userFollowersListId != null) {
            $userListMember = UserListMember::query()->where(['user_id' => $userId, 'list_id' => $userFollowersListId->id])->select('id')->first();
            if ($userListMember != null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check current user following type.
     * @param $userId
     * @param bool $getTranslated
     * @return array|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function getUserFollowingType($userId, $getTranslated = false)
    {
        if (self::loggedUserIsFollowingUser($userId)) {
            if($getTranslated){
                return __('Unfollow');
            }
            return 'unfollow';
        } else {
            if($getTranslated){
                return __('Follow');
            }
            return 'follow';
        }
    }

    /**
     * Return the counts and list identifier used by the logged-in sidebar.
     *
     * @return array{followers_count: int, following_count: int, following_list_id: int|null}
     */
    public static function getUserFollowStats(int $userId): array
    {
        $followingList = UserList::query()
            ->where('user_id', $userId)
            ->where('type', UserList::FOLLOWING_TYPE)
            ->withCount('members')
            ->first();

        $followersCount = UserListMember::query()
            ->join('user_lists', 'user_list_members.list_id', '=', 'user_lists.id')
            ->where('user_list_members.user_id', $userId)
            ->where('user_lists.type', UserList::FOLLOWING_TYPE)
            ->count('user_list_members.id');

        return [
            'followers_count' => $followersCount,
            'following_count' => (int) ($followingList->members_count ?? 0),
            'following_list_id' => $followingList?->id,
        ];
    }

    public static function getUserFollowers($userID) {
        $followers = UserListMember::select('user_lists.user_id', 'users.email', 'users.settings', 'users.name')
            ->join('user_lists', 'user_list_members.list_id', '=', 'user_lists.id')
            ->join('users', 'users.id', '=', 'user_lists.user_id')
            ->where('user_list_members.user_id', $userID)
            ->where('user_lists.type', 'following')
            ->get()
            ->toArray();
        return $followers;
    }
}
