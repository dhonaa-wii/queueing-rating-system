<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Models\ConnectionStatus;
use App\Models\RoomSession;
use App\Models\RoomSessionAccount;
use App\Models\RoomTerminal;
use App\Models\TerminalConnection;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single place that decides whether a panelist may occupy a specific
 * room_terminal seat, and does it — shared by both ways a panelist can
 * identify themselves on an already room-account-claimed tablet: scanning
 * the QR with their phone (Panelist\TerminalScanController) or typing their
 * personal username/password directly into the tablet's fallback form
 * (RoomSessionController::manualLogin()). Keeping the eligibility/conflict
 * rules in one place avoids the two entry points drifting apart.
 *
 * User-directed 2026-08-22: this used to hard-block anyone not already
 * assigned/backup somewhere in the room (see the removed eligibility check
 * below). It no longer does — a panelist who isn't the exact assigned
 * seat-holder for the room's current/next group is still let in; instead
 * classifyForRoom() below tells the caller (RoomSessionController) whether
 * to route them through App\Services\PanelSubstitutionService's
 * self-service (backup) or Admin-approval (unassigned) replacement flow.
 */
class TerminalConnectionService
{
    public function __construct(private RoomQueuePreviewService $roomQueuePreviewService)
    {
    }

    public function connect(RoomTerminal $terminal, User $panelist, string $authenticatedVia, ?string $ip, ?string $userAgent): array
    {
        $terminal->loadMissing('roomSession.presentationDateRoom.presentationDate');

        if (! $terminal->is_enabled) {
            return $this->failure('This terminal is not enabled.');
        }

        $roomSession = $terminal->roomSession;
        $room = $roomSession->presentationDateRoom;

        $occupied = TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->exists();

        if ($occupied) {
            return $this->failure('This terminal seat is already occupied — ask an Administrator to clear it if that\'s wrong.');
        }

        $roomTerminalIds = $roomSession->roomTerminals()->pluck('id');

        // database-schema.md's own rule for terminal_connections: "One
        // panelist cannot occupy two terminals in the same room session."
        $alreadyElsewhere = TerminalConnection::whereIn('room_terminal_id', $roomTerminalIds)
            ->where('panelist_user_id', $panelist->id)
            ->whereNull('disconnected_at')
            ->exists();

        if ($alreadyElsewhere) {
            return $this->failure('You are already connected to another seat in this room.');
        }

        // Scoped by presentation_date_id (not just category+room_name) —
        // user-directed 2026-09-07: an account is generated fresh per day
        // now, so two different dates in the same category could each have
        // their own live account for a same-named room at once (e.g. an
        // extended day overlapping the next day's Start Event); this must
        // resolve to the one belonging to *this* room's own day, not
        // whichever row happens to match category+room_name first.
        $account = RoomSessionAccount::where('presentation_date_id', $room->presentation_date_id)
            ->where('room_name', $room->room_name)
            ->where('is_active', true)
            ->first();

        // Falls back to the room's own terminal count if the account is
        // somehow missing — the seat count itself is the real cap either way.
        $cap = $account->max_concurrent_logins ?? $roomTerminalIds->count();

        $activeInRoom = TerminalConnection::whereIn('room_terminal_id', $roomTerminalIds)
            ->whereNull('disconnected_at')
            ->count();

        if ($activeInRoom >= $cap) {
            return $this->failure("This room is already at its maximum of {$cap} logged-in panelist(s).");
        }

        $connectedStatus = ConnectionStatus::where('code', 'CONNECTED')->firstOrFail();

        $connection = TerminalConnection::create([
            'room_terminal_id' => $terminal->id,
            'panelist_user_id' => $panelist->id,
            'connected_at' => now(),
            'connection_status_id' => $connectedStatus->id,
            'authenticated_via' => $authenticatedVia,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 250) : null,
        ]);

        return ['ok' => true, 'connection' => $connection, 'classification' => $this->classifyForRoom($roomSession, $panelist)];
    }

    /**
     * Any assigned/backup panelist for *any* attempt ever scheduled in this
     * room — deliberately broad, unrelated to classifyForRoom() below. Still
     * used for canEvaluate gating and PresentationControlService's staffing
     * checks, so it's left exactly as it always was.
     */
    public function eligiblePanelistIds(RoomSession $roomSession): Collection
    {
        return AttemptPanelAssignment::whereHas(
            'presentationAttempt.attemptSchedule',
            fn ($query) => $query->where('presentation_date_room_id', $roomSession->presentation_date_room_id)
        )
            ->whereHas('assignmentStatus', fn ($query) => $query->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->pluck('panelist_user_id')
            ->unique();
    }

    /**
     * User-directed 2026-09-07: flow control (Presentation Control's Call
     * Next/Start/Pause/Resume/Complete/Defer/End Room buttons) no longer
     * belongs to a fixed "Terminal 1/Lead" seat — it follows whichever
     * panelist the Admin explicitly designated Lead/Chair in Group & Panel
     * Assignment (attempt_panel_assignments.is_lead), wherever that
     * panelist actually logs in. Deliberately room-wide, same broad scope
     * as eligiblePanelistIds() above rather than scoped to one specific
     * current/called/next attempt — a panel is normally assigned
     * identically across a whole room's queue for the day
     * (PanelAssignmentService::assign()'s multi-select "same panel for
     * every selected group" case), so this reads as "is this panelist the
     * room's Lead" rather than "the Lead for this exact group."
     */
    public function isLead(RoomSession $roomSession, User $panelist): bool
    {
        return AttemptPanelAssignment::whereHas(
            'presentationAttempt.attemptSchedule',
            fn ($query) => $query->where('presentation_date_room_id', $roomSession->presentation_date_room_id)
        )
            ->where('panelist_user_id', $panelist->id)
            ->where('is_lead', true)
            ->whereHas('assignmentStatus', fn ($query) => $query->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->exists();
    }

    /**
     * Narrow, room-current-moment classification — drives the terminal's
     * post-login UX (App\Services\PanelSubstitutionService), unlike
     * eligiblePanelistIds() above. Scoped to the room's immediate target
     * attempt (current ?? called ?? next, from RoomQueuePreviewService,
     * same triage the tablet's own Current/Called/Next display already
     * uses) — "assigned panel for the next/current group," per spec. If the
     * room has nothing current/called/queued at all, there's nothing to
     * classify against.
     */
    public function classifyForRoom(RoomSession $roomSession, User $panelist): array
    {
        $roomSession->loadMissing('presentationDateRoom');
        $room = $roomSession->presentationDateRoom;

        $queue = $this->roomQueuePreviewService->forRoom($room);
        $targetSchedule = $queue['current'] ?? $queue['called'] ?? $queue['next'] ?? null;

        if (! $targetSchedule) {
            return ['kind' => 'no-target', 'attempt' => null];
        }

        $attempt = $targetSchedule->presentationAttempt;

        $assignment = AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)
            ->where('panelist_user_id', $panelist->id)
            ->whereHas('assignmentStatus', fn ($query) => $query->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with('assignmentKind')
            ->first();

        if (! $assignment) {
            return ['kind' => 'unassigned', 'attempt' => $attempt];
        }

        $kind = match (true) {
            $assignment->is_lead => 'lead',
            $assignment->assignmentKind->code === 'BACKUP_PANELIST' => 'backup',
            default => 'assigned',
        };

        return [
            'kind' => $kind,
            'attempt' => $attempt,
        ];
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
