<?php

namespace App\Http\Middleware;

use App\Support\SessionLifetimes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the Security Settings' session timeout (minutes of inactivity
 * before sign-out). Must run before StartSession, which builds the session
 * handler and the cookie from this config.
 *
 * Only signed-in accounts get the timeout. A session with nobody signed in —
 * a room session tablet, claimed once for the whole day — keeps the long
 * device lifetime however short the timeout is (user-directed 2026-10-19).
 * session.lifetime (the cookie) is the longer of the two; the handler
 * (App\Session\DeviceAwareDatabaseSessionHandler) enforces the account
 * timeout server-side.
 */
class ApplySessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        // The .env value, for the settings page to show as the default.
        config(['session.env_lifetime' => config('session.env_lifetime', config('session.lifetime'))]);

        config([
            'session.account_lifetime' => SessionLifetimes::account(),
            'session.lifetime' => SessionLifetimes::device(),
        ]);

        return $next($request);
    }
}
