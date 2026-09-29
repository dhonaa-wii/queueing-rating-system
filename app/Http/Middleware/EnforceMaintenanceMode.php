<?php

namespace App\Http\Middleware;

use App\Support\MaintenanceMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shuts the site for everyone but a signed-in Super Admin (or, during a
 * restore, for everyone but the restore's own step) while MaintenanceMode is on.
 * See MaintenanceMode for why the flag is a file.
 *
 * Runs after StartSession so the Super Admin can be recognised — but in RESTORE
 * mode it never looks at the user at all, because the users table is being
 * rewritten and a lookup there could fail or wrongly find nobody.
 */
class EnforceMaintenanceMode
{
    /** Reachable in every mode: the restore step authenticates itself with a token. */
    private const ALWAYS = ['super-admin.restores.step'];

    /** Reachable in maintenance mode so a Super Admin can sign in and switch it off. */
    private const SIGN_IN = ['login', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $state = MaintenanceMode::state();

        if ($state === null) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, self::ALWAYS, true)) {
            return $next($request);
        }

        if ($state['mode'] === MaintenanceMode::MAINTENANCE) {
            // The login POST has no route name, so the path is matched too.
            if (in_array($route, self::SIGN_IN, true) || $request->is('login') || $request->user()?->hasRole('SUPER_ADMIN')) {
                return $next($request);
            }
        }

        $message = $state['mode'] === MaintenanceMode::RESTORE
            ? 'The system is being restored from a backup and will be back shortly.'
            : ($state['message'] ?? 'The system is down for maintenance and will be back shortly.');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 503, ['Retry-After' => 60]);
        }

        return response()->view('maintenance', [
            'message' => $message,
            'restoring' => $state['mode'] === MaintenanceMode::RESTORE,
        ], 503, ['Retry-After' => 60]);
    }
}
