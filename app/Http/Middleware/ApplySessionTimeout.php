<?php

namespace App\Http\Middleware;

use App\Support\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies the Security Settings' session timeout (minutes of inactivity
 * before sign-out) by overriding the session lifetime for this request.
 * Must run before StartSession, which reads the lifetime when it opens the
 * session. Unset = the SESSION_LIFETIME from .env.
 */
class ApplySessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $minutes = SecurityPolicy::sessionTimeout();
        } catch (Throwable) {
            // e.g. mid-restore, when system_settings is being rewritten.
            $minutes = null;
        }

        // The .env value, for the settings page to show as the default.
        config(['session.env_lifetime' => config('session.env_lifetime', config('session.lifetime'))]);

        if ($minutes !== null) {
            config(['session.lifetime' => $minutes]);
        }

        return $next($request);
    }
}
