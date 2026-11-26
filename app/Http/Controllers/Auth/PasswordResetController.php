<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function showRequest(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(string $locale, ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker()->sendResetLink($request->only('email'));

        $this->audit->record('auth.password_reset_requested', 'users', null, null, [
            'status' => match ($status) {
                Password::RESET_LINK_SENT => 'sent',
                Password::RESET_THROTTLED => 'throttled',
                default => 'suppressed',
            },
        ]);

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => __($status)]);
        }

        // Always use the same response so the endpoint cannot be used to
        // enumerate registered email addresses.
        return back()->with('success', __(Password::RESET_LINK_SENT));
    }

    public function showReset(string $locale, Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(string $locale, ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                event(new PasswordReset($user));
                $this->audit->record('auth.password_reset_completed', 'users', (string) $user->id);
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login.show', ['locale' => $request->route('locale')])
                ->with('success', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
