<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicatePaymentReferenceException;
use App\Http\Controllers\Controller;
use App\Models\AdjustmentReason;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptSchedule;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDateRoom;
use App\Models\QueueEntry;
use App\Models\ResearchGroup;
use App\Models\User;
use App\Services\CapacityAnalysisService;
use App\Services\NotificationService;
use App\Services\PanelAssignmentService;
use App\Services\PanelistConflictService;
use App\Services\PaymentVerificationService;
use App\Services\PresentationAttemptAdminActionService;
use App\Services\QueueAdjustmentService;
use App\Services\QueueGenerationService;
use App\Services\ReDefenseService;
use App\Services\ResearchGroupRegistrationService;
use App\Services\RoomQueuePreviewService;
use App\Support\DefaultCategoryPicker;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PanelAssignmentController extends Controller
{
    /**
     * Group & Panel Assignment opens straight on a category rather than on a
     * grid of category cards (user-directed 2026-09-17) — DefaultCategoryPicker
     * gives the same answer the other two category-scoped modules give, so an
     * admin moving between them stays on the category they were working in.
     * The picker moved onto the workspace itself, as a dropdown. This route
     * only renders something of its own when no category has a queue yet.
     */
    public function index()
    {
        $categories = $this->queuedCategories();
        $category = DefaultCategoryPicker::pick($categories);

        if (! $category) {
            return view('admin.panel-assignments.index');
        }

        return redirect()->route('admin.panel-assignments.show', $category);
    }

    /**
     * Categories this module can open: only those with a generated queue —
     * nothing to assign panels to otherwise (functional-spec §6.6 operates on
     * presentation_attempts, which only exist post-generation). Newest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PresentationCategory>
     */
    private function queuedCategories()
    {
        // A COMPLETED (or ARCHIVED) category takes no new groups any more
        // (see ResearchGroupRegistrationService's and this controller's own
        // storeGroup() guard) — user-directed 2026-09-17: it drops out of
        // this picker and stays reachable through Reports and Presentation
        // Setup's category list instead. Same two codes Event Control's own
        // openCategories() excludes, kept consistent with it.
        return PresentationCategory::whereHas('researchGroups.presentationAttempts')
            ->whereHas('categoryStatus', fn ($query) => $query->whereNotIn('code', ['COMPLETED', 'ARCHIVED']))
            ->with(['academicYear', 'semester', 'presentationDates.eventDateStatus'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function show(Request $request, PresentationCategory $category, CapacityAnalysisService $capacityService, PanelistConflictService $conflictService, PanelAssignmentService $panelService, ReDefenseService $reDefenseService, RoomQueuePreviewService $roomQueuePreview, PaymentVerificationService $paymentService)
    {
        $category->load(['academicYear', 'semester', 'college', 'categoryStatus', 'presentationMode', 'categoryPaymentSetting', 'categoryPaymentTypes']);

        $rooms = PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('roomUseStatus', fn ($q) => $q->where('code', '!=', 'REMOVED'))
            ->with('presentationDate.eventDateStatus', 'roomUseStatus')
            ->get()
            ->sortBy(fn ($room) => $room->presentationDate->presentation_date->format('Y-m-d') . '-' . $room->room_name)
            ->values();

        // Every room the category has ever configured still has to render in
        // the roster below (groups stay parked in a finished day's room until
        // they're moved), but a *transfer target* only makes sense on a day
        // that can still run — ongoing or upcoming, matching the same
        // isOpenForScheduling() rule QueueAdjustmentService::transferRoom()
        // re-checks server-side and the room registry uses when placing rooms.
        $transferRooms = $rooms->filter(fn (PresentationDateRoom $room) => $room->presentationDate->isOpenForScheduling())->values();

        $attemptsQuery = PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->with([
                'researchGroup.students' => fn ($q) => $q->orderByDesc('is_leader')->orderBy('id'),
                'researchGroup.proposedTitles' => fn ($q) => $q->orderBy('sort_order'),
                'presentationStatus',
                // The roster and the position pickers both ask whether a row
                // is a pending re-defense (pinned to the end of its room).
                'attemptType',
                'attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'attemptSchedule.queueEntry.queueAdjustments' => fn ($q) => $q->orderByDesc('adjusted_at'),
                'attemptSchedule.queueEntry.queueAdjustments.adjustmentType',
                'attemptSchedule.queueEntry.queueAdjustments.reason',
                'attemptPanelAssignments' => fn ($q) => $q->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
                'attemptPanelAssignments.panelist.profile',
                'attemptPanelAssignments.assignmentKind',
                'attemptPanelAssignments.assignmentStatus',
                'attemptSchedule.presentationDateRoom',
                'paymentVerifications.paymentStatus',
                'evaluationSubmissions.submissionStatus',
            ]);

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $attemptsQuery->whereHas('researchGroup', function ($q) use ($search) {
                $q->whereHas('students', fn ($sq) => $sq->nameMatches($search))
                    ->orWhere('current_project_title', 'like', "%{$search}%")
                    ->orWhere('group_reference', 'like', "%{$search}%")
                    ->orWhereHas('proposedTitles', fn ($pq) => $pq->where('title_text', 'like', "%{$search}%"));
            });
        }

        $attempts = $attemptsQuery->get()->filter(fn ($attempt) => $attempt->attemptSchedule !== null);

        $roomGroups = $rooms->map(function (PresentationDateRoom $room) use ($attempts) {
            $roomAttempts = $attempts->filter(fn ($attempt) => $attempt->attemptSchedule->presentation_date_room_id === $room->id);

            $active = $roomAttempts->filter(fn ($attempt) => $attempt->attemptSchedule->queueEntry?->removed_at === null)
                ->sortBy(fn ($attempt) => $attempt->attemptSchedule->queueEntry->queue_number)
                ->values();

            $deferred = $roomAttempts->filter(fn ($attempt) => $attempt->attemptSchedule->queueEntry && $attempt->attemptSchedule->queueEntry->removed_at !== null)
                ->sortByDesc(fn ($attempt) => $attempt->attemptSchedule->queueEntry->removed_at)
                ->values();

            return [
                'room' => $room,
                'active' => $active,
                'deferred' => $deferred,
            ];
        })->filter(fn ($group) => $group['active']->isNotEmpty() || $group['deferred']->isNotEmpty())->values();

        $deferredAttention = $roomGroups->flatMap(function (array $group) use ($category, $paymentService) {
            return $group['deferred']->map(function (PresentationAttempt $attempt) use ($group, $category, $paymentService) {
                $queueEntry = $attempt->attemptSchedule->queueEntry;
                $lastDefer = $queueEntry->queueAdjustments->first(fn ($adjustment) => $adjustment->adjustmentType?->code === 'DEFER');
                $paymentSummary = $paymentService->summaryFor($category, $attempt);

                return [
                    'type' => 'deferred',
                    'attentionAt' => $queueEntry->removed_at,
                    'attempt' => $attempt,
                    'room' => $group['room'],
                    'deferredAt' => $queueEntry->removed_at,
                    'reason' => $lastDefer?->reason,
                    'remarks' => $lastDefer?->remarks,
                    'isPaymentConcern' => $lastDefer?->reason?->code === 'PAYMENT_VERIFICATION_CONCERN',
                    'paymentSummary' => $paymentSummary,
                    'paymentResolved' => $paymentSummary['allSatisfied'],
                ];
            });
        });

        // Pending terminal-login replacement requests for this category
        // (2026-08-22, App\Services\PanelSubstitutionService) — surfaced
        // here too, not just the topbar bell/Dashboard, so an Admin already
        // in this category's workspace sees them without switching context.
        $substitutionAttention = PanelSubstitutionRequest::whereHas(
            'presentationAttempt.researchGroup',
            fn ($q) => $q->where('category_id', $category->id)
        )
            ->whereHas('status', fn ($q) => $q->where('code', 'PENDING'))
            ->with(['originalPanelist.profile', 'requestedSubstitute.profile', 'presentationAttempt.researchGroup.category'])
            ->get()
            ->map(fn (PanelSubstitutionRequest $substitutionRequest) => [
                'type' => 'substitution',
                'attentionAt' => $substitutionRequest->created_at,
                'substitutionRequest' => $substitutionRequest,
            ]);

        // System-wide panelist scheduling conflicts (2026-08-25), filtered
        // down to pairs touching this category — surfaced here in addition
        // to the notification bell and Panelist Management, since an admin
        // already in this category's workspace shouldn't have to switch
        // context to see it.
        $allConflicts = $conflictService->scanAllConflicts();

        $conflictAttention = $allConflicts
            ->filter(function (array $item) use ($category) {
                return $item['attempt']->researchGroup->category_id === $category->id
                    || $item['conflict']->presentationAttempt->researchGroup->category_id === $category->id;
            })
            ->map(fn (array $item) => [
                'type' => 'conflict',
                'attentionAt' => now(),
                'attempt' => $item['attempt'],
                'otherAttempt' => $item['conflict']->presentationAttempt,
                'message' => $item['message'],
            ]);

        // The same scan, re-keyed by every attempt involved (both sides of a
        // pair, not just the one it happened to be reported from) so the
        // roster can mark each conflicting row and open that row's own
        // reassignment modal.
        $conflictsByAttempt = $conflictService->indexByAttempt($allConflicts, $category->id);

        // Groups the configured dates and rooms have no time for
        // (AttemptSchedule::hasNoSlot()) — one item for the lot, since the
        // remedy (another date or room) is the same for all of them.
        $unscheduledCount = $attempts->filter(fn (PresentationAttempt $attempt) => $attempt->attemptSchedule->hasNoSlot($attempt))->count();
        $unscheduledAttention = $unscheduledCount > 0
            ? collect([['type' => 'unscheduled', 'attentionAt' => now(), 'count' => $unscheduledCount]])
            : collect();

        $needsAttention = $deferredAttention->concat($substitutionAttention)->concat($conflictAttention)->concat($unscheduledAttention)->sortByDesc('attentionAt')->values();
        $deferredCount = $deferredAttention->count();

        // Substitute-less unavailability reports (§2.12), keyed by the
        // attempt they're reported against — drives the roster's "Needs
        // Reassignment" badge/Assign Replacement action. Reuses the same
        // PENDING rows $substitutionAttention already loaded (with
        // originalPanelist.profile) rather than a second query.
        $unavailabilityReportsByAttempt = $substitutionAttention
            ->pluck('substitutionRequest')
            ->filter(fn (PanelSubstitutionRequest $r) => $r->requested_substitute_user_id === null)
            ->groupBy('presentation_attempt_id');

        $panelists = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->whereHas('accountStatus', fn ($q) => $q->where('is_login_allowed', true))
            ->with('profile', 'panelistProfile.college')
            ->get()
            ->sortBy(fn ($user) => trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')))
            ->values();

        // How many groups each panelist is currently carrying in this
        // category, split by seat kind — surfaced next to their name in the
        // Assign Panel picker so an admin can see at a glance who's already
        // loaded up before adding another group to their plate.
        $panelistAssignmentCounts = AttemptPanelAssignment::whereHas(
            'presentationAttempt.researchGroup',
            fn ($q) => $q->where('category_id', $category->id)
        )
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with('assignmentKind')
            ->get()
            ->groupBy('panelist_user_id')
            ->map(fn ($rows) => [
                'assigned' => $rows->where('assignmentKind.code', 'ASSIGNED_PANELIST')->count(),
                'backup' => $rows->where('assignmentKind.code', 'BACKUP_PANELIST')->count(),
            ]);

        // Name matching for the Assign Panel picker's adviser muting is done
        // client-side on already-normalized strings, so the browser never
        // re-implements PanelAssignmentService's normalization (which the
        // server-side guard also uses) and the two can't drift apart.
        $panelistNameVariants = $panelists->mapWithKeys(
            fn (User $panelist) => [$panelist->id => PanelAssignmentService::panelistNameVariants($panelist)]
        );

        $adviserNameByAttempt = $attempts->mapWithKeys(
            fn (PresentationAttempt $attempt) => [
                $attempt->id => PanelAssignmentService::normalizeName((string) $attempt->researchGroup->technical_adviser_name),
            ]
        );

        // Panel-vs-room-requirement mismatch per attempt, for the roster's
        // Panel column. A room's panelist_count can be raised or lowered in
        // Presentation Setup long after its groups were assigned, so an
        // already-assigned group can silently fall out of compliance —
        // PresentationControlService::start() refuses on the same rule.
        $panelIssueByAttempt = $attempts->mapWithKeys(
            fn (PresentationAttempt $attempt) => [$attempt->id => $panelService->panelRequirementIssue($attempt)]
        );

        // Gates the Defer action (row dropdown + bulk toolbar) — user-
        // directed 2026-09-17: once one of the group's assigned panel has
        // already submitted a real evaluation, the group can no longer be
        // sent back to the queue. Read off the already eager-loaded
        // evaluationSubmissions relation (evaluationSubmissions.submissionStatus
        // above) rather than re-querying per row, matching the exact
        // SUBMITTED/FINALIZED/INVALIDATED boundary
        // EvaluationSubmissionService::hasRealSubmission() enforces server-side.
        $hasSubmittedEvaluationByAttempt = $attempts->mapWithKeys(
            fn (PresentationAttempt $attempt) => [$attempt->id => $attempt->evaluationSubmissions
                ->contains(fn ($submission) => in_array($submission->submissionStatus?->code, ['SUBMITTED', 'FINALIZED', 'INVALIDATED'], true))]
        );

        // Drives the Deferred tab's Verify Payment button/modal (broadened
        // 2026-09-20 from "only a Lead-referred payment concern" to any
        // deferred group in a payment-required category that hasn't fully
        // satisfied every configured type yet) — an Admin can encode a
        // group's payment reference number(s) before reinserting it, not
        // only after a panelist explicitly flagged a concern.
        $paymentRequired = $paymentService->isRequired($category);
        $paymentSummaryByAttempt = $paymentRequired
            ? $attempts->mapWithKeys(fn (PresentationAttempt $attempt) => [$attempt->id => $paymentService->summaryFor($category, $attempt)])
            : collect();

        $adjustmentReasons = AdjustmentReason::where('is_active', true)->orderBy('name')->get();

        $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';

        // Keyed by room id so the Reinsert modal (one per deferred attempt,
        // always the attempt's own — unchanged — room) can show today's
        // rough capacity without a separate AJAX round trip.
        $roomDayCapacities = $rooms->mapWithKeys(fn (PresentationDateRoom $room) => [$room->id => $capacityService->analyzeRoomDay($room)]);

        // Resolved per group, not once for the page: targetRoom() picks the
        // room on the last open day whose queue finishes last, and breaks a
        // tie towards the room that group presented in before — so two groups
        // can legitimately land in different rooms, and a confirmation modal
        // has to state the one that group will actually get. The page-level
        // value is kept only to decide whether the action is available at all
        // (null = no ongoing or upcoming date is configured).
        $reDefenseTargetRoom = $reDefenseService->targetRoom($category);
        $reDefenseRows = $reDefenseService->awaiting($category);

        $reDefenseTargets = $reDefenseRows->mapWithKeys(function (array $row) use ($category, $reDefenseService) {
            $room = $reDefenseService->targetRoom($category, $row['latest']);

            return [$row['latest']->id => [
                'room' => $room,
                'position' => $room ? $reDefenseService->nextPositionIn($room) : null,
            ]];
        });

        // The roster's Time column, resolved by the one service that owns the
        // running-day rule (live expected while a day is underway, the plan
        // while it is only upcoming) so the admin roster reads the same time
        // the room tablet, the panelist and the student are all seeing.
        // Keyed after flattening — flatMap collapses with array_merge, which
        // would renumber the schedule ids away.
        $expectedTimes = $rooms
            ->flatMap(fn (PresentationDateRoom $room) => $roomQueuePreview->forRoom($room)['schedules'])
            ->mapWithKeys(fn ($schedule) => [$schedule->id => $schedule->expected_start_at]);

        return view('admin.panel-assignments.show', [
            'expectedTimes' => $expectedTimes,
            'category' => $category,
            // The header's category picker, which replaced the back link — this
            // module opens straight on a category, so there is no picker page
            // to go back to.
            'pickerCategories' => DefaultCategoryPicker::withCurrent($this->queuedCategories(), $category),
            'roomGroups' => $roomGroups,
            'needsAttention' => $needsAttention,
            'deferredCount' => $deferredCount,
            'unavailabilityReportsByAttempt' => $unavailabilityReportsByAttempt,
            'reDefenseRows' => $reDefenseRows,
            'reDefenseTargetRoom' => $reDefenseTargetRoom,
            'reDefenseTargets' => $reDefenseTargets,
            'rooms' => $rooms,
            'transferRooms' => $transferRooms,
            'roomDayCapacities' => $roomDayCapacities,
            'panelists' => $panelists,
            'panelistAssignmentCounts' => $panelistAssignmentCounts,
            'panelistNameVariants' => $panelistNameVariants,
            'adviserNameByAttempt' => $adviserNameByAttempt,
            'panelIssueByAttempt' => $panelIssueByAttempt,
            'conflictsByAttempt' => $conflictsByAttempt,
            'hasSubmittedEvaluationByAttempt' => $hasSubmittedEvaluationByAttempt,
            'paymentRequired' => $paymentRequired,
            'paymentTypes' => $category->categoryPaymentTypes,
            'paymentSummaryByAttempt' => $paymentSummaryByAttempt,
            'adjustmentReasons' => $adjustmentReasons,
            'search' => $search,
            'backupSeats' => PanelAssignmentService::BACKUP_SEATS,
            'isTitleProposal' => $isTitleProposal,
            'memberSlots' => max(0, $category->maximum_members - 1),
            'requiredTitleCount' => $isTitleProposal ? ($category->required_proposed_title_count ?? 1) : 0,
        ]);
    }

    /**
     * Admin-entered group, reached from Group & Panel Assignment (user-
     * directed: same form/fields as public Screen B registration, same
     * research_groups/students/proposed_titles writes via
     * ResearchGroupRegistrationService, then the same round-robin queue
     * placement a normal registration eventually gets via
     * QueueGenerationService — see syncAfterRegistrationChange()).
     */
    public function storeGroup(Request $request, PresentationCategory $category, ResearchGroupRegistrationService $registrationService, QueueGenerationService $queueService)
    {
        if ($category->isCompleted()) {
            return back()->with('error', 'This category has ended — new groups can no longer be added to it.');
        }

        $category->load('presentationMode');
        $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';

        try {
            $data = $registrationService->validate($request, $category, $isTitleProposal);
            $registrationService->guardAgainstDuplicate($category, $data['leader'], $data['project_title'], $isTitleProposal);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $researchGroup = $registrationService->create($category, $data, $isTitleProposal);

        $syncResult = $queueService->syncAfterRegistrationChange($category->fresh(), $request->user()->id);

        return back()->with('status', "Group {$researchGroup->group_reference} added." . $this->describeSyncResult($syncResult));
    }

    /**
     * Edits an existing registration's leader/member names, sections,
     * project title (or proposed titles), research track, and technical
     * adviser — user-directed "edit the data ... through modal edit view".
     * Deliberately does not re-run queue placement: an edit to an
     * already-queued group's identity/text fields doesn't change where it
     * belongs in the queue.
     */
    public function updateGroup(Request $request, PresentationCategory $category, ResearchGroup $group, ResearchGroupRegistrationService $registrationService, PanelAssignmentService $panelService, QueueAdjustmentService $queueService)
    {
        abort_unless($group->category_id === $category->id, 404);

        // A registration is only edited while the group's schedule is
        // ongoing or upcoming, and never while it is presenting (user-
        // directed 2026-09-24) — judged on its latest attempt, the one that
        // is still to happen. A group with no schedule yet has nothing to
        // lock it.
        $latestSchedule = $group->presentationAttempts()
            ->orderByDesc('attempt_number')
            ->first()
            ?->attemptSchedule;

        if ($latestSchedule && ($blocked = $queueService->adminBlockedReason($latestSchedule))) {
            return back()->with('error', $blocked);
        }

        $category->load('presentationMode');
        $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';

        try {
            $data = $registrationService->validate($request, $category, $isTitleProposal);
            $registrationService->guardAgainstDuplicate($category, $data['leader'], $data['project_title'], $isTitleProposal, $group->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        // An adviser named here who already sits on this group's own panel
        // would be evaluating their own advisee — the Admin has to change
        // the panel first (user-directed); the edit is refused rather than
        // quietly dropping that panelist's assignment.
        $adviserConflict = $panelService->findAssignedPanelConflictForAdviser($group, $data['technical_adviser_name'] ?? null);

        if ($adviserConflict) {
            return back()->with('error', $adviserConflict);
        }

        $registrationService->update($group, $data, $isTitleProposal);

        return back()->with('status', "Group {$group->group_reference} updated.");
    }

    private function describeSyncResult(array $syncResult): string
    {
        return match ($syncResult['state'] ?? null) {
            'generated', 'regenerated' => ($syncResult['unscheduled'] ?? 0) > 0
                ? ' Added to the queue — ' . $syncResult['unscheduled'] . ' group' . ($syncResult['unscheduled'] === 1 ? '' : 's') . ' still to be scheduled: the configured dates and rooms are full.'
                : ' Added to the queue.',
            'not_eligible' => ' Not yet queued — ' . ($syncResult['reason'] ?? 'registration/queue setup isn\'t ready yet') . '.',
            'blocked' => ' Could not update the queue automatically: ' . ($syncResult['reason'] ?? 'unknown reason') . '.',
            default => '',
        };
    }

    public function assign(Request $request, PresentationCategory $category, PanelAssignmentService $service)
    {
        $validated = $request->validate([
            'attempt_ids' => ['required', 'array', 'min:1'],
            'attempt_ids.*' => ['integer'],
            'assigned_panelist_ids' => ['required', 'array', 'min:1'],
            'assigned_panelist_ids.*' => ['integer', 'exists:users,id'],
            'backup_panelist_id' => ['required', 'integer', 'exists:users,id'],
            'lead_panelist_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $result = $service->assign(
            $category,
            $validated['attempt_ids'],
            $validated['assigned_panelist_ids'],
            (int) $validated['backup_panelist_id'],
            (int) $validated['lead_panelist_id'],
            $request->user()->id
        );

        if (! $result['ok']) {
            if (isset($result['conflict'])) {
                return back()->with('assign_conflict', $result['conflict']);
            }

            return back()->with('error', $result['error']);
        }

        return back()->with('status', "Panel assigned to {$result['count']} group(s).");
    }

    /**
     * Accepts one or many schedule_ids (2026-08-25 — Group & Panel
     * Assignment's row actions moved into a per-row 3-dot dropdown plus a
     * selection-driven bulk action bar, both now sharing this one route: a
     * single-row Move submits an array of one id, the bulk bar submits
     * whatever's checked). All selected schedules must share the same room
     * — reorder() only makes sense within one room's queue — rejected with
     * a clear message rather than silently reordering a subset. Schedules
     * are re-inserted in their *original* relative queue order at
     * increasing positions starting from the submitted one, which — since
     * QueueAdjustmentService::reorder() always renumbers the whole room
     * from the current order on each call — lands them as a contiguous
     * block in that same relative order, exactly like selecting a page of
     * rows and dragging them together.
     */
    public function reorder(Request $request, PresentationCategory $category, QueueAdjustmentService $service)
    {
        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer'],
            'position' => ['required', 'integer', 'min:1'],
            'reason_id' => ['required', 'integer', 'exists:adjustment_reasons,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $schedules = $this->schedulesForCategory($category, $validated['schedule_ids']);

        if ($schedules->isEmpty()) {
            return back()->with('error', 'No matching groups were found.');
        }

        if ($schedules->pluck('presentation_date_room_id')->unique()->count() > 1) {
            return back()->with('error', 'Select groups from the same room to move them together.');
        }

        $ordered = $schedules->sortBy(fn (AttemptSchedule $s) => $s->queueEntry?->queue_number)->values();
        $position = (int) $validated['position'];
        $moved = 0;
        $conflicts = collect();
        $lastError = null;

        foreach ($ordered as $schedule) {
            $result = $service->reorder($schedule, $position, (int) $validated['reason_id'], $validated['remarks'] ?? null, $request->user()->id);

            if ($result['ok']) {
                $moved++;
                $conflicts = $conflicts->concat($result['conflicts'] ?? collect());
                $position++;
            } else {
                $lastError = $result['error'] ?? null;
            }
        }

        return $moved > 0
            ? $this->withConflictAlert(back()->with('status', $moved === 1 ? 'Group moved.' : "{$moved} groups moved."), ['conflicts' => $conflicts])
            : back()->with('error', $lastError ?? 'Could not move the selected group(s).');
    }

    /**
     * Same one-or-many pattern as reorder() above. Unlike Move, the
     * selected schedules don't need to share a room — transferring several
     * groups from different rooms into one target room is the whole point
     * — each is transferred in turn (in original queue order) and lands
     * appended to the target room's queue in that same order.
     */
    public function transfer(Request $request, PresentationCategory $category, QueueAdjustmentService $service)
    {
        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer'],
            'target_room_id' => ['required', 'integer', 'exists:presentation_date_rooms,id'],
            'reason_id' => ['required', 'integer', 'exists:adjustment_reasons,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $targetRoom = PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))
            ->findOrFail($validated['target_room_id']);

        $schedules = $this->schedulesForCategory($category, $validated['schedule_ids'])
            ->sortBy(fn (AttemptSchedule $s) => $s->queueEntry?->queue_number)
            ->values();

        $moved = 0;
        $conflicts = collect();
        $lastError = null;

        foreach ($schedules as $schedule) {
            $result = $service->transferRoom($schedule, $targetRoom, (int) $validated['reason_id'], $validated['remarks'] ?? null, $request->user()->id);

            if ($result['ok']) {
                $moved++;
                $conflicts = $conflicts->concat($result['conflicts'] ?? collect());
            } else {
                $lastError = $result['error'] ?? null;
            }
        }

        return $moved > 0
            ? $this->withConflictAlert(back()->with('status', $moved === 1 ? 'Group transferred.' : "{$moved} groups transferred."), ['conflicts' => $conflicts])
            : back()->with('error', $lastError ?? 'Could not transfer the selected group(s).');
    }

    /** Same one-or-many pattern — each entry is deferred independently. */
    public function defer(Request $request, PresentationCategory $category, QueueAdjustmentService $service)
    {
        $validated = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer'],
            'reason_id' => ['required', 'integer', 'exists:adjustment_reasons,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $entries = QueueEntry::whereIn('id', $validated['entry_ids'])
            ->whereHas('attemptSchedule.presentationAttempt.researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->get();

        $deferred = 0;
        $conflicts = collect();
        $lastError = null;

        foreach ($entries as $entry) {
            $result = $service->defer($entry, (int) $validated['reason_id'], $validated['remarks'] ?? null, $request->user()->id);

            if ($result['ok']) {
                $deferred++;
                $conflicts = $conflicts->concat($result['conflicts'] ?? collect());
            } else {
                $lastError = $result['error'] ?? null;
            }
        }

        return $deferred > 0
            ? $this->withConflictAlert(back()->with('status', $deferred === 1 ? 'Group deferred from the queue.' : "{$deferred} groups deferred from the queue."), ['conflicts' => $conflicts])
            : back()->with('error', $lastError ?? 'Could not defer the selected group(s).');
    }

    /**
     * New action (2026-08-25) — Group & Panel Assignment previously had no
     * way to remove a group's presentation from the queue outright, only
     * defer it. Reuses App\Services\PresentationAttemptAdminActionService::
     * delete() as-is (already built for Live Monitoring's own delete
     * action) — deletes the attempt/schedule/queue placement and
     * everything hanging off it, guarded against a real follow-up attempt
     * or submitted evaluations; the research group's registration itself
     * is untouched, matching how Defer already only ever affects queue
     * placement, not the registration.
     */
    public function destroySchedules(Request $request, PresentationCategory $category, PresentationAttemptAdminActionService $service, QueueAdjustmentService $queueService)
    {
        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer'],
        ]);

        $schedules = $this->schedulesForCategory($category, $validated['schedule_ids']);

        $deleted = 0;
        $lastError = null;

        foreach ($schedules as $schedule) {
            // Deletable anytime — during a running day, or while "to be
            // scheduled" on a day that is over (user-directed 2026-09-30).
            // Only a group presenting right now is refused: its run is live
            // on the room's tablets. A recorded outcome stays protected too.
            $status = $schedule->presentationAttempt?->presentationStatus;

            if (in_array($status?->code, ['ONGOING', 'PAUSED'], true)) {
                $lastError = "{$schedule->presentationAttempt->researchGroup?->group_reference} is presenting right now — complete or defer it first.";
                continue;
            }

            if ($status?->is_terminal) {
                $lastError = 'A group with a recorded outcome cannot be deleted.';
                continue;
            }

            $result = $service->delete($schedule, $request->user()->id);

            if ($result['ok']) {
                $deleted++;
            } else {
                $lastError = $result['error'] ?? null;
            }
        }

        return $deleted > 0
            ? back()->with('status', $deleted === 1 ? 'Group deleted.' : "{$deleted} groups deleted.")
            : back()->with('error', $lastError ?? 'Could not delete the selected group(s).');
    }

    private function schedulesForCategory(PresentationCategory $category, array $ids)
    {
        return AttemptSchedule::whereIn('id', $ids)
            ->whereHas('presentationAttempt.researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->with('queueEntry', 'presentationDateRoom', 'presentationAttempt.presentationStatus', 'presentationAttempt.researchGroup')
            ->get();
    }

    public function reinsert(Request $request, PresentationCategory $category, QueueEntry $entry, QueueAdjustmentService $service)
    {
        $this->ensureEntryBelongsToCategory($entry, $category);

        $validated = $request->validate([
            'position' => ['nullable', 'integer', 'min:1'],
            'reason_id' => ['required', 'integer', 'exists:adjustment_reasons,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->reinsert(
            $entry,
            (int) $validated['reason_id'],
            $validated['remarks'] ?? null,
            $request->user()->id,
            isset($validated['position']) ? (int) $validated['position'] : null
        );

        return $result['ok']
            ? $this->withConflictAlert(back()->with('status', 'Group reinserted into the queue.'), $result)
            : back()->with('error', $result['error']);
    }

    /**
     * Admin encodes a deferred group's payment reference number(s) before
     * reinserting it (user-directed 2026-09-20) — one field per configured
     * payment type, keyed by category_payment_type_id; a blank field is left
     * untouched so an already-verified type (e.g. checked live by the Lead
     * before the group was deferred for an unrelated reason) isn't
     * overwritten, and an Admin can resolve one type now and the rest later.
     */
    public function verifyPayment(Request $request, PresentationCategory $category, PresentationAttempt $attempt, PaymentVerificationService $service)
    {
        $attempt->loadMissing('researchGroup');

        abort_unless($attempt->researchGroup->category_id === $category->id, 404);

        $category->loadMissing('categoryPaymentTypes');

        $validated = $request->validate([
            'reference_numbers' => ['sometimes', 'array'],
            // "the field will hold number and letters"
            'reference_numbers.*' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $referenceNumbers = collect($validated['reference_numbers'] ?? [])
            ->mapWithKeys(fn ($value, $typeId) => [(int) $typeId => (string) $value])
            ->all();

        try {
            $resolvedCount = $service->resolveByAdmin($attempt, $category, $referenceNumbers, $validated['remarks'] ?? null, $request->user()->id);
        } catch (DuplicatePaymentReferenceException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($resolvedCount === 0) {
            return back()->with('error', 'Enter at least one reference number to mark a payment resolved.');
        }

        return back()->with('status', "Payment verified for {$attempt->researchGroup->group_reference}.");
    }

    /**
     * Resolves a panelist scheduling conflict from the roster: swaps the
     * named panelist(s) out of this one group's panel, leaving every other
     * seat alone. Reached by clicking the conflicting (red) row, which opens
     * that row's own Panel Conflict modal — user-directed 2026-09-22.
     *
     * Deliberately not the Assign Panel form: a conflict is about one person
     * being in two places at once, so re-picking the whole panel would be a
     * far bigger edit than the problem calls for.
     */
    public function replacePanelists(Request $request, PresentationCategory $category, PresentationAttempt $attempt, PanelAssignmentService $panelService, PanelistConflictService $conflictService, NotificationService $notifications)
    {
        $attempt->loadMissing('researchGroup');

        abort_unless($attempt->researchGroup->category_id === $category->id, 404);

        $validated = $request->validate([
            'replacements' => ['required', 'array'],
            'replacements.*' => ['nullable', 'integer'],
        ]);

        $replacements = collect($validated['replacements'])
            ->filter(fn ($incomingId) => $incomingId !== null && $incomingId !== '')
            ->mapWithKeys(fn ($incomingId, $outgoingId) => [(int) $outgoingId => (int) $incomingId])
            ->all();

        $result = $panelService->replacePanelists($attempt, $replacements, $request->user()->id);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        // The conflict this just cleared is also a standing notification
        // behind the Dashboard's Needs Attention card — re-synced here so it
        // clears with the action rather than waiting for the next sweep,
        // same as QueueAdjustmentService does after a move.
        $conflictService->syncNotifications($notifications);

        $summary = collect($result['replaced'])
            ->map(fn (array $pair) => "{$pair['out']} → {$pair['in']}")
            ->implode(', ');

        return back()->with('status', "Panel updated for {$attempt->researchGroup->group_reference}: {$summary}.");
    }

    /**
     * Schedules the group's next attempt after a Re-Defense verdict, from
     * the Re-Defense tab. Placement isn't chosen here — user-directed
     * 2026-09-13, a re-defense always lands on the category's last still-open
     * day, at the end of that room's queue (see ReDefenseService::
     * targetRoom()), so this action takes no room/position input at all.
     */
    public function scheduleReDefense(Request $request, PresentationCategory $category, PresentationAttempt $attempt, ReDefenseService $service)
    {
        $attempt->loadMissing('researchGroup');

        abort_unless($attempt->researchGroup->category_id === $category->id, 404);

        $result = $service->createNextAttempt($attempt, $request->user()->id);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        $room = $result['room'];
        $day = $room->presentationDate->presentation_date->format('M j, Y');

        // The real landed number, read back after renumberRoom() compacted
        // the room — the modal showed a prediction from page-render time.
        $position = $result['attempt']->attemptSchedule?->queueEntry?->queue_number;

        return back()->with('status', "{$attempt->researchGroup->group_reference} scheduled for attempt {$result['attempt_number']} — queue position {$position} in \"{$room->room_name}\" on {$day}.");
    }

    /**
     * 2026-08-25: a queue move that creates a panelist scheduling conflict
     * is no longer blocked — it saves, and the admin is alerted immediately
     * via a one-time modal (partials.schedule-conflict-modal) on top of the
     * standing notification/badge PanelistConflictService::syncNotifications()
     * already raised. Same flash-then-auto-open-modal pattern as
     * PanelistController's credential_reveal flash.
     */
    private function withConflictAlert($redirect, array $result)
    {
        $conflicts = $result['conflicts'] ?? collect();

        if ($conflicts->isEmpty()) {
            return $redirect;
        }

        return $redirect->with('conflict_alert', [
            'title' => 'Scheduling Conflict Created',
            'items' => $conflicts->pluck('message')->unique()->values()->all(),
        ]);
    }

    private function ensureEntryBelongsToCategory(QueueEntry $entry, PresentationCategory $category): void
    {
        $entry->loadMissing('attemptSchedule.presentationAttempt.researchGroup');

        abort_unless($entry->attemptSchedule->presentationAttempt->researchGroup->category_id === $category->id, 404);
    }

}
