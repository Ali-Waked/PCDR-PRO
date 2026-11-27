<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuthenticationActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthenticationActivityService $activity) {}

    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(string $locale, LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->registerFailure($request);

            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::guard()->logout();
            $this->registerFailure($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        if ($this->verificationRequired() && ! $user->hasVerifiedEmail()) {
            Auth::guard()->logout();
            $request->session()->regenerateToken();

            return redirect()->route('verification.notice', ['locale' => $request->route('locale')]);
        }

        $request->session()->regenerate();
        $request->session()->put('account.session_reference', bin2hex(random_bytes(16)));
        $this->activity->recordSuccessfulLogin($user);
        RateLimiter::clear($this->throttleKey($request));

        return redirect()->intended(route('dashboard', ['locale' => $request->route('locale')], false));
    }

    public function destroy(string $locale, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $this->activity->recordLogout($user);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show', ['locale' => $request->route('locale') ?? 'ar']);
    }

    private function verificationRequired(): bool
    {
        return (bool) config('settings.definitions.security.require_email_verification.default', true);
    }

    private function registerFailure(LoginRequest $request): void
    {
        $key = $this->throttleKey($request);
        RateLimiter::hit($key);
        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user === null) {
            return;
        }

        $threshold = (int) config('settings.definitions.security.lockout_threshold.default', 5);
        $minutes = (int) config('settings.definitions.security.lockout_minutes.default', 15);
        $attempts = $user->failed_login_attempts + 1;
        $user->failed_login_attempts = $attempts;

        if ($attempts >= $threshold) {
            $user->locked_until = now()->addMinutes($minutes);
        }

        $user->save();
        $this->activity->recordFailedLogin($user, 'invalid_credentials');
    }

    private function throttleKey(LoginRequest $request): string
    {
        return 'login|'.mb_strtolower($request->string('email')->toString()).'|'.$request->ip();
    }
}
