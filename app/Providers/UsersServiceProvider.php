<?php

namespace App\Providers;

use App\Model\Reward;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
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

    public static function getTotalAmountEarnedFromRewardsByUsers($referred, $referral, $rewardType = Reward::FEE_PERCENTAGE_REWARD_TYPE)
    {
        return Reward::where(['from_user_id' => $referral, 'to_user_id' => $referred, 'reward_type' => $rewardType])->sum('amount');
    }

    public static function loggedAsAdmin(): bool {
        $currentUser = Auth::user();
        return $currentUser && (int) $currentUser->role_id === 1;
    }
}
