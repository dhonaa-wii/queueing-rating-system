<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Models\ConnectionStatus;
use App\Models\PanelAssignmentKind;
use App\Models\PanelAssignmentStatus;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\RoomSession;
use App\Models\SubstitutionStatus;
use App\Models\TerminalConnection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backs the terminal-login replacement flow (user-directed 2026-08-22):
 * a panelist who isn't the exact assigned seat-holder for a room's
 * current/next group is let in by TerminalConnectionService::connect()
 * rather than blocked, then picks which not-yet-logged-in assigned seat
 * they're requesting to fill. A backup panelist's pick applies immediately
 * (self-service, no Admin needed); a genuinely unassigned panelist's pick
 * stays PENDING until an Admin approves it. Both paths write to
 * panel_substitution_requests/substitution_statuses — existing,
 * fully-migrated-and-seeded tables with no prior writer anywhere in this
 * codebase (confirmed via grep before building), shaped for exactly this
 * ("requests and approvals for panelist substitution").
 */
class PanelSubstitutionService
{
    public function __construct(
        private NotificationService $notifications,
        private PanelistConflictService $conflicts,
        private PanelistNotifier $panelistNotifier,
    ) {
    }

    /**
     * The "assigned panel that is not yet logged in" list both the backup
     * and unassigned pickers show — deliberately narrower than
     * TerminalConnectionService::eligiblePanelistIds(): only
     * ASSIGNED_PANELIST rows for this one attempt, and only those with no
     * live TerminalConnection anywhere in the room session right now.
     */
    public function replaceableCandidates(PresentationAttempt $attempt, RoomSession $roomSession): Collection
    {
        $connectedPanelistIds = TerminalConnection::whereHas(
            'roomTerminal',
            fn ($query) => $query->where('room_session_id', $roomSession->id)
        )
            ->whereNull('disconnected_at')
            ->pluck('panelist_user_id');

        return AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)
            ->whereHas('assignmentKind', fn ($query) => $query->where('code', 'ASSIGNED_PANELIST'))
            ->whereHas('assignmentStatus', fn ($query) => $query->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->whereNotIn('panelist_user_id', $connectedPanelistIds)
            ->with('panelist.profile')
            ->get();
    }

    /**
     * $kind is 'backup' or 'unassigned', from
     * TerminalConnectionService::classifyForRoom(). $originalPanelistUserId
     * is re-validated against replaceableCandidates() here — never trusted
     * from the client, same posture as every other service in this app.
     */
    public function request(PresentationAttempt $attempt, RoomSession $roomSession, User $substitute, int $originalPanelistUserId, string $kind, int $requestedByUserId): array
    {
        $candidate = $this->replaceableCandidates($attempt, $roomSession)
            ->firstWhere('panelist_user_id', $originalPanelistUserId);

        if (! $candidate) {
            return $this->failure('That panelist is no longer available to replace — they may have already logged in, or someone else already requested this swap.');
        }

        // Avoids a duplicate PENDING row (and a duplicate round of Admin
        // notifications) if the tablet's form is submitted twice for the
        // same still-pending request — the 'unassigned' path is the only
        // one that can actually stay pending long enough for this to
        // happen; 'backup' resolves synchronously below.
        $existingPending = PanelSubstitutionRequest::where('presentation_attempt_id', $attempt->id)
            ->where('requested_substitute_user_id', $substitute->id)
            ->whereHas('status', fn ($query) => $query->where('code', 'PENDING'))
            ->first();

        if ($existingPending) {
            return ['ok' => true, 'status' => 'PENDING', 'request' => $existingPending];
        }

        $pendingStatus = SubstitutionStatus::where('code', 'PENDING')->firstOrFail();

        $substitutionRequest = PanelSubstitutionRequest::create([
            'presentation_attempt_id' => $attempt->id,
            'original_panelist_user_id' => $originalPanelistUserId,
            'requested_substitute_user_id' => $substitute->id,
            'requested_by' => $requestedByUserId,
            'reason' => $kind === 'backup'
                ? 'Alternate panel self-identified via terminal login.'
                : 'Unassigned panelist requested via terminal login.',
            'status_id' => $pendingStatus->id,
        ]);

        if ($kind === 'backup') {
            // Self-service — no Admin reviewed this, so reviewed_by stays
            // null; assigned_by is the backup panelist themselves.
            return $this->approve($substitutionRequest, $substitute->id, reviewerId: null);
        }

        $substitutionRequest->load('originalPanelist.profile', 'requestedSubstitute.profile', 'presentationAttempt.researchGroup');
        $group = $substitutionRequest->presentationAttempt->researchGroup;
        $originalName = $this->displayName($substitutionRequest->originalPanelist);
        $substituteName = $this->displayName($substitutionRequest->requestedSubstitute);

        $this->notifications->notifyRole(
            'ADMIN',
            'PANEL_SUBSTITUTION_REQUESTED',
            'Panel substitution needs review',
            "{$substituteName} is requesting to replace {$originalName} on {$group->group_reference}'s panel.",
            $substitutionRequest
        );

        return ['ok' => true, 'status' => 'PENDING', 'request' => $substitutionRequest];
    }

