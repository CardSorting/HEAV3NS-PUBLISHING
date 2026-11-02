<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClearListRequest;
use App\Http\Requests\ManageUserFollowsRequest;
use App\Http\Requests\SaveListRequest;
use App\Http\Requests\UpdateUserListMemberRequest;
use App\Model\Reel;
use App\Model\ReelComment;
use App\Model\User;
use App\Model\UserList;
use App\Model\UserListMember;
use App\Model\UserReport;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\ReelsServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use JavaScript;
use View;

class ListsController extends Controller
{
    private const LISTS_PER_PAGE = 20;
    private const LIST_MEMBERS_PER_PAGE = 24;

    /**
     * Renders main lists page.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $lists = ListsHelperServiceProvider::getUserLists();
        $followersList = ListsHelperServiceProvider::getUserFollowersList();
        $lists->splice(1, 0, [$followersList]);
        $lists = $this->paginateCollection($lists, $request, self::LISTS_PER_PAGE);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => array_merge($this->getPaginatorConfig($lists), [
                    'lists' => $this->renderListRows($lists->getCollection()),
                ]),
            ]);
        }

        JavaScript::put([
            'listsPaginatorConfig' => array_merge($this->getPaginatorConfig($lists), [
                'type' => 'lists',
            ]),
        ]);

        return view('pages.lists', [
            'lists' => $lists,
        ]);
    }

    /**
     * Renders individual lists page.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function showList(Request $request)
    {
        $listID = $request->route('list_id');
        $searchQuery = substr(trim((string) $request->get('query', '')), 0, 100);
        if($listID !== UserList::FOLLOWERS_TYPE){
            $list = UserList::where('id', $listID)->where('user_id', Auth::user()->id)->first();
            if(!$list){
                abort(404);
            }
            $members = $this->getListMembersPaginator($list->id, $request, $searchQuery);
        }
        else{
            $list = new UserList();
            $list->name = __("Followers");
            $list->type = UserList::FOLLOWERS_TYPE;
            $list->setAttribute('id', UserList::FOLLOWERS_TYPE);
            $list->user_id = Auth::user()->id;
            $members = $this->getFollowersPaginator(Auth::user()->id, $request, $searchQuery);
        }

        $list->setAttribute('isManageable', true);
        if ($list->type == UserList::FOLLOWING_TYPE || $list->type == UserList::BLOCKED_TYPE || $list->type == UserList::FOLLOWERS_TYPE) {
            $list->setAttribute('isManageable', false);
        }
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => array_merge($this->getPaginatorConfig($members), [
                    'users' => $this->renderListMemberRows($members->getCollection(), $list),
                ]),
            ]);
        }

        JavaScript::put([
            'listVars' => ['name'=>$list->name, 'list_id'=>$list->id],
            'listsPaginatorConfig' => array_merge($this->getPaginatorConfig($members), [
                'type' => 'members',
                'search_query' => $searchQuery,
            ]),
        ]);

        return view('pages.list', [
            'list' => $list,
            'members' => $members,
            'listEmptyState' => $this->listEmptyState($list),
            'listSearchEmptyState' => [
                'title' => __('No matching profiles'),
                'copy' => __('Try a different name or username.'),
                'actionUrl' => route('my.lists.show', ['list_id' => $list->id]),
                'actionLabel' => __('Clear search'),
            ],
        ]);
    }

    private function listEmptyState(UserList $list): array
    {
        return match ($list->type) {
            UserList::FOLLOWING_TYPE => [
                'title' => __('No followed creators yet'),
                'copy' => __('Creators you follow will appear here.'),
                'actionUrl' => route('search.get', ['filter' => 'people']),
                'actionLabel' => __('Explore creators'),
            ],
            UserList::FOLLOWERS_TYPE => [
                'title' => __('No followers yet'),
                'copy' => __('People who follow you will appear here.'),
                'actionUrl' => null,
                'actionLabel' => null,
            ],
            UserList::BLOCKED_TYPE => [
                'title' => __('No blocked profiles'),
                'copy' => __('Profiles you block will appear here.'),
                'actionUrl' => null,
                'actionLabel' => null,
            ],
            default => [
                'title' => __('This list is empty'),
                'copy' => __('Profiles you add to this list will appear here.'),
                'actionUrl' => route('search.get', ['filter' => 'people']),
                'actionLabel' => __('Explore creators'),
            ],
        };
    }

    /**
     * Method used for creating/updating a lists.
     *
     * @param SaveListRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveList(SaveListRequest $request)
    {
        $type = $request->get('type');
        $name = $request->get('name');
        if ($type == 'create') {
            $listID = UserList::create([
                'user_id' => Auth::user()->id,
                'name' => $name,
                'type' => 'custom',
            ])->id;
            $list = UserList::with(['members', 'members.user'])->where('id', $listID)->where('user_id', Auth::user()->id)->first();

            return response()->json([
                'success'=>true,
                'data'=> View::make('elements.lists.list-box')->with(['list' => $list, 'isLastItem' => true])->render(),
            ]);
        } elseif ($type == 'edit') {
            $listID = $request->get('list_id');
            $list = UserList::where('id', $listID)->where('user_id', Auth::user()->id)->where('type', 'custom');
            $list->update([
                'name' => $name,
            ]);

            return response()->json([
                'success'=> true,
            ]);
        }

        return response()->json(['success' => false, 'message' => __('Invalid list action.')], 400);
    }

    /**
     * Method used for deleting a list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteList(Request $request)
    {
        $listID = $request->get('id');
        $list = UserList::where('id', $listID)->where('user_id', Auth::user()->id)->where('type', 'custom')->first();
        if ($list) {
            $list->delete();

            return response()->json(['success' => true, 'message' => __('List deleted successfully.')]);
        } else {
            return response()->json(['success' => false, 'error' => __('List deleted successfully.')]);
        }
    }

    /**
     * Method used for adding an user to a list.
     *
     * @param UpdateUserListMemberRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addListMember(UpdateUserListMemberRequest $request)
    {
        $listID = $request->get('list_id');
        $userID = $request->get('user_id');
        $returnData = $request->get('returnData') == 'false' ? false : true;
        if (!$this->isAuthorized($listID)) {
            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message'=> __('Not authorized')], 403);
        }

        return $this->withFollowStats(
            ListsHelperServiceProvider::addListMember($listID, $userID, $returnData)
        );
    }

    /**
     * Paginates an in-memory collection while keeping the current route/query.
     *
     * @param Collection $items
     * @param Request $request
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    private function paginateCollection(Collection $items, Request $request, int $perPage)
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $this->getPaginationQuery($request),
            ]
        );
    }

    /**
     * Returns paginated users for a concrete list.
     *
     * @param int $listID
     * @param Request $request
     * @param string $searchQuery
     * @return LengthAwarePaginator
     */
    private function getListMembersPaginator($listID, Request $request, $searchQuery = '')
    {
        $users = User::query()
            ->select('users.*')
            ->join('user_list_members', 'users.id', '=', 'user_list_members.user_id')
            ->where('user_list_members.list_id', $listID);

        $this->applyListMembersSearch($users, $searchQuery);

        return $users
            ->orderByDesc('user_list_members.id')
            ->paginate(self::LIST_MEMBERS_PER_PAGE)
            ->appends($this->getPaginationQuery($request));
    }

