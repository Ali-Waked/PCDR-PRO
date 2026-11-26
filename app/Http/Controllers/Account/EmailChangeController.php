<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelEmailChangeRequest;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use App\Models\User;
use App\Services\AccountEmailChangeService;
use App\Services\AccountSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailChangeController extends Controller
{
    public function __construct(
        private readonly AccountEmailChangeService $emailChanges,
        private readonly AccountSessionService $sessions,
    ) {}

    public function store(string $locale, RequestEmailChangeRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $this->emailChanges->request($user, (string) $request->validated('email'));

        return back()->with('success', __('ui.account_profile.email_change_requested'));
    }

    public function confirm(string $locale, string $token, Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $confirmed = $this->emailChanges->confirm(
            $user,
            $token,
            $this->sessions->currentReference($request),
        );

        if (! $confirmed) {
            return back()->with('flash', [
                'error' => __('ui.account_profile.email_change_invalid'),
            ]);
        }

        return redirect()->route('account.profile.show', ['locale' => $locale])
            ->with('success', __('ui.account_profile.email_change_confirmed'));
    }

    public function destroy(string $locale, CancelEmailChangeRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $this->emailChanges->cancel($user);

        return back()->with('success', __('ui.account_profile.email_change_cancelled'));
    }
}