    /**
     * The panelist-initiated half of the same workflow, from My Assignments:
     * a panelist who can't make a presentation reports it and the Admin
     * arranges the replacement. Unlike request() above, no substitute is
     * named — user-directed 2026-08-15, "panel will not suggest sub," which
     * is what panel_substitution_requests.requested_substitute_user_id was
     * made nullable for. Nothing is swapped here: the row is the report, and
     * the Admin resolves it through Group & Panel Assignment's Assign Panel.
     */
    public function requestUnavailability(AttemptPanelAssignment $assignment, int $panelistUserId, string $reason): array
    {
        if ($assignment->panelist_user_id !== $panelistUserId) {
            return $this->failure('That assignment is not yours.');
        }

        $assignment->loadMissing('assignmentStatus', 'presentationAttempt.presentationStatus', 'presentationAttempt.researchGroup');

        if (in_array($assignment->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true)) {
            return $this->failure('You are no longer on this panel.');
        }

        if ($assignment->presentationAttempt?->presentationStatus?->is_terminal) {
            return $this->failure('This presentation already has a recorded outcome.');
        }

        // User-directed 2026-09-20: allowed for an upcoming assignment even
        // once its day/room has started — only once the group's own
        // presentation is actually ONGOING (or PAUSED mid-presentation) is it
        // too late to swap the panelist out. CALLED (invited up, not yet
        // started) is still fine.
        if (in_array($assignment->presentationAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)) {
            return $this->failure('This group is currently presenting — it can no longer be marked unavailable.');
        }

        // "To be scheduled" / "To be assigned": no date, slot or room yet, so
        // there is nothing to be unavailable for.
        if ($assignment->presentationAttempt?->attemptSchedule?->isAwaitingReschedule($assignment->presentationAttempt)) {
            return $this->failure('This group has no schedule yet — it can be marked unavailable once it is scheduled.');
        }

        // Same duplicate guard as request() — a second submission for a
        // still-pending report is a no-op rather than another Admin ping.
        $existingPending = PanelSubstitutionRequest::where('presentation_attempt_id', $assignment->presentation_attempt_id)
            ->where('original_panelist_user_id', $panelistUserId)
            ->whereHas('status', fn ($query) => $query->where('code', 'PENDING'))
            ->first();

        if ($existingPending) {
            return ['ok' => true, 'status' => 'PENDING', 'request' => $existingPending];
        }

        $substitutionRequest = PanelSubstitutionRequest::create([
            'presentation_attempt_id' => $assignment->presentation_attempt_id,
            'original_panelist_user_id' => $panelistUserId,
            'requested_substitute_user_id' => null,
            'requested_by' => $panelistUserId,
            'reason' => $reason,
            'status_id' => SubstitutionStatus::where('code', 'PENDING')->firstOrFail()->id,
        ]);

        $group = $assignment->presentationAttempt->researchGroup;
        $panelistName = $this->displayName($assignment->panelist);

        $this->notifications->notifyRole(
            'ADMIN',
            'PANEL_SUBSTITUTION_REQUESTED',
            'Panelist reported unavailable',
            "{$panelistName} is unavailable for {$group->group_reference}'s panel.",
            $substitutionRequest
        );

        return ['ok' => true, 'status' => 'PENDING', 'request' => $substitutionRequest];
    }

