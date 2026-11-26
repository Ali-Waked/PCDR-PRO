<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\User;
use App\Services\AccountProfileService;
use App\Services\AccountSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountProfileService $profiles,
        private readonly AccountSessionService $sessions,
    ) {}

    public function show(string $locale, Request $request): Response
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        return Inertia::render('Account/Profile/Show', $this->profiles->show(
            $user,
            $locale,
            $this->sessions->currentReference($request),
            (int) $request->query('page', 1),
        ));
    }

    public function update(string $locale, UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        /** @var array{name?: string, locale?: string, theme?: string|null} $data */
        $data = $request->validated();
        $this->profiles->update($user, $data);

        return back()->with('success', __('ui.account_profile.profile_updated'));
    }
}
