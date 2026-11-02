<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountSuspensionController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (!$user->isBanned()) {
            return redirect()->route('feed');
        }

        return response()->view('pages.account-suspension', ['ban' => $user->activeBan]);
    }
}