    /**
     * $performedByUserId drives applySwap()'s assigned_by — the acting
     * Admin for a normal review, or the backup panelist themselves for the
     * self-service path (request()'s 'backup' branch). $reviewerId is null
     * for that same self-service path since nobody actually reviewed it.
     */
    public function approve(PanelSubstitutionRequest $substitutionRequest, int $performedByUserId, ?int $reviewerId = null): array
    {
        $substitutionRequest->loadMissing('status');

        if ($substitutionRequest->status->code !== 'PENDING') {
            return $this->failure('This request has already been reviewed.');
        }

        // An unavailability report names nobody to swap in, so there is no
        // approve-in-one-click for it — the Admin picks the replacement in
        // Group & Panel Assignment, which is where its notification links.
        // Defensive: the bell does not offer Confirm on these.
        if ($substitutionRequest->requested_substitute_user_id === null) {
            return $this->failure('This is an unavailability report with no substitute named — assign the replacement panel in Group & Panel Assignment.');
        }

        DB::transaction(function () use ($substitutionRequest, $performedByUserId, $reviewerId) {
            $this->applySwap($substitutionRequest, $performedByUserId);

            $substitutionRequest->update([
                'status_id' => SubstitutionStatus::where('code', 'APPROVED')->firstOrFail()->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);
        });

        $this->panelistNotifier->replaced($substitutionRequest, $reviewerId);
        $this->panelistNotifier->assigned($substitutionRequest->requested_substitute_user_id, [$substitutionRequest->presentation_attempt_id], $performedByUserId);

        return ['ok' => true, 'status' => 'APPROVED', 'request' => $substitutionRequest->fresh()];
    }

    /**
     * Resolves a substitute-less unavailability report from Group & Panel
     * Assignment (user-directed 2026-09-20): the Admin picks exactly one
     * replacement for the exact seat the reporting panelist held — every
     * other assignment on the attempt (backup, other assigned seats, who's
     * Lead) is left untouched, same as applySwap() already does for the
     * named-substitute approve() path above. Re-validated here rather than
     * trusted from the client: the substitute must be a real, login-allowed
     * panelist, not already holding a live seat on this attempt, clear of any
     * real schedule conflict (the same rule PanelAssignmentService::assign()
     * enforces for a full assignment), and — when the category requires a
     * technical adviser — not that group's own adviser.
     */
    public function assignReplacement(PanelSubstitutionRequest $substitutionRequest, int $substituteUserId, int $performedByUserId): array
    {
        $substitutionRequest->loadMissing(
            'status',
            'presentationAttempt.presentationStatus',
            'presentationAttempt.attemptSchedule',
            'presentationAttempt.researchGroup.category',
            'presentationAttempt.attemptPanelAssignments.assignmentStatus'
        );

        if ($substitutionRequest->status->code !== 'PENDING') {
            return $this->failure('This request has already been reviewed.');
        }

        if ($substitutionRequest->requested_substitute_user_id !== null) {
            return $this->failure('This request already names a substitute — use Confirm/Reject instead.');
        }

        $attempt = $substitutionRequest->presentationAttempt;

        if (! $attempt || $attempt->presentationStatus?->is_terminal) {
            return $this->failure('This presentation already has a recorded outcome.');
        }

        $substitute = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->whereHas('accountStatus', fn ($q) => $q->where('is_login_allowed', true))
            ->with('profile')
            ->find($substituteUserId);

        if (! $substitute) {
            return $this->failure('Select a panelist to fill this seat.');
        }

        $liveAssignments = $attempt->attemptPanelAssignments
            ->reject(fn ($pa) => in_array($pa->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true));

        if ($liveAssignments->contains('panelist_user_id', $substituteUserId)) {
            return $this->failure('That panelist already holds a seat on this group\'s panel.');
        }

        $group = $attempt->researchGroup;
        $category = $group?->category;

        if ($group && $category?->technical_adviser_required
            && PanelAssignmentService::nameMatchesPanelist((string) $group->technical_adviser_name, $substitute)) {
            return $this->failure("{$group->technical_adviser_name} is this group's technical adviser and cannot also be assigned as a panelist.");
        }

        $schedule = $attempt->attemptSchedule;

        if ($schedule) {
            $conflicts = $this->conflicts->conflictingAssignments($schedule, [$substituteUserId], [$attempt->id]);

            if ($conflicts->isNotEmpty()) {
                return $this->failure($this->conflicts->messageFor($attempt, $conflicts->first()));
            }
        }

        DB::transaction(function () use ($substitutionRequest, $substituteUserId, $performedByUserId) {
            $substitutionRequest->update(['requested_substitute_user_id' => $substituteUserId]);

            $this->applySwap($substitutionRequest->fresh(), $performedByUserId);

            $substitutionRequest->update([
                'status_id' => SubstitutionStatus::where('code', 'APPROVED')->firstOrFail()->id,
                'reviewed_by' => $performedByUserId,
                'reviewed_at' => now(),
            ]);
        });

        $this->panelistNotifier->unavailabilityApproved($substitutionRequest, $performedByUserId);
        $this->panelistNotifier->assigned($substituteUserId, [$substitutionRequest->presentation_attempt_id], $performedByUserId);

        return ['ok' => true, 'status' => 'APPROVED', 'request' => $substitutionRequest->fresh()];
    }

    public function reject(PanelSubstitutionRequest $substitutionRequest, int $reviewerId): array
    {
        $substitutionRequest->loadMissing('status');

        if ($substitutionRequest->status->code !== 'PENDING') {
            return $this->failure('This request has already been reviewed.');
        }

        DB::transaction(function () use ($substitutionRequest, $reviewerId) {
            $substitutionRequest->update([
                'status_id' => SubstitutionStatus::where('code', 'REJECTED')->firstOrFail()->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            // Frees the seat right away — the tablet's next poll notices the
            // connection is gone and drops back to the QR/manual-login
            // screen (user-confirmed 2026-08-22 behavior on rejection).
            // connect()'s own "already elsewhere" guard already prevents a
            // panelist from holding more than one live seat at a time, so
            // there's at most one connection to close here.
            TerminalConnection::where('panelist_user_id', $substitutionRequest->requested_substitute_user_id)
                ->whereNull('disconnected_at')
                ->update([
                    'disconnected_at' => now(),
                    'connection_status_id' => ConnectionStatus::where('code', 'DISCONNECTED')->firstOrFail()->id,
                ]);
        });

        return ['ok' => true, 'status' => 'REJECTED', 'request' => $substitutionRequest->fresh()];
    }

    /**
     * If the panelist being replaced was the room's designated Lead
     * (attempt_panel_assignments.is_lead, user-directed 2026-09-07), the
     * substitute inherits Lead status too — otherwise flow-control would
     * silently vanish from the room the moment the Lead is swapped out.
     */
    private function applySwap(PanelSubstitutionRequest $substitutionRequest, int $performedByUserId): void
    {
        $assignedKind = PanelAssignmentKind::where('code', 'ASSIGNED_PANELIST')->firstOrFail();
        $assignedStatus = PanelAssignmentStatus::where('code', 'ASSIGNED')->firstOrFail();
        $replacedStatus = PanelAssignmentStatus::where('code', 'REPLACED')->firstOrFail();
        $now = now();

        $originalAssignment = AttemptPanelAssignment::where('presentation_attempt_id', $substitutionRequest->presentation_attempt_id)
            ->where('panelist_user_id', $substitutionRequest->original_panelist_user_id)
            ->whereHas('assignmentStatus', fn ($query) => $query->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->first();

        $wasLead = (bool) $originalAssignment?->is_lead;

        $originalAssignment?->update(['assignment_status_id' => $replacedStatus->id, 'ended_at' => $now]);

        AttemptPanelAssignment::updateOrCreate(
            [
                'presentation_attempt_id' => $substitutionRequest->presentation_attempt_id,
                'panelist_user_id' => $substitutionRequest->requested_substitute_user_id,
            ],
            [
                'assignment_kind_id' => $assignedKind->id,
                'is_lead' => $wasLead,
                'assignment_status_id' => $assignedStatus->id,
                'assigned_by' => $performedByUserId,
                'assigned_at' => $now,
                'ended_at' => null,
            ]
        );
    }

    private function displayName(?User $user): string
    {
        if (! $user) {
            return 'Someone';
        }

        $user->loadMissing('profile');
        $name = trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? ''));

        return $name !== '' ? $name : $user->username;
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
