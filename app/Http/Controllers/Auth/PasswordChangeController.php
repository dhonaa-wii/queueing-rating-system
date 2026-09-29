<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordChangeController extends Controller
{
    public function edit(Request $request)
    {
        return view('auth.password-change', [
            'forced' => $request->user()->mustChangePassword(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $activeStatus = AccountStatus::where('code', 'ACTIVE')->firstOrFail();

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'account_status_id' => $activeStatus->id,
        ])->save();

        // Once the panelist sets their own password, the temp password Admin could
        // see in the Panelist Registry is no longer valid — clear it so the View
        // page stops showing it (no-op for roles without a panelist profile).
        $user->panelistProfile()->update(['temporary_password' => null]);

        return redirect()->route($user->dashboardRouteName())->with('status', 'Password updated.');
    }
}
