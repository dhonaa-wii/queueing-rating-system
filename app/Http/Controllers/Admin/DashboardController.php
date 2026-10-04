<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\EvaluationSubmission;
use App\Models\Notification;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationOutcome;
use App\Models\PresentationRun;
use App\Models\QueueEntry;
use App\Models\ResearchGroup;
use App\Models\RoomSession;
use App\Models\Semester;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\FocusLink;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /** Days of history behind the activity chart; the view's range toggle slices this. */
    private const ACTIVITY_DAYS = 30;

    /** Queue pipeline order — a group moves left to right through these. */
    private const PIPELINE_ORDER = [
        'SCHEDULED', 'QUEUED', 'READY_NEXT', 'CALLED', 'ONGOING', 'PAUSED',
        'DEFERRED', 'COMPLETED', 'ABSENT', 'CANCELLED', 'RESCHEDULED',
    ];

    /** Category rows sort by how much the admin can still act on them. */
    private const CATEGORY_STATUS_PRIORITY = [
        'ACTIVE' => 0, 'REGISTRATION_OPEN' => 1, 'UPCOMING' => 2,
        'SETUP_INCOMPLETE' => 3, 'DRAFT' => 4, 'COMPLETED' => 5,
    ];

    private const SCORE_BINS = [
        ['label' => 'Below 60', 'min' => 0, 'max' => 60],
        ['label' => '60–69', 'min' => 60, 'max' => 70],
        ['label' => '70–79', 'min' => 70, 'max' => 80],
        ['label' => '80–89', 'min' => 80, 'max' => 90],
        ['label' => '90–100', 'min' => 90, 'max' => 100.0001],
    ];

    public function index(NotificationService $notifications)
    {
        $user = auth()->user();
        $now = now();
        $today = $now->copy()->startOfDay();

        // ---- Categories (status recomputed live — no scheduler exists in
        // this app, so every page load refreshes derived status). ----
        $categories = PresentationCategory::forAdminCollege()->with(['categoryStatus', 'presentationMode', 'presentationDates'])
            ->withCount('researchGroups')
            ->get();
        $categories->each->refreshStatus();
        $categories->load('categoryStatus');

        $archivedCategoryIds = $categories->where('categoryStatus.code', 'ARCHIVED')->pluck('id');
        $workingCategories = $categories->where('categoryStatus.code', '!=', 'ARCHIVED')->values();

        // ---- Attempts across every non-archived category ----
        $attempts = PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->whereIn('category_id', $workingCategories->pluck('id')))
            ->with(['presentationStatus', 'finalOutcome', 'researchGroup:id,category_id'])
            ->get();
        $completedAttempts = $attempts->filter(fn ($a) => $a->presentationStatus?->code === 'COMPLETED');

        // ---- Groups ----
        $groupsQuery = ResearchGroup::whereIn('category_id', $workingCategories->pluck('id'));
        $totalGroups = (clone $groupsQuery)->count();

        $activityStart = $today->copy()->subDays(self::ACTIVITY_DAYS - 1);
        $registrationsByDay = (clone $groupsQuery)
            ->where('registered_at', '>=', $activityStart)
            ->selectRaw('DATE(registered_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');
        $completionsByDay = $completedAttempts
            ->filter(fn ($a) => $a->completed_at && $a->completed_at->gte($activityStart))
            ->countBy(fn ($a) => $a->completed_at->format('Y-m-d'));

        $activity = collect(range(0, self::ACTIVITY_DAYS - 1))->map(function (int $offset) use ($activityStart, $registrationsByDay, $completionsByDay) {
            $date = $activityStart->copy()->addDays($offset);
            $key = $date->format('Y-m-d');

            return [
                'date' => $key,
                'label' => $date->format('M j'),
                'registered' => (int) ($registrationsByDay[$key] ?? 0),
                'completed' => (int) ($completionsByDay[$key] ?? 0),
            ];
        });

        $lastWeek = $activity->slice(-7);
        $previousWeek = $activity->slice(-14, 7);

        // ---- Panelists ----
        $liveAssignment = fn ($q) => $q->whereHas('assignmentStatus', fn ($s) => $s->whereNotIn('code', ['REPLACED', 'WITHDRAWN']));

        $panelists = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->whereHas('panelistProfile', fn ($q) => $q->where('college_id', \App\Support\AdminCollege::id() ?? 0))
            ->with(['profile', 'accountStatus'])
            ->withCount([
                'attemptPanelAssignments as upcoming_count' => function ($q) use ($liveAssignment) {
                    $liveAssignment($q);
                    $q->whereHas('presentationAttempt.presentationStatus', fn ($s) => $s->where('is_terminal', false));
                },
                'attemptPanelAssignments as completed_count' => function ($q) use ($liveAssignment) {
                    $liveAssignment($q);
                    $q->whereHas('presentationAttempt.presentationStatus', fn ($s) => $s->where('code', 'COMPLETED'));
                },
            ])
            ->get();

        $panelistWorkload = $panelists
            ->filter(fn (User $p) => $p->upcoming_count + $p->completed_count > 0)
            ->sortByDesc(fn (User $p) => $p->upcoming_count + $p->completed_count)
            ->take(6)
            ->map(fn (User $p) => [
                'name' => $this->displayName($p),
                'initials' => $this->initials($p),
                'upcoming' => (int) $p->upcoming_count,
                'completed' => (int) $p->completed_count,
            ])
            ->values();

        // ---- Evaluation scores (one average per attempt, from the panel's
        // submitted sheets) ----
        $submissions = EvaluationSubmission::whereNotNull('weighted_total_score')
            ->whereHas('submissionStatus', fn ($q) => $q->whereIn('code', ['SUBMITTED', 'FINALIZED']))
            ->whereIn('presentation_attempt_id', $attempts->pluck('id'))
            ->get(['presentation_attempt_id', 'weighted_total_score']);

        $attemptScores = $submissions->groupBy('presentation_attempt_id')
            ->map(fn ($rows) => (float) $rows->avg('weighted_total_score'));

        $scoreDistribution = collect(self::SCORE_BINS)->map(fn (array $bin) => [
            'label' => $bin['label'],
            'count' => $attemptScores->filter(fn ($s) => $s >= $bin['min'] && $s < $bin['max'])->count(),
        ]);

        // ---- Presentation runs ----
        $runs = PresentationRun::whereNotNull('completed_at')
            ->whereNotNull('actual_duration_seconds')
            ->whereIn('presentation_attempt_id', $completedAttempts->pluck('id'))
            ->get(['actual_duration_seconds', 'configured_duration_seconds']);

        // ---- Outcomes (fixed catalog order, zeros kept so the legend is
        // stable). Completing a presentation always records the Lead's
        // verdict, so only the catalog outcomes are charted. ----
        $outcomeCounts = $completedAttempts->whereNotNull('final_outcome_id')->countBy('final_outcome_id');
        $outcomes = PresentationOutcome::where('is_active', true)->orderBy('id')->get()
            ->map(fn (PresentationOutcome $o) => [
                'code' => $o->code,
                'label' => $o->name,
                'count' => (int) ($outcomeCounts[$o->id] ?? 0),
            ]);

        // ---- Queue pipeline ----
        $statusCounts = $attempts->countBy(fn ($a) => $a->presentationStatus?->code);
        $statusNames = $attempts->pluck('presentationStatus')->filter()->keyBy('code');
        $pipeline = collect(self::PIPELINE_ORDER)
            ->filter(fn ($code) => ($statusCounts[$code] ?? 0) > 0)
            ->map(fn ($code) => [
                'code' => $code,
                'label' => $statusNames[$code]->name ?? $code,
                'count' => (int) $statusCounts[$code],
            ])
            ->values();

        // ---- Category progress table ----
        $attemptsByCategory = $attempts->groupBy(fn ($a) => $a->researchGroup?->category_id);
        $categoryRows = $workingCategories
            ->map(function (PresentationCategory $category) use ($attemptsByCategory, $today) {
                $categoryAttempts = $attemptsByCategory->get($category->id, collect());
                $dates = $category->presentationDates->sortBy('presentation_date');
                $nextDate = $dates->first(fn ($d) => $d->completed_at === null && $d->presentation_date->gte($today));

                return [
                    'model' => $category,
                    'code' => $category->categoryStatus?->code,
                    'status' => $category->statusDisplayName(),
                    'mode' => $category->presentationMode?->name,
                    'groups' => (int) $category->research_groups_count,
                    'attempts' => $categoryAttempts->count(),
                    'completed' => $categoryAttempts->filter(fn ($a) => $a->presentationStatus?->is_terminal)->count(),
                    'nextDate' => $nextDate?->presentation_date,
                    'days' => $dates->count(),
                ];
            })
            ->sortBy(fn ($row) => sprintf('%02d-%s', self::CATEGORY_STATUS_PRIORITY[$row['code']] ?? 9, strtolower($row['model']->name)))
            ->values();

        // ---- Rooms open right now ----
        $liveSessions = RoomSession::whereNull('ended_at')
            ->whereHas('presentationDateRoom.presentationDate', fn ($q) => $q->whereNull('completed_at')->whereIn('category_id', $categories->pluck('id')))
            ->with([
                'roomSessionStatus',
                'presentationDateRoom.presentationDate.category',
                'presentationDateRoom.attemptSchedules.presentationAttempt.presentationStatus',
                'currentAttempt.researchGroup',
                'currentAttempt.presentationStatus',
                'currentAttempt.presentationRun',
            ])
            ->get()
            ->map(function (RoomSession $session) {
                $room = $session->presentationDateRoom;
                $roomAttempts = $room->attemptSchedules->pluck('presentationAttempt')->filter();
                $current = $session->currentAttempt;

                return [
                    'room' => $room->room_name,
                    'category' => $room->presentationDate->category,
                    'dateId' => $room->presentation_date_id,
                    'status' => $session->roomSessionStatus?->name,
                    'statusCode' => $session->roomSessionStatus?->code,
                    'group' => $current?->researchGroup,
                    'groupStatus' => $current?->presentationStatus?->name,
                    'groupStatusCode' => $current?->presentationStatus?->code,
                    'startedAt' => $current?->presentationRun?->started_at,
                    'done' => $roomAttempts->filter(fn ($a) => $a->presentationStatus?->is_terminal)->count(),
                    'total' => $roomAttempts->count(),
                ];
            });

        // ---- Upcoming presentation days ----
        $upcomingDates = PresentationDate::whereNull('completed_at')
            ->whereDate('presentation_date', '>=', $today)
            ->whereIn('category_id', $workingCategories->pluck('id'))
            ->with(['category', 'eventDateStatus', 'presentationDateRooms.roomUseStatus'])
            ->with(['presentationDateRooms' => fn ($q) => $q->withCount('attemptSchedules')])
            ->orderBy('presentation_date')
            ->orderBy('event_start_time')
            ->limit(5)
            ->get();
        $upcomingDates->each->refreshStatus();

        // ---- Needs attention ----
        $pendingSubstitutions = PanelSubstitutionRequest::whereHas('status', fn ($q) => $q->where('code', 'PENDING'))
            ->whereHas('presentationAttempt.researchGroup', fn ($q) => $q->whereIn('category_id', $categories->pluck('id')))
            ->with(['originalPanelist.profile', 'requestedSubstitute.profile', 'presentationAttempt.researchGroup.category'])
            ->latest('id')
            ->get();
        $otherNotifications = $this->recentOtherNotifications($user);
        // Substitution-request notifications are excluded: each one is already
        // represented by its pending request row, so counting both doubles it.
        $needsAttentionCount = $pendingSubstitutions->count() + Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('notification_type', '!=', 'PANEL_SUBSTITUTION_REQUESTED')
            ->count();

        $activePanelists = $panelists->filter(fn (User $p) => (bool) $p->accountStatus?->is_login_allowed)->count();

        return view('admin.dashboard', [
            'firstName' => $user->profile->first_name ?? $user->username,
            'greeting' => match (true) {
                $now->hour < 12 => 'Good morning',
                $now->hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'academicYear' => AcademicYear::where('is_active', true)->first(),
            'semester' => Semester::where('is_active', true)->first(),
            'college' => $user->administratorProfile?->college,

            'stats' => [
                'groups' => [
                    'value' => $totalGroups,
                    'week' => $lastWeek->sum('registered'),
                    'previousWeek' => $previousWeek->sum('registered'),
                    'spark' => $activity->slice(-14)->pluck('registered')->values(),
                ],
                'presentations' => [
                    'value' => $completedAttempts->count(),
                    'total' => $attempts->count(),
                    'today' => $completedAttempts->filter(fn ($a) => $a->completed_at?->isSameDay($now))->count(),
                    'spark' => $activity->slice(-14)->pluck('completed')->values(),
                ],
                'rooms' => [
                    'value' => $liveSessions->count(),
                    'presenting' => $liveSessions->filter(fn ($s) => in_array($s['groupStatusCode'], ['ONGOING', 'PAUSED'], true))->count(),
                ],
                'panelists' => [
                    'value' => $activePanelists,
                    'total' => $panelists->count(),
                    'assigned' => $panelists->filter(fn (User $p) => $p->upcoming_count > 0)->count(),
                ],
                'score' => [
                    'value' => $attemptScores->isNotEmpty() ? round($attemptScores->avg(), 1) : null,
                    'evaluations' => $submissions->count(),
                ],
                'duration' => [
                    'value' => $runs->isNotEmpty() ? (int) round($runs->avg('actual_duration_seconds')) : null,
                    'planned' => $runs->isNotEmpty() ? (int) round($runs->avg('configured_duration_seconds')) : null,
                ],
            ],

            'activity' => $activity,
            'outcomes' => $outcomes,
            'pipeline' => $pipeline,
            'scoreDistribution' => $scoreDistribution,
            'scoredAttempts' => $attemptScores->count(),
            'panelistWorkload' => $panelistWorkload,
            'categoryRows' => $categoryRows,
            'liveSessions' => $liveSessions,
            'upcomingDates' => $upcomingDates,
            'pendingSubstitutions' => $pendingSubstitutions,
            'otherNotifications' => $otherNotifications,
            'needsAttentionCount' => $needsAttentionCount,
        ]);
    }

    private function displayName(?User $user): string
    {
        if (! $user) {
            return 'Someone';
        }

        return trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')) ?: $user->username;
    }

    private function initials(User $user): string
    {
        $initials = mb_substr($user->profile->first_name ?? '', 0, 1) . mb_substr($user->profile->last_name ?? '', 0, 1);

        return mb_strtoupper($initials ?: mb_substr($user->username, 0, 2));
    }

    /**
     * Unread, non-substitution notifications, each linked to the exact place
     * it is resolved (substitution requests are listed separately, with their
     * own actions).
     */
    private function recentOtherNotifications(User $user): Collection
    {
        $items = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('notification_type', '!=', 'PANEL_SUBSTITUTION_REQUESTED')
            ->latest('id')
            ->limit(6)
            ->get();

        $datesById = PresentationDate::whereIn('id', $items->where('related_type', PresentationDate::class)->pluck('related_id'))
            ->with(['category', 'presentationDateRooms.roomSessions.currentAttempt.presentationStatus'])
            ->get()->keyBy('id');
        $categoriesById = PresentationCategory::whereIn('id', $items->where('related_type', PresentationCategory::class)->pluck('related_id'))
            ->get()->keyBy('id');
        $attemptsById = PresentationAttempt::whereIn('id', $items->where('related_type', PresentationAttempt::class)->pluck('related_id'))
            ->with('researchGroup.category')->get()->keyBy('id');
        $entriesById = QueueEntry::whereIn('id', $items->where('related_type', QueueEntry::class)->pluck('related_id'))
            ->with('attemptSchedule.presentationAttempt.researchGroup.category')
            ->get()->keyBy('id');

        return $items->map(function (Notification $item) use ($datesById, $categoriesById, $attemptsById, $entriesById) {
            $link = null;

            if ($item->related_type === PresentationDate::class) {
                $date = $datesById->get($item->related_id);
                if ($date?->category && $item->notification_type === 'EVENT_AUTO_END_BLOCKED') {
                    // The room whose presentation is still in progress.
                    $stuckRoom = $date->presentationDateRooms->first(fn ($room) => $room->roomSessions->contains(
                        fn ($session) => $session->ended_at === null
                            && in_array($session->currentAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)
                    ));
                    $link = FocusLink::to(
                        route('admin.live-monitoring.show', $date->category) . '?date=' . $date->id,
                        $stuckRoom ? ['room-' . $stuckRoom->id] : []
                    );
                } elseif ($date?->category) {
                    $link = FocusLink::to(route('admin.categories.show', $date->category), ['date-span', 'date-' . $date->id], '#tab-schedules');
                }
            } elseif ($item->related_type === PresentationCategory::class) {
                $category = $categoriesById->get($item->related_id);
                if ($category) {
                    $link = FocusLink::to(route('admin.categories.show', $category), ['date-span'], '#tab-schedules');
                }
            } elseif ($item->related_type === PresentationAttempt::class) {
                $attempt = $attemptsById->get($item->related_id);
                if ($attempt?->researchGroup?->category) {
                    $link = FocusLink::to(route('admin.panel-assignments.show', $attempt->researchGroup->category), ['attempt-' . $attempt->id]);
                }
            } elseif ($item->related_type === QueueEntry::class) {
                $attempt = $entriesById->get($item->related_id)?->attemptSchedule?->presentationAttempt;
                if ($attempt?->researchGroup?->category) {
                    $link = FocusLink::to(route('admin.panel-assignments.show', $attempt->researchGroup->category), ['attempt-' . $attempt->id]);
                }
            }

            return [
                'type' => $item->notification_type,
                'title' => $item->title,
                'message' => $item->message,
                'createdAt' => $item->created_at?->diffForHumans(null, true, true),
                'link' => $link,
            ];
        })->values();
    }
}
