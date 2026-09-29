<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecuritySettingController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::orderBy('setting_key')->get();

        return view('super-admin.settings.security', compact('settings'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateSetting($request);

        SystemSetting::create([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'SYSTEM_SETTING_CREATED', $validated['setting_key']);

        return back()->with('status', 'Setting added.');
    }

    public function update(Request $request, SystemSetting $setting)
    {
        $validated = $this->validateSetting($request, $setting);

        $setting->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'SYSTEM_SETTING_UPDATED', $setting->setting_key);

        return back()->with('status', 'Setting updated.');
    }

    public function destroy(Request $request, SystemSetting $setting)
    {
        $key = $setting->setting_key;
        $setting->delete();

        $this->audit($request, 'SYSTEM_SETTING_DELETED', $key);

        return back()->with('status', 'Setting removed.');
    }

    private function validateSetting(Request $request, ?SystemSetting $setting = null): array
    {
        $validated = $request->validate([
            'setting_key' => [
                'required', 'string', 'max:150',
                Rule::unique('system_settings', 'setting_key')->ignore($setting?->id),
            ],
            'value_type' => ['required', Rule::in(['string', 'integer', 'boolean', 'json'])],
            'setting_value' => ['required', 'string'],
        ]);

        $validated['setting_value'] = match ($validated['value_type']) {
            'integer' => (int) $validated['setting_value'],
            'boolean' => filter_var($validated['setting_value'], FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($validated['setting_value'], true) ?? [],
            default => $validated['setting_value'],
        };

        return $validated;
    }

    private function audit(Request $request, string $action, string $key): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => SystemSetting::class,
            'new_values' => ['setting_key' => $key],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
