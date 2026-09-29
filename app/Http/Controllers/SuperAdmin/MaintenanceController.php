<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MaintenanceSetting;
use App\Services\MaintenanceService;
use App\Support\MaintenanceMode;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            // Blank keeps every entry forever.
            'audit_retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
            'log_max_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'log_keep_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $settings = MaintenanceSetting::current();
        $before = $settings->only(['audit_retention_days', 'log_max_mb', 'log_keep_days']);

        $settings->update([
            'audit_retention_days' => $request->filled('audit_retention_days') ? (int) $validated['audit_retention_days'] : null,
            'log_max_mb' => $validated['log_max_mb'],
            'log_keep_days' => $validated['log_keep_days'],
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'MAINTENANCE_SETTINGS_UPDATED', $settings->id, $before, $settings->only(['audit_retention_days', 'log_max_mb', 'log_keep_days']));

        return $this->back('Maintenance settings saved.');
    }

    public function run(Request $request)
    {
        $result = $this->maintenance->run();

        $this->audit($request, 'MAINTENANCE_RUN', null, null, [
            'removed' => collect($result['items'])->sum('count'),
        ]);

        return $this->back('Maintenance completed.');
    }

    public function setMode(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        if (MaintenanceMode::restoring()) {
            return $this->back('A restore is in progress.', true);
        }

        if ($validated['enabled']) {
            MaintenanceMode::enable(MaintenanceMode::MAINTENANCE, $validated['message'] ?? null, $request->user()->username);
        } else {
            MaintenanceMode::disable();
        }

        $this->audit($request, $validated['enabled'] ? 'MAINTENANCE_MODE_ENABLED' : 'MAINTENANCE_MODE_DISABLED', null, null, array_filter([
            'message' => $validated['message'] ?? null,
        ]));

        return $this->back($validated['enabled'] ? 'Maintenance mode is on.' : 'Maintenance mode is off.');
    }

    private function back(string $message, bool $error = false)
    {
        return redirect()->to(route('super-admin.backups.index').'#tab-maintenance')
            ->with($error ? 'error' : 'status', $message);
    }

    private function audit(Request $request, string $action, ?int $entityId, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => MaintenanceSetting::class,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
