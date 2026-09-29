<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('username', $credentials['username'])->first();

        if (! $user || ! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->accountStatus->is_login_allowed) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'This account no longer has access. Contact an administrator.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->mustChangePassword() && ! $user->hasRole('PANELIST')) {
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
