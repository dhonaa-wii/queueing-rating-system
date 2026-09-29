<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelSubstitutionRequest;
use App\Services\NotificationService;
use App\Services\PanelSubstitutionService;
use Illuminate\Http\Request;

/**
 * Global (not nested under a category) — the Dashboard's Needs Attention card
 * and Group & Panel Assignment's own needs-attention list both need to work
 * across every category at once and post to these same routes (see
 * Admin\PanelAssignmentController::show()'s $needsAttention extension).
 * Backed by App\Services\PanelSubstitutionService +
 * App\Services\NotificationService, both new 2026-08-22.
 *
 * The JSON index() the topbar notification bell used to poll, and the
 * markNotificationRead() its tiles fired on click, were removed with that
 * bell (2026-09-22). The six automatic sweeps that piggybacked on that poll
 * moved to App\Http\Middleware\RunAdminMaintenanceSweeps — see its own doc
 * comment. Notifications themselves are still written, and are still shown by
 * the Dashboard's Needs Attention card.
 */
class PanelSubstitutionController extends Controller
{
    /**
     * Resolves a substitute-less unavailability report (§2.12) with exactly
     * one named replacement — the Group & Panel Assignment "Assign
     * Replacement" action (user-directed 2026-09-20), reached from the
     * Needs Attention link's blinking row. Every other seat on the attempt's
     * panel is left exactly as it is; see
     * PanelSubstitutionService::assignReplacement().
     */
    public function assignReplacement(Request $request, PanelSubstitutionRequest $substitutionRequest, PanelSubstitutionService $service, NotificationService $notifications)
    {
        $validated = $request->validate([
            'substitute_user_id' => ['required', 'integer'],
        ]);

        $result = $service->assignReplacement($substitutionRequest, (int) $validated['substitute_user_id'], $request->user()->id);

        if ($result['ok']) {
            $notifications->markRelatedRead($substitutionRequest);
        }

        return $this->respond($request, $result, 'Replacement panelist assigned.');
    }

    public function approve(Request $request, PanelSubstitutionRequest $substitutionRequest, PanelSubstitutionService $service, NotificationService $notifications)
    {
        $adminId = $request->user()->id;
        $result = $service->approve($substitutionRequest, $adminId, $adminId);

        if ($result['ok']) {
            $notifications->markRelatedRead($substitutionRequest);
        }

        return $this->respond($request, $result, 'Substitution approved.');
    }

    public function reject(Request $request, PanelSubstitutionRequest $substitutionRequest, PanelSubstitutionService $service, NotificationService $notifications)
    {
        $result = $service->reject($substitutionRequest, $request->user()->id);

        if ($result['ok']) {
            $notifications->markRelatedRead($substitutionRequest);
        }

        return $this->respond($request, $result, 'Substitution rejected.');
    }

    private function respond(Request $request, array $result, string $successMessage)
    {
        if ($request->wantsJson()) {
            return $result['ok']
                ? response()->json(['message' => $successMessage])
                : response()->json(['message' => $result['error']], 422);
        }

        return $result['ok']
            ? back()->with('status', $successMessage)
            : back()->with('error', $result['error']);
    }
}
