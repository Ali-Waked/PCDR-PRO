<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RevokeAllSessionsRequest;
use App\Http\Requests\Account\RevokeSessionRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\AccountSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PasswordController extends Controller
{
    public function __construct(
        private readonly AccountSecurityService $security,
        private readonly AccountSessionService $sessions,
    ) {}

    public function update(string $locale, UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $this->security->changePassword(
            $user,
            (string) $request->validated('password'),
            $this->sessions->currentReference($request),
        );

        return back()->with('success', __('ui.account_profile.password_updated'));
    }

    public function revokeSession(string $locale, string $reference, RevokeSessionRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        /** @var array{reference: string, current_password: string} $data */
        $data = $request->validated();
        $this->security->revokeSession($user, $data['reference']);

        return back()->with('success', __('ui.account_profile.session_revoked'));
    }

    public function revokeOtherSessions(string $locale, RevokeAllSessionsRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $currentReference = $this->sessions->currentReference($request);
        if ($currentReference === null) {
            return back()->with('success', __('ui.account_profile.sessions_revoked'));
        }

        $this->security->revokeOtherSessions($user, $currentReference);

        return back()->with('success', __('ui.account_profile.sessions_revoked'));
    }

    public function sessionsJson(string $locale, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        return response()->json($this->security->sessionsJson(
            $user,
            $locale,
            $this->sessions->currentReference($request),
        ))->header('Cache-Control', 'no-store');
    }
}
