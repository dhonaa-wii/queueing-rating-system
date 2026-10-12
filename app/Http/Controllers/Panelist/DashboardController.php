<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptSchedule;
use App\Models\CategoryAnnouncement;
use App\Models\EvaluationSubmission;
use App\Models\PresentationAttempt;
use App\Models\PresentationDateRoom;
use App\Models\ResearchGroup;
use App\Services\AdviseeService;
use App\Services\RoomQueuePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Panelist Dashboard — a glance view across every category: the panelist's
 * own seats, the groups they advise (2026-10-11), and the rooms they sit in
 * while a day is actually running. Everything else lives on My Assignments /
 * My Advisees, which the panels link to.
 */
class DashboardController extends Controller
{
    private const LIST_SIZE = 5;

    public function index(Request $request, RoomQueuePreviewService $roomQueuePreview, AdviseeService $adviseeService)
    {
        $user = $request->user();

        $assignments = AttemptPanelAssignment::where('panelist_user_id', $user->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with([
                'assignmentKind',
                'assignmentStatus',
                'presentationAttempt.presentationStatus',
                'presentationAttempt.researchGroup.category.academicYear',
                'presentationAttempt.researchGroup.category.semester',
                'presentationAttempt.researchGroup.students',
                'presentationAttempt.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempt.attemptSchedule.presentationDateRoom.roomSessions.roomSessionStatus',
                'presentationAttempt.attemptSchedule.queueEntry',
            ])
            ->get()
            ->filter(fn (AttemptPanelAssignment $assignment) => $assignment->presentationAttempt?->researchGroup?->category !== null)
            ->values();

        $adviseeGroups = $adviseeService->groupsFor($user);

        // Every category the panelist sits on a panel or advises a group in,
        // shown together (2026-10-12: no longer one category at a time). A
        // category where they only advise still counts.
        $categories = $assignments->pluck('presentationAttempt.researchGroup.category')
            ->merge($adviseeGroups->pluck('category'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $seats = $assignments;
        $advisees = $adviseeGroups;

        // Refresh each day's derived status once, like every other page that
        // shows it, then read live expected times from the same forecast the
        // room device and Event Control use.
        $rooms = $seats->map(fn ($a) => $a->presentationAttempt->attemptSchedule?->presentationDateRoom)
            ->merge($advisees->flatMap(fn ($g) => $g->presentationAttempts->map(fn ($attempt) => $attempt->attemptSchedule?->presentationDateRoom)))
            ->filter()
            ->unique('id')
            ->values();
        $rooms->pluck('presentationDate')->unique('id')->each(function ($date) {
            $date->refreshStatus();
            $date->load('eventDateStatus');
        });

        $previews = $rooms->mapWithKeys(fn (PresentationDateRoom $room) => [$room->id => $roomQueuePreview->forRoom($room)]);
        // Keyed only after flattening: flatMap renumbers integer keys.
        $expectedTimes = $previews->flatMap(fn ($preview) => $preview['schedules'])
            ->mapWithKeys(fn ($schedule) => [$schedule->id => $schedule->expected_start_at]);
        // Rows whose live expected time runs past their room's session end,
        // keyed to that session end (AttemptSchedule::markPastSessionEnd()).
        $pastSessionEnd = $previews->flatMap(fn ($preview) => $preview['schedules'])
            ->filter(fn ($schedule) => $schedule->isPastSessionEnd())
            ->mapWithKeys(fn ($schedule) => [$schedule->id => $schedule->session_end_at]);

        // ---- Seats ----
        $openSeats = $seats->reject(fn ($a) => $a->presentationAttempt->presentationStatus->is_terminal);
        $presentedSeats = $seats->filter(fn ($a) => $a->presentationAttempt->presentationStatus->is_terminal)
            ->reject(fn ($a) => $a->assignmentKind?->code === 'BACKUP_PANELIST');

        $evaluatedCount = EvaluationSubmission::where('panelist_user_id', $user->id)
            ->whereHas('presentationAttempt.researchGroup', fn ($q) => $q->whereIn('category_id', $categories->pluck('id')))
            ->whereHas('submissionStatus', fn ($q) => $q->whereIn('code', ['SUBMITTED', 'FINALIZED']))
            ->count();

        $upcoming = $openSeats
            ->map(fn (AttemptPanelAssignment $a) => $this->row($a->presentationAttempt, $expectedTimes, $pastSessionEnd) + ['assignment' => $a])
            ->sortBy('sort')
            ->values();

        $nextUp = $upcoming->first(fn ($row) => $row['at'] !== null);

        // ---- Advisees — one row per group, on its latest attempt ----
        $adviseeRows = $advisees->map(function (ResearchGroup $group) use ($expectedTimes, $pastSessionEnd) {
            $attempt = $group->presentationAttempts->last();

            return $attempt
                ? $this->row($attempt, $expectedTimes, $pastSessionEnd) + ['group' => $group]
                : ['group' => $group, 'attempt' => null, 'state' => 'unqueued', 'at' => null, 'room' => null, 'sort' => '2', 'sessionEnd' => null];
        });

        $adviseePresented = $adviseeRows->where('state', 'done');
        $adviseeRows = $adviseeRows->reject(fn ($row) => $row['state'] === 'done')->sortBy('sort')
            ->concat($adviseePresented->sortByDesc(fn ($row) => $row['attempt']->completed_at?->format('Y-m-d H:i:s') ?? ''))
            ->values();

        // ---- Rooms running right now ----
        $liveRooms = $seats->map(fn ($a) => $a->presentationAttempt->attemptSchedule?->presentationDateRoom)
            ->filter(fn ($room) => $room?->presentationDate?->isRunning())
            ->unique('id')
            ->sortBy('room_name')
            ->map(fn (PresentationDateRoom $room) => [
                'room' => $room,
                'session' => $room->roomSessions->sortByDesc('id')->first(),
                'preview' => $previews[$room->id],
            ])
            ->values();

        $announcements = CategoryAnnouncement::whereIn('category_id', $categories->pluck('id'))
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->with('category')
            ->latest('starts_at')
            ->get();

        return view('panelist.dashboard', [
            'categories' => $categories,
            'stats' => [
                'seats' => $seats->count(),
                'toPresent' => $openSeats->count(),
                'evaluated' => $evaluatedCount,
                'presented' => $presentedSeats->count(),
                'advisees' => $advisees->count(),
                'adviseesPresented' => $adviseePresented->count(),
            ],
            'nextUp' => $nextUp,
            'upcoming' => $upcoming->take(self::LIST_SIZE),
            'upcomingMore' => max($upcoming->count() - self::LIST_SIZE, 0),
            'adviseeRows' => $adviseeRows->take(self::LIST_SIZE),
            'adviseeMore' => max($adviseeRows->count() - self::LIST_SIZE, 0),
            'liveRooms' => $liveRooms,
            'announcements' => $announcements,
        ]);
    }

    /**
     * Where one attempt stands, for a dashboard row: done, deferred, waiting
     * for a schedule, or scheduled at its live expected time (the plan until
     * its day starts — RoomQueuePreviewService decides that).
     */
    private function row(PresentationAttempt $attempt, Collection $expectedTimes, Collection $pastSessionEnd): array
    {
        $schedule = $attempt->attemptSchedule;
        $room = $schedule?->presentationDateRoom;

        $state = match (true) {
            (bool) $attempt->presentationStatus?->is_terminal => 'done',
            $schedule?->queueEntry?->removed_at !== null => 'deferred',
            ! $schedule || $schedule->isAwaitingReschedule($attempt) => 'awaiting',
            default => 'scheduled',
        };

        $at = $state === 'scheduled'
            ? ($expectedTimes->get($schedule->id) ?? $schedule->adjusted_expected_at ?? $schedule->planned_start_at)
            : null;

        // Scheduled rows first by time, then deferred, then waiting.
        $sort = match ($state) {
            'scheduled' => '0 '.($at?->format('Y-m-d H:i:s') ?? '9999'),
            'deferred' => '1',
            default => '2',
        };

        // Set when its live expected time runs past its room's session end.
        $sessionEnd = $state === 'scheduled' ? $pastSessionEnd->get($schedule->id) : null;

        return ['attempt' => $attempt, 'state' => $state, 'at' => $at, 'room' => $room, 'sort' => $sort, 'sessionEnd' => $sessionEnd];
    }
}
