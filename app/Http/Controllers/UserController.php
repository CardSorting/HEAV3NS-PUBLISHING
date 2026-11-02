<?php

namespace App\Http\Controllers;

use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class UserController extends Controller
{
    /**
     * Impersonate admin as user and act like him in the website.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function impersonate(Request $request) {
        $userId = $request->route('id');
        try{
            $currentUserId = Auth::user()->id;
            Auth::loginUsingId($userId);

            Session::put('previousUserId', $currentUserId);
            if(!Session::get('impersonated')) {
                Session::put('impersonated', true);
            }
        } catch (\Exception $exception){
            return Redirect::route('filament.admin.resources.users.index');
        }
        return Redirect::route(Auth::user()->isBanned() ? 'account.suspension' : 'feed');
    }

    /**
     * Leave impersonation and return to admin user.
     * @param Request $request
     * @return string
     */
    public function leaveImpersonation(Request $request) {
        abort_unless($request->user() instanceof User, 403);
        $previousUserId = Session::get('previousUserId');
        // Existing sessions stored the originating administrator in an array.
        if (is_array($previousUserId)) {
            $previousUserId = end($previousUserId);
        }

        abort_unless(Session::get('impersonated') && is_numeric($previousUserId), 403);
        $previousUser = User::query()->find((int) $previousUserId);
        abort_unless($previousUser && (int) $previousUser->role_id === 1 && !$previousUser->isBanned(), 403);

        try{
            Auth::login($previousUser);
            Session::remove('previousUserId');
            Session::remove('impersonated');
        } catch (\Exception $exception) {
            return Redirect::route('feed');
        }

        return Redirect::route('filament.admin.resources.users.index');

    }
}
