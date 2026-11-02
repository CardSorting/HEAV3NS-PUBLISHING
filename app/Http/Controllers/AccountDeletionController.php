<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Model\UserDeletionRequest;
use App\Services\UserDeletionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDeletionController extends Controller
{
    public function store(DeleteAccountRequest $request, UserDeletionRequestService $deletionRequestService): RedirectResponse
    {
        $result = $deletionRequestService->createRequest(
            $request->user(),
            $request->input('reason')
        );

        if (!$result['success']) {
            return back()
                ->withErrors(['delete_request' => $result['message']])
                ->withInput($request->except('delete_password'));
        }

        return back()->with('success', $result['message']);
    }

    public function cancel(Request $request, UserDeletionRequestService $deletionRequestService): RedirectResponse
    {
        if (!$deletionRequestService->usersMayCancel()) {
            return back()->withErrors(['delete_request' => __('Account deletion requests cannot be canceled by users.')]);
        }

        $deletionRequest = UserDeletionRequest::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', UserDeletionRequest::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        if (!$deletionRequest) {
            return back()->withErrors(['delete_request' => __('No active account deletion request was found.')]);
        }

        $result = $deletionRequestService->cancel($deletionRequest, $request->user());

        if (!$result['success']) {
            return back()->withErrors(['delete_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
