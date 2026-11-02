<?php

namespace App\Providers;

use App\Model\FeaturedUser;
use App\Model\UserGender;
use App\Model\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use View;

class MembersHelperServiceProvider extends ServiceProvider
{
    /**
     * Returns list of filtered users.
     * @param $options
     * @return array|LengthAwarePaginator
     * @phpstan-return ($options is array{encodePostsToHtml: true} ? array<string, mixed> : LengthAwarePaginator)
     */
    public static function getSearchUsers($options) {

        $users = User::where('users.public_profile', 1);
        $users->where('users.role_id', 2);

        if(Auth::check()){
            $users->where('users.id', '<>', Auth::user()->id);
        }

        if(isset($options['gender']) && $options['gender'] !== 'all'){
            $genderID = UserGender::where('gender_name', strtolower($options['gender']))->select('id')->first();
            if(isset($genderID->id)){
                $users->where('gender_id', $genderID->id);
            }
        }

        if(isset($options['min_age'])){
            $minDate = Carbon::now()->subYears($options['min_age']);
            $users->where('birthdate', '<', $minDate->format('Y-m-d'));
        }

        if(isset($options['max_age'])){
            $maxDate = Carbon::now()->subYears($options['max_age']);
            $users->where('birthdate', '>', $maxDate->format('Y-m-d'));
        }

        if(isset($options['location'])){
            $users->where('location', 'like', '%'.$options['location'].'%');
        }

        if(isset($options['searchTerm'])){
            // Might take a small hit on performance
            $users->where(function ($query) use ($options) {
                $query->where('username', 'like', '%'.$options['searchTerm'].'%');
                $query->orWhere('bio', 'like', '%'.$options['searchTerm'].'%');
                $query->orWhere('name', 'like', '%'.$options['searchTerm'].'%');
            });
        }

        $users->orderBy('users.id', 'desc');

        if(getSetting('feed.hide_non_verified_users_from_search')){
            $users->join('user_verifies', function ($join) {
                $join->on('users.id', '=', 'user_verifies.user_id');
            });
//            $users->where('user_verifies.user_id','NOT', null);
        }

        if (isset($options['pageNumber'])) {
            $users = $users->paginate(9, ['*'], 'page', $options['pageNumber'])->appends(request()->query());
        } else {
            $users = $users->paginate(9)->appends(request()->query());
        }

        app(\App\Services\UserStaffBadge::class)->preload($users->getCollection());

        if(!isset($options['encodePostsToHtml'])){
            $options['encodePostsToHtml'] = false;
        }

        if ($options['encodePostsToHtml']) {
            // Posts encoded as JSON
            $data = [
                'total' => $users->total(),
                'currentPage' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'prev_page_url' => $users->previousPageUrl(),
                'next_page_url' => $users->nextPageUrl(),
                'first_page_url' => $users->nextPageUrl(),
                'hasMore' => $users->hasMorePages(),
            ];
            $postsData = $users->map(function ($user) use ($data) {
                $user->setAttribute('postPage', $data['currentPage']);
                $user = ['id' => $user->id, 'html' => View::make('elements.search.users-list-element')->with('user', $user)->render()];
                return $user;
            });
            $data['users'] = $postsData;
        } else {
            // Collection data posts | To be rendered on the server side
            $postsCurrentPage = $users->currentPage();
            $users->map(function ($user) use ($postsCurrentPage) {
                $user->setAttribute('postPage', $postsCurrentPage);
                return $user;
            });
            $data = $users;
        }

        return $data;
    }

    /**
     * Get widget users for expired subscriptions of the current user.
     *
     * Behaviour identical to original.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getExpiredSubscriptions()
    {
        $expiredSubUsers = Auth::user()
            ->expiredSubscriptions
            ->map(function ($subscription) {
                return $subscription->creator;
            });

        $limit = (int) getSetting('feed.expired_subs_widget_total_cards');

        if ($limit) {
            $expiredSubUsers = $expiredSubUsers->take($limit);
        }

        return $expiredSubUsers;
    }

    /**
     * Returns a list of latest profiles.
     * @param $limit
     * @return mixed
     */
    public static function getFeaturedMembers($limit)
    {
        $members = FeaturedUser::with(['user'])->orderByDesc('featured_users.created_at')->limit($limit);
        $members->join('users', function ($join) {
            $join->on('users.id', '=', 'featured_users.user_id');
        });
        $members = $members->get()->map(function ($v) {
            return $v->user;
        });
        if(count($members)){
            return $members;
        }
        else{
            $members = User::limit($limit)->where('public_profile', 1)->whereIn('role_id', [2])->orderByDesc('created_at')->get();
            return $members;
        }
    }
}
