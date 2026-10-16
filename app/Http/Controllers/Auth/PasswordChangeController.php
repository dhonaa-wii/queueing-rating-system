<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountStatus;
use Illuminate\Http\Request;
use App\Support\SecurityPolicy;

class PasswordChangeController extends Controller
{
    public function edit(Request $request)
    {
        return view('auth.password-change', [
            'forced' => $request->user()->mustChangePassword(),
            'expired' => $request->user()->passwordExpired(),
            'rules' => SecurityPolicy::passwordRules(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            // The Security Settings' password rules (default: 8+ characters with
            // an uppercase letter, a lowercase letter and a number). Must differ
            // from the current (often temporary) password, or a first-login
            // change would accomplish nothing.
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                ...SecurityPolicy::passwordValidationRules(),
            ],
        ], [
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $activeStatus = AccountStatus::where('code', 'ACTIVE')->firstOrFail();

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'account_status_id' => $activeStatus->id,
            'password_changed_at' => now(),
        ])->save();

        // Once the panelist sets their own password, the temp password Admin could
        // see in the Panelist Registry is no longer valid — clear it so the View
        // page stops showing it (no-op for roles without a panelist profile).
        $user->panelistProfile()->update(['temporary_password' => null]);

        // A first login comes straight here instead of following the login's
        // intended redirect, so a page the user was heading to (a notification
        // link, a terminal QR scan) is honoured once the password is set.
        return redirect()->intended(route($user->dashboardRouteName()))->with('status', 'Password updated.');
    }
}
