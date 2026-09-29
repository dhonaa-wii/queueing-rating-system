<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\BackupSetting;
use App\Models\MaintenanceSetting;
use App\Services\BackupService;
use App\Services\HealthService;
use App\Services\MaintenanceService;
use App\Support\MaintenanceMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use ZipArchive;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backups,
        private HealthService $health,
        private MaintenanceService $maintenance,
    ) {}

    public function index()
    {
        $backups = Backup::with('triggeredBy')->latest('id')->limit(50)->get();
        $running = Backup::where('status', Backup::RUNNING)->latest('id')->first();
        $lastCompleted = $backups->firstWhere('status', Backup::COMPLETED);

        return view('super-admin.backups.index', [
            'backups' => $backups,
            'running' => $running,
            'lastCompleted' => $lastCompleted,
            'settings' => BackupSetting::current(),
            'storedCount' => Backup::where('status', Backup::COMPLETED)->count(),
            'storedBytes' => $this->backups->totalSizeBytes(),
            'freeBytes' => @disk_free_space(storage_path()) ?: null,
            'health' => $this->health->report(),
            'maintenanceSettings' => MaintenanceSetting::current(),
            'cleanupPreview' => $this->maintenance->preview(),
            'logStatus' => $this->maintenance->logStatus(),
            'maintenanceState' => MaintenanceMode::state(),
            'lastRestore' => BackupRestore::latest('id')->first(),
        ]);
    }

    /** Begin a run; the page then drives it to the end through step(). */
    public function store(Request $request): JsonResponse
    {
        try {
            $backup = $this->backups->start('MANUAL', $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit($request, 'BACKUP_STARTED', $backup);

        return $this->state($backup);
    }

    /**
     * Add an archive made elsewhere (another install, an off-site copy) to the
     * list so it can be restored. It is checked to be one of ours, not opened:
     * an encrypted archive can't be read until the restore asks for its password.
     */
    public function upload(Request $request)
    {
        $request->validate(['archive' => ['required', 'file', 'mimes:zip']]);

        $file = $request->file('archive');
        $this->backups->prepareDirectory();

        $name = 'qrs-upload-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip';
        $file->storeAs('backups', $name, 'local');
        $path = $this->backups->disk()->path('backups/'.$name);

        $zip = new ZipArchive;
        $manifest = $zip->open($path) === true ? $zip->statName('manifest.json') : false;
        $database = $manifest !== false ? $zip->statName('database.sql') : false;
        $encrypted = $manifest !== false && ($manifest['encryption_method'] ?? 0) !== 0;
        $zip->close();

        if ($manifest === false || $database === false) {
            @unlink($path);

            return back()->with('error', 'That file is not a backup made by this system.');
        }

        $backup = Backup::create([
            'status' => Backup::COMPLETED,
            'trigger' => 'UPLOADED',
            'triggered_by' => $request->user()->id,
            'file_name' => $name,
            'size_bytes' => filesize($path),
            'checksum_sha256' => hash_file('sha256', $path),
            'is_encrypted' => $encrypted,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->audit($request, 'BACKUP_UPLOADED', $backup, ['original_name' => $file->getClientOriginalName()]);

        return back()->with('status', 'Backup uploaded. It can now be restored from the list.');
    }

    public function step(Backup $backup): JsonResponse
    {
        return $this->state($this->backups->step($backup, 20));
    }

    public function download(Request $request, Backup $backup)
    {
        abort_unless($backup->isCompleted() && $backup->relativePath(), 404);
        abort_unless($this->backups->disk()->exists($backup->relativePath()), 404);

        $this->audit($request, 'BACKUP_DOWNLOADED', $backup);

        return $this->backups->disk()->download($backup->relativePath(), $backup->file_name);
    }

    public function destroy(Request $request, Backup $backup)
    {
        $this->audit($request, 'BACKUP_DELETED', $backup);
        $this->backups->delete($backup);

        return back()->with('status', 'Backup deleted.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'frequency' => ['required', Rule::in([BackupSetting::OFF, BackupSetting::DAILY, BackupSetting::WEEKLY])],
            'run_time' => ['required', 'date_format:H:i'],
            'keep_count' => ['required', 'integer', 'min:1', 'max:30'],
            'archive_password' => ['nullable', 'string', 'min:8', 'max:100'],
            'clear_password' => ['nullable', 'boolean'],
        ]);

        $settings = BackupSetting::current();
        $settings->fill([
            'frequency' => $validated['frequency'],
            'run_time' => $validated['run_time'],
            'keep_count' => $validated['keep_count'],
            'updated_by' => $request->user()->id,
        ]);

        // Blank keeps the stored password; only an explicit clear removes it.
        if (! empty($validated['archive_password'])) {
            $settings->archive_password = $validated['archive_password'];
        } elseif ($request->boolean('clear_password')) {
            $settings->archive_password = null;
        }

        $settings->save();

        // A smaller "keep" takes effect now rather than at the next backup.
        $this->backups->prune();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'BACKUP_SETTINGS_UPDATED',
            'entity_type' => BackupSetting::class,
            'entity_id' => $settings->id,
            // Never the password itself, only whether one is set.
            'new_values' => [
                'frequency' => $settings->frequency,
                'run_time' => $settings->run_time,
                'keep_count' => $settings->keep_count,
                'archive_password_set' => $settings->archive_password !== null,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Backup settings saved.');
    }

    private function state(Backup $backup): JsonResponse
    {
        return response()->json([
            'id' => $backup->id,
            'status' => $backup->status,
            'progress' => $backup->progressPercent(),
            'error' => $backup->status === Backup::FAILED ? $backup->error_message : null,
            'step_url' => route('super-admin.backups.step', $backup),
        ]);
    }

    private function audit(Request $request, string $action, Backup $backup, array $extra = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => Backup::class,
            'entity_id' => $backup->id,
            'new_values' => array_filter(['file_name' => $backup->file_name] + $extra),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
