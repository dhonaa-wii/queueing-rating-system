<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Services\RestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RestoreController extends Controller
{
    public function __construct(private RestoreService $restores) {}

    /**
     * Check the archive, close the site and begin. Everything that can be wrong
     * with the file (password, integrity, version) is reported here, before any
     * data has been touched.
     */
    public function store(Request $request, Backup $backup): JsonResponse
    {
        $request->validate([
            'confirm' => ['required', 'in:RESTORE'],
            'archive_password' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            [$restore, $token] = $this->restores->start($backup, $request->user(), $request->input('archive_password'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->state($restore) + ['token' => $token]);
    }

    /**
     * Advance a run. Authenticated by the run's token rather than the session:
     * the users table is rewritten part-way through, so anything that has to
     * look a user up in it would fail at exactly the wrong moment.
     */
    public function step(Request $request, BackupRestore $restore): JsonResponse
    {
        abort_unless($this->restores->verifyToken($restore, $request->header('X-Restore-Token')), 403);

        return response()->json($this->state($this->restores->step($restore, 20)));
    }

    /** @return array<string, mixed> */
    private function state(BackupRestore $restore): array
    {
        return [
            'id' => $restore->id,
            'status' => $restore->status,
            'progress' => $restore->progressPercent(),
            'phase_label' => $restore->phaseLabel(),
            'error' => $restore->status === BackupRestore::FAILED ? $restore->error_message : null,
            'step_url' => route('super-admin.restores.step', $restore),
        ];
    }
}
