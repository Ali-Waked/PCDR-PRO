<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccountSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TrackAuthenticatedActivity
{
    /**
     * Keep a public session reference and a throttled user activity timestamp.
     * The raw session ID is only used for the database metadata backfill and is
     * never exposed to the client, logs, or session listing.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || $request->routeIs('logout', 'account.avatar.download', 'verification.verify')
            || in_array($request->method(), ['HEAD', 'OPTIONS'], true)
        ) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $reference = $this->ensureReference($request);

        $this->touch($user);

        /** @var Response $response */
        $response = $next($request);

        $this->backfillSessionMetadata($request, $reference);

        return $response;
    }

    private function ensureReference(Request $request): string
    {
        $reference = $request->session()->get('account.session_reference');

        if (is_string($reference) && preg_match('/^[a-f0-9]{32}$/', $reference) === 1) {
            return $reference;
        }

        $reference = bin2hex(random_bytes(16));
        $request->session()->put('account.session_reference', $reference);

        return $reference;
    }

    private function touch(User $user): void
    {
        try {
            if (! Cache::add('account:activity:'.$user->id, true, 60)) {
                return;
            }

            app(AccountSessionService::class)->touch($user);
        } catch (Throwable) {
            // Activity tracking is best-effort and must not break the request.
        }
    }

    private function backfillSessionMetadata(Request $request, string $reference): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        try {
            $query = DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('id', $request->session()->getId());

            (clone $query)
                ->whereNull('public_reference')
                ->update(['public_reference' => $reference]);

            (clone $query)
                ->whereNull('created_at')
                ->update(['created_at' => now()]);
        } catch (Throwable) {
            // Session schemas may be older than the security migration;
            // backfill silently catches up on the next request.
        }
    }
}
