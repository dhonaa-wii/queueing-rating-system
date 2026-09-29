<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->accountStatus->is_login_allowed) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => 'This account no longer has access. Contact an administrator.']);
        }

        if ($user->mustChangePassword()) {
            $exemptRoutes = ['password.change', 'password.update', 'logout'];

            // Panelists see their (locked) dashboard immediately after login instead
            // of being forced onto the dedicated password-change page — they're
            // guided to Settings from there instead. Admin/Super Admin keep the
            // original hard-redirect behavior.
            if ($user->hasRole('PANELIST')) {
                $exemptRoutes[] = 'panelist.dashboard';
                $exemptRoutes[] = 'profile.update';
            }

            if (! $request->routeIs(...$exemptRoutes)) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
