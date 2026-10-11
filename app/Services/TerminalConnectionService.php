<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Models\ConnectionStatus;
use App\Models\RoomSession;
use App\Models\RoomTerminal;
use App\Models\TerminalConnection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single place that decides whether a panelist may take a seat in a room
 * session, and does it. Called when a panelist presses Join Room in Live Room
 * (RoomSessionController::joinAsPanelist()).
 *
 * User-directed 2026-10-11 (room session redesign): no room account and no
 * terminal picker. A seat (room_terminals row, one per panelist_count) is
 * taken when a panelist joins from their own account and is handed out
 * automatically; once every seat is taken nobody else can join.
 *
 * User-directed 2026-08-22: anyone is let in, not only the assigned panel;
 * classifyForRoom() below tells the caller whether to route them through
 * App\Services\PanelSubstitutionService's self-service (backup) or
 * Admin-approval (unassigned) replacement flow.
 */
class TerminalConnectionService
{
    public function __construct(private RoomQueuePreviewService $roomQueuePreviewService)
    {
    }

    /**
     * Seats a panelist in the room. $preferred is the seat a QR token was
     * issued for: used when it is still free, otherwise the first free seat.
     */
    public function connectToRoom(RoomSession $roomSession, User $panelist, string $authenticatedVia, ?string $ip, ?string $userAgent, ?RoomTerminal $preferred = null): array
    {
        if (! $roomSession->isOpen()) {
            return $this->failure('This room session has ended.');
        }

        return DB::transaction(function () use ($roomSession, $panelist, $authenticatedVia, $ip, $userAgent, $preferred) {
            // Locks the room's seats so two panelists logging in at the same
            // moment can't both be handed the last free one.
            $terminals = RoomTerminal::where('room_session_id', $roomSession->id)
                ->where('is_enabled', true)
                ->orderBy('terminal_number')
                ->lockForUpdate()
                ->get();

            if ($terminals->isEmpty()) {
                return $this->failure('This room has no panel seats.');
            }

            $active = TerminalConnection::whereIn('room_terminal_id', $terminals->pluck('id'))
                ->whereNull('disconnected_at')
                ->get();

            // database-schema.md: "One panelist cannot occupy two terminals in
            // the same room session." Logging in again moves the seat.
            $own = $active->firstWhere('panelist_user_id', $panelist->id);
            $moved = false;

            if ($own) {
                $own->update([
                    'disconnected_at' => now(),
                    'connection_status_id' => ConnectionStatus::where('code', 'DISCONNECTED')->firstOrFail()->id,
                ]);
                $terminal = $terminals->firstWhere('id', $own->room_terminal_id);
                $moved = true;
            } else {
                $taken = $active->pluck('room_terminal_id');
                $free = $terminals->reject(fn ($terminal) => $taken->contains($terminal->id));

                if ($free->isEmpty()) {
                    return $this->failure("This room's panel is complete ({$terminals->count()}/{$terminals->count()}).");
                }

                $terminal = ($preferred && $free->contains('id', $preferred->id))
                    ? $free->firstWhere('id', $preferred->id)
                    : $free->first();
            }

            $connection = TerminalConnection::create([
                'room_terminal_id' => $terminal->id,
                'panelist_user_id' => $panelist->id,
                'connected_at' => now(),
                'connection_status_id' => ConnectionStatus::where('code', 'CONNECTED')->firstOrFail()->id,
                'authenticated_via' => $authenticatedVia,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? substr($userAgent, 0, 250) : null,
            ]);

            return [
                'ok' => true,
                'connection' => $connection,
                'classification' => $this->classifyForRoom($roomSession, $panelist),
                'moved' => $moved,
            ];
        });
    }

    /**
     * Seats taken / total — drives "Panel complete" on the room screen and
     * the room list.
     */
    public function seatSummary(RoomSession $roomSession): array
    {
        $terminalIds = RoomTerminal::where('room_session_id', $roomSession->id)
            ->where('is_enabled', true)
            ->pluck('id');

        $taken = TerminalConnection::whereIn('room_terminal_id', $terminalIds)
            ->whereNull('disconnected_at')
            ->count();

        return [
            'taken' => $taken,
            'total' => $terminalIds->count(),
            'full' => $terminalIds->isNotEmpty() && $taken >= $terminalIds->count(),
        ];
    }

    /**
     * First seat with nobody on it — the seat a new QR token is issued for.
     */
    public function firstFreeTerminal(RoomSession $roomSession): ?RoomTerminal
    {
        $taken = TerminalConnection::whereHas('roomTerminal', fn ($query) => $query->where('room_session_id', $roomSession->id))
            ->whereNull('disconnected_at')
            ->pluck('room_terminal_id');

        return RoomTerminal::where('room_session_id', $roomSession->id)
            ->where('is_enabled', true)
            ->whereNotIn('id', $taken)
            ->orderBy('terminal_number')
            ->first();
    }

    /**
     * Any assigned/backup panelist for *any* attempt ever scheduled in this
     * room — deliberately broad, unrelated to classifyForRoom() below. Used
     * for canEvaluate gating and PresentationControlService's staffing checks.
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
     * User-directed 2026-09-07: flow control follows whichever panelist the
     * Admin designated Lead/Chair (attempt_panel_assignments.is_lead),
     * wherever that panelist logs in. Room-wide, same broad scope as
     * eligiblePanelistIds() above — a panel is normally assigned identically
     * across a whole room's queue for the day.
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
     * Narrow, room-current-moment classification — drives the room screen's
     * post-login UX (App\Services\PanelSubstitutionService). Scoped to the
     * room's immediate target attempt (current ?? called ?? next).
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
