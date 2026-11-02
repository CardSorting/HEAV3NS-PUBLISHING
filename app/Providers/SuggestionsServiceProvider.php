<?php

namespace App\Providers;

use App\Model\FeaturedUser;
use App\Model\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use View;

class SuggestionsServiceProvider extends ServiceProvider
{
    /**
     * Get suggested members.
     *
     * Behaviour is identical to the original:
     *  - Uses featured list OR most-subscribed with fallback to latest.
     *  - Respects settings for skipping empty / unverified profiles.
     *  - Optional filtering (free profiles), shuffling, and HTML rendering.
     *
     * @param  bool  $encodeToHtml
     * @param  array $filters
     * @return \Illuminate\Support\Collection|array
     */
    public static function getSuggestedMembers(bool $encodeToHtml = false, array $filters = [])
    {
        $settings = self::suggestionSettings();

        // Build base members query depending on mode
        if ($settings['use_featured_list']) {
            $membersQuery = self::queryFromFeaturedUsers($settings);
        } else {
            $membersQuery = self::queryFromSubscriptionsOrLatest($settings);
        }

        // Filtering free accounts
        if (isset($filters['free'])) {
            $membersQuery->where('paid_profile', 0);
        }

        // Execute query and shuffle
        $members = $membersQuery->get()->shuffle();

        // Return either raw data or encoded HTML
        if ($encodeToHtml) {
            return self::renderSuggestionHtml($members, $filters, $settings);
        }

        return $members;
    }

    /**
     * Centralize all settings used by suggestions.
     */
    private static function suggestionSettings(): array
    {
        return [
            'skip_empty_profiles'   => (bool) getSetting('feed.suggestions_skip_empty_profiles'),
            'skip_unverified'       => (bool) getSetting('feed.suggestions_skip_unverified_profiles'),
            'use_featured_list'     => (bool) getSetting('feed.suggestions_use_featured_users_list'),
            'total_cards'           => (int) getSetting('feed.feed_suggestions_total_cards'),
            'cards_per_page'        => (int) getSetting('feed.feed_suggestions_card_per_page'),
        ];
    }

    /**
     * Build query when using the featured users list.
     *
     * Behaviour identical: same limit formula and filters.
     */
    private static function queryFromFeaturedUsers(array $settings)
    {
        $userIds = FeaturedUser::pluck('user_id')->toArray();

        return User::query()
            ->where('public_profile', 1)
            ->whereIn('id', $userIds)
            ->limit($settings['total_cards'] * $settings['cards_per_page']);
    }

    /**
     * Build query based on most-subscribed users with fallback to latest.
     *
     * Behaviour identical: still uses the same raw SQL logic for top subs,
     * same thresholds, same filters.
     */
    private static function queryFromSubscriptionsOrLatest(array $settings)
    {
        $topSubbedUserIds = self::topSubbedUserIds($settings);

        $members = User::query()
            ->where('public_profile', 1)
            ->limit($settings['total_cards']);

        // If there are at least 6 “top subbed” users, use them
        if (count($topSubbedUserIds) >= 6) {
            return $members->whereIn('id', $topSubbedUserIds);
        }

        // Otherwise fallback to latest creators
        $members->where('role_id', 2)
            ->orderByDesc('users.created_at');

        if (Auth::check()) {
            $members->where('users.id', '<>', Auth::id());
        }

        if ($settings['skip_empty_profiles']) {
            $members->whereNotNull('avatar')
                ->whereNotNull('cover');
        }

        if ($settings['skip_unverified']) {
            $members->join('user_verifies', function ($join) {
                $join->on('users.id', '=', 'user_verifies.user_id');
                $join->on('user_verifies.status', '=', DB::raw("'verified'"));
            });
        }

        return $members;
    }

    /**
     * Fetch IDs of most-subscribed users using the same raw SQL as before.
     *
     * Behaviour identical.
     */
    private static function topSubbedUserIds(array $settings): array
    {
        $skipEmpty = $settings['skip_empty_profiles'];
        $skipUnverified = $settings['skip_unverified'];
        $mostSubbedMax = $settings['total_cards'];

        $query = "
            SELECT usersTable.id, COUNT(subsTable.id) AS subs_count
            FROM users usersTable
            INNER JOIN subscriptions subsTable
                ON usersTable.id = subsTable.recipient_user_id
            ".($skipUnverified
                ? "INNER JOIN user_verifies verifications
                        ON usersTable.id = verifications.user_id
                       AND verifications.status = 'verified'"
                : "")."
            WHERE usersTable.role_id = 2
            ".($skipEmpty
                ? "AND (usersTable.avatar IS NOT NULL
                       AND usersTable.cover IS NOT NULL)"
                : "")."
            GROUP BY usersTable.id
            ORDER BY subs_count DESC
            LIMIT 0, {$mostSubbedMax}
        ";

        $topSubbedUsers = DB::select($query);

        return array_map(function ($v) {
            return $v->id;
        }, $topSubbedUsers);
    }

    /**
     * Render the HTML view for suggestions.
     *
     * Behaviour identical: same view, same variables, same return structure.
     */
    private static function renderSuggestionHtml($members, array $filters, array $settings): array
    {
        $view = View::make('elements.feed.suggestions-wrapper')
            ->with('profiles', $members)
            ->with('perPage', $settings['cards_per_page']);

        if (isset($filters['isMobile'])) {
            $view->with('isMobile', true);
        }

        return [
            'html' => $view->render(),
        ];
    }
}