    /**
     * Returns paginated users following the given user.
     *
     * @param int $userID
     * @param Request $request
     * @param string $searchQuery
     * @return LengthAwarePaginator
     */
    private function getFollowersPaginator($userID, Request $request, $searchQuery = '')
    {
        $users = User::query()
            ->select('users.*')
            ->join('user_lists', 'users.id', '=', 'user_lists.user_id')
            ->join('user_list_members', 'user_list_members.list_id', '=', 'user_lists.id')
            ->where('user_list_members.user_id', $userID)
            ->where('user_lists.type', UserList::FOLLOWING_TYPE);

        $this->applyListMembersSearch($users, $searchQuery);

        return $users
            ->orderByDesc('user_list_members.id')
            ->paginate(self::LIST_MEMBERS_PER_PAGE)
            ->appends($this->getPaginationQuery($request));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $users
     * @param string $searchQuery
     * @return void
     */
    private function applyListMembersSearch($users, $searchQuery)
    {
        if ($searchQuery === '') {
            return;
        }

        $users->where(function ($query) use ($searchQuery) {
            $query->where('users.name', 'like', '%'.$searchQuery.'%')
                ->orWhere('users.username', 'like', '%'.$searchQuery.'%');
        });
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getPaginationQuery(Request $request)
    {
        $query = $request->query();
        unset($query['page']);

        return $query;
    }

    /**
     * @param LengthAwarePaginator $paginator
     * @return array
     */
    private function getPaginatorConfig(LengthAwarePaginator $paginator)
    {
        return [
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'current_page' => $paginator->currentPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'hasMore' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param Collection $lists
     * @return Collection
     */
    private function renderListRows(Collection $lists)
    {
        return $lists->map(function ($list) {
            return [
                'id' => $list->id,
                'html' => View::make('elements.lists.list-box')->with([
                    'list' => $list,
                    'isLastItem' => false,
                ])->render(),
            ];
        });
    }

    /**
     * @param Collection $members
     * @param UserList $list
     * @return Collection
     */
    private function renderListMemberRows(Collection $members, UserList $list)
    {
        app(\App\Services\UserStaffBadge::class)->preload($members);

        return $members->map(function ($member) use ($list) {
            return [
                'id' => $member->id,
                'html' => View::make('elements.lists.list-member-card')->with([
                    'member' => $member,
                    'list' => $list,
                ])->render(),
            ];
        });
    }

    /**
     * Method used for deleting an user from a list.
     *
     * @param UpdateUserListMemberRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteListMember(UpdateUserListMemberRequest $request)
    {
        $listID = $request->get('list_id');
        $userID = $request->get('user_id');
        $returnData = $request->get('returnData') == 'false' ? false : true;
        if (!$this->isAuthorized($listID)) {
            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message'=> __('Not authorized')], 403);
        }

        return $this->withFollowStats(
            ListsHelperServiceProvider::deleteListMember($listID, $userID, $returnData)
        );
    }

    /**
     * Method used for deleting all members withing a list.
     *
     * @param ClearListRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearList(ClearListRequest $request)
    {
        try {
            $listID = $request->get('list_id');
            if (!$this->isAuthorized($listID)) {
                return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message'=> __('Not authorized')], 403);
            }
            if (!UserList::where('id', $listID)->where('user_id', Auth::user()->id)->count()) {
                return response()->json(['success' => false, 'errors' => [__('List not found.')]]);
            }
            UserListMember::where('list_id', $listID)->delete();

            return response()->json(['success' => true, 'message' => __('List cleared.')]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.')]]);
        }
    }

    /**
     * Method used for saving user reports.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postReport(Request $request)
    {
        $fromUserID = Auth::user()->id;
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'post_id' => ['nullable', 'integer'],
            'message_id' => ['nullable', 'integer'],
            'stream_id' => ['nullable', 'integer'],
            'story_id' => ['nullable', 'integer'],
            'reel_id' => ['nullable', 'integer'],
            'reel_comment_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', Rule::in(UserReport::$typesMap)],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $reportedUserID = $data['user_id'] ?? null;
        $reportedPostID = $data['post_id'] ?? null;
        $reportedMessageID = $data['message_id'] ?? null;
        $reportedStreamID = $data['stream_id'] ?? null;
        $reportedStoryID = $data['story_id'] ?? null;
        $reportedReelID = $data['reel_id'] ?? null;
        $reportedReelCommentID = $data['reel_comment_id'] ?? null;
        $reportType = $data['type'];
        $details = isset($data['details']) && trim($data['details']) !== '' ? trim($data['details']) : null;
        try {
            $duplicateReportQuery = null;

            if ($reportedReelCommentID) {
                $comment = ReelComment::with('reel')->find((int) $reportedReelCommentID);

                if (!$comment || !$comment->reel) {
                    return response()->json(['success' => false, 'errors' => [__('Comment not found.')], 'message' => __('Comment not found.')], 404);
                }

                if (!ReelsServiceProvider::canViewReel($request->user(), $comment->reel)) {
                    return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
                }

                $reportedUserID = (int) $comment->user_id;
                $reportedReelID = (int) $comment->reel_id;
                $reportedReelCommentID = (int) $comment->id;
                $duplicateReportQuery = UserReport::where('from_user_id', $fromUserID)
                    ->where('reel_comment_id', $reportedReelCommentID);
            } elseif ($reportedReelID) {
                $reel = Reel::find((int) $reportedReelID);

                if (!$reel) {
                    return response()->json(['success' => false, 'errors' => [__('Reel not found.')], 'message' => __('Reel not found.')], 404);
                }

                if (!ReelsServiceProvider::canViewReel($request->user(), $reel)) {
                    return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
                }

                $reportedUserID = (int) $reel->user_id;
                $reportedReelID = (int) $reel->id;
                $duplicateReportQuery = UserReport::where('from_user_id', $fromUserID)
                    ->where('reel_id', $reportedReelID)
                    ->whereNull('reel_comment_id');
            }

            if ($duplicateReportQuery) {
                if ((int) $reportedUserID === (int) $fromUserID) {
                    return response()->json(['success' => false, 'errors' => [__('You cannot report your own content.')], 'message' => __('You cannot report your own content.')], 422);
                }

                if ($duplicateReportQuery->exists()) {
                    return response()->json(['success' => true, 'message' => __('Report already submitted.')]);
                }
            }

            $data = [
                'from_user_id' => $fromUserID,
                'user_id' => $reportedUserID,
                'post_id' => $reportedPostID,
                'message_id' => $reportedMessageID,
                'stream_id' => $reportedStreamID,
                'story_id' => $reportedStoryID,
                'reel_id' => $reportedReelID,
                'reel_comment_id' => $reportedReelCommentID,
                'type' => $reportType,
                'status' => UserReport::$statusMap[0],
                'details' => $details,
            ];
            UserReport::create($data);

            return response()->json(['success' => true, 'message' => __('Report sent.')]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.')], 'message'=>$exception->getMessage()]);
        }
    }

    /**
     * Method used for checking if user is authorized to manage a certain list.
     *
     * @param $listID
     * @return bool
     */
    public function isAuthorized($listID)
    {
        // Checking if is authorized
        $userLists = UserList::where('user_id', Auth::user()->id)->pluck('id')->toArray();
        $isOwnedList = in_array($listID, $userLists);
        if (!$isOwnedList) {
            return false;
        }

        return true;
    }

    /**
     * Method used for adding/removing an user from followers list.
     * @param ManageUserFollowsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function manageUserFollows(ManageUserFollowsRequest $request) {
        $userId = $request->get('user_id');
        try {
            ListsHelperServiceProvider::managePredefinedUserMemberList(Auth::user()->id, $userId, ListsHelperServiceProvider::getUserFollowingType($userId));
        } catch (\Exception $exception){
            return response()->json(['success' => false, 'text' => ListsHelperServiceProvider::getUserFollowingType($userId)]);
        }

        return response()->json([
            'success' => true,
            'text' => ListsHelperServiceProvider::getUserFollowingType($userId, true),
            'follow_stats' => ListsHelperServiceProvider::getUserFollowStats((int) Auth::id()),
        ]);
    }

    /** Add authoritative sidebar counts to successful list-member responses. */
    private function withFollowStats(\Illuminate\Http\JsonResponse $response): \Illuminate\Http\JsonResponse
    {
        $data = $response->getData(true);
        if (($data['success'] ?? false) === true) {
            $data['follow_stats'] = ListsHelperServiceProvider::getUserFollowStats((int) Auth::id());
            $response->setData($data);
        }

        return $response;
    }
}
