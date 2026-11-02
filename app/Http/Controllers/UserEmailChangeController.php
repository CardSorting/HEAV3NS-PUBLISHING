<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserEmailRequest;
use App\Model\PendingUserEmailChange;
use App\Services\UserEmailChangeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserEmailChangeController extends Controller
{
    public function store(UpdateUserEmailRequest $request, UserEmailChangeService $emailChangeService)
    {
        $pendingChange = $emailChangeService->requestChange($request->user(), $request->input('new_email'));

        return back()->with('success', __('Verification email sent to :email.', [
            'email' => $pendingChange->new_email,
        ]));
    }

    public function resend(Request $request, UserEmailChangeService $emailChangeService)
    {
        $pendingChange = $emailChangeService->resend($request->user());

        return back()->with('success', __('Verification email sent to :email.', [
            'email' => $pendingChange->new_email,
        ]));
    }

    public function cancel(Request $request, UserEmailChangeService $emailChangeService)
    {
        $emailChangeService->cancel($request->user());

        return back()->with('success', __('Email change canceled.'));
    }

    public function verify(
        Request $request,
        PendingUserEmailChange $emailChange,
        string $token,
        UserEmailChangeService $emailChangeService
    ) {
        try {
            $emailChangeService->verify($request->user(), $emailChange, $token);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('my.settings', ['type' => 'account'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('my.settings', ['type' => 'account'])
            ->with('success', __('Your email address has been updated.'));
    }
}
