<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Support\SecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super Admin → Security Settings (2026-10-16): login lockout, session
 * timeout, password rules and password age. A blank field removes its
 * `system_settings` row, which puts that part back to the built-in
 * behaviour (see SecurityPolicy).
 */
class SecuritySettingController extends Controller
{
    public function index()
    {
        return view('super-admin.settings.security', [
            'lockout' => SecurityPolicy::lockout(),
            'sessionTimeout' => SecurityPolicy::sessionTimeout(),
            'defaultSessionLifetime' => (int) config('session.env_lifetime', config('session.lifetime')),
            'passwordRules' => SecurityPolicy::passwordRules(),
            'customPasswordRules' => SecurityPolicy::hasCustomPasswordRules(),
            'passwordMaxAge' => SecurityPolicy::passwordMaxAgeDays(),
        ]);
    }

    public function update(Request $request, string $section)
    {
        match ($section) {
            'lockout' => $this->saveLockout($request),
            'session' => $this->saveSession($request),
            'password' => $this->savePassword($request),
        };

        SecurityPolicy::forget();

        return redirect()->route('super-admin.settings.security.index')->with('status', 'Security settings saved.');
    }

    private function saveLockout(Request $request): void
    {
        $validated = $request->validate([
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100', 'required_with:lockout_minutes'],
            'lockout_minutes' => ['nullable', 'integer', 'min:1', 'max:1440', 'required_with:max_attempts'],
        ], [
            'max_attempts.required_with' => 'Set both the attempts and the lock duration, or leave both blank.',
            'lockout_minutes.required_with' => 'Set both the attempts and the lock duration, or leave both blank.',
        ]);

        $this->put($request, SecurityPolicy::LOCKOUT, 'json', blank($validated['max_attempts']) ? null : [
            'max_attempts' => (int) $validated['max_attempts'],
            'minutes' => (int) $validated['lockout_minutes'],
        ]);
    }

    private function saveSession(Request $request): void
    {
        $validated = $request->validate([
            'session_timeout' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $value = $validated['session_timeout'];
        $this->put($request, SecurityPolicy::SESSION_TIMEOUT, 'integer', blank($value) ? null : (int) $value);
    }

    private function savePassword(Request $request): void
    {
        $validated = $request->validate([
            'min_length' => ['required', 'integer', 'min:4', 'max:64'],
            'max_age_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $rules = [
            'min_length' => (int) $validated['min_length'],
            'uppercase' => $request->boolean('uppercase'),
            'lowercase' => $request->boolean('lowercase'),
            'number' => $request->boolean('number'),
            'symbol' => $request->boolean('symbol'),
        ];

        // Rules identical to the built-in default are stored as "not set".
        $this->put($request, SecurityPolicy::PASSWORD_RULES, 'json',
            $rules === SecurityPolicy::DEFAULT_PASSWORD_RULES ? null : $rules);

        $age = $validated['max_age_days'];
        $this->put($request, SecurityPolicy::PASSWORD_MAX_AGE, 'integer', blank($age) ? null : (int) $age);
    }

    /** Save one setting; null removes it. Audited only when it changes. */
    private function put(Request $request, string $key, string $type, mixed $value): void
    {
        $existing = SystemSetting::where('setting_key', $key)->first();
        $old = $existing?->setting_value;

        if ($old === $value) {
            return;
        }

        if ($value === null) {
            $existing?->delete();
        } else {
            SystemSetting::updateOrCreate(['setting_key' => $key], [
                'setting_value' => $value,
                'value_type' => $type,
                'updated_by' => $request->user()->id,
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'SECURITY_SETTING_UPDATED',
            'entity_type' => SystemSetting::class,
            'entity_id' => $existing?->id,
            'old_values' => [$key => $old],
            'new_values' => [$key => $value],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
