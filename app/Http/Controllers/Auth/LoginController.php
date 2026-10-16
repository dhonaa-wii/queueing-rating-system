<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginLockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, LoginLockout $lockout)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('username', $credentials['username'])->first();

        // A locked account is refused even with the right password.
        if ($user && ($locked = $lockout->lockedMessage($user))) {
            throw ValidationException::withMessages(['username' => $locked]);
        }

        if (! $user || ! Auth::attempt($credentials)) {
            $locked = $user ? $lockout->recordFailure($user) : null;

            throw ValidationException::withMessages([
                'username' => $locked ?? 'These credentials do not match our records.',
            ]);
        }

        $lockout->clear($user);

        if (! $user->accountStatus->is_login_allowed) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'This account no longer has access. Contact an administrator.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->mustChangePassword()) {
            return redirect()->route('password.change');
        }

        // Lets a page like the QR terminal-scan confirm screen (which requires
        // login first) send the panelist back to exactly where they were headed,
        // instead of always landing on their dashboard.
        return redirect()->intended(route($user->dashboardRouteName()));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
