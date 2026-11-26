<?php

namespace App\Providers;

use App\Models\AuditArchive;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Policies\AuditArchivePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\UserPolicy;
use App\Services\EmailLogoService;
use App\Services\RbacService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        AuditArchive::class => AuditArchivePolicy::class,
        Setting::class => SettingPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            app(EmailLogoService::class)->attachTo($event->message);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->isPlatformOwner()) {
                return null;
            }

            $rbac = App::make(RbacService::class);

            if ($rbac->isAuthorityAbility($ability) || $rbac->isAuthorityPermission($ability)) {
                return false;
            }

            return true;
        });

        foreach (array_keys(config('permissions', [])) as $permission) {
            Gate::define($permission, fn (User $user) => App::make(RbacService::class)->has($user, (string) $permission));
        }

        // The unified Roles & Permissions page is reachable with either view
        // permission. Mutation endpoints retain their own stricter gates.
        Gate::define('access.view', function (User $user): bool {
            $rbac = App::make(RbacService::class);

            return $rbac->has($user, 'roles.view') || $rbac->has($user, 'permissions.view');
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $limit = (int) config('settings.definitions.security.login_rate_limit.default', 5);

            return Limit::perMinute($limit)->by(Str::transliterate(
                Str::lower((string) $request->input('email', '')).'|'.$request->ip(),
            ));
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            return Limit::perMinute(3)->by(Str::transliterate(
                Str::lower((string) $request->input('email', '')).'|'.$request->ip(),
            ));
        });

        RateLimiter::for('account-email', function (Request $request): Limit {
            return Limit::perMinute(3)->by(Str::transliterate(
                (string) Auth::id().'|'.$request->ip(),
            ));
        });

        RateLimiter::for('account-sensitive', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::transliterate(
                (string) Auth::id().'|'.$request->ip(),
            ));
        });

        // Laravel 13's Limit has no `and()` combinator, so the hourly avatar
        // cap is registered as a second named limiter and applied in sequence.
        RateLimiter::for('account-avatar', function (Request $request): Limit {
            return Limit::perMinute(10)->by(Str::transliterate(
                (string) Auth::id().'|'.$request->ip(),
            ));
        });

        RateLimiter::for('account-avatar-hour', function (Request $request): Limit {
            return Limit::perHour(30)->by(Str::transliterate(
                (string) Auth::id().'|'.$request->ip(),
            ));
        });
    }
}
