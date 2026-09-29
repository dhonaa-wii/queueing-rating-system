<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\AttemptPanelAssignment;
use App\Models\EvaluationLetterhead;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\ResearchGroup;
use App\Models\Student;
use App\Services\GradePdfExporter;
use App\Services\PanelSubstitutionService;
use App\Services\RoomQueuePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssignmentController extends Controller
{
    public function index(Request $request, RoomQueuePreviewService $roomQueuePreview)
    {
        $user = $request->user();

        $assignments = AttemptPanelAssignment::where('panelist_user_id', $user->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with([
                'assignmentKind',
                'assignmentStatus',
                'presentationAttempt.presentationStatus',
                'presentationAttempt.finalOutcome',
                'presentationAttempt.researchGroup.category.academicYear',
                'presentationAttempt.researchGroup.category.semester',
                'presentationAttempt.researchGroup.category.college',
                'presentationAttempt.researchGroup.students',
                'presentationAttempt.researchGroup.proposedTitles' => fn ($q) => $q->orderBy('sort_order'),
                'presentationAttempt.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempt.attemptSchedule.queueEntry',
                // Newest first — a deferred row's real defer time and reason
                // come off its latest DEFER adjustment (planned-vs-actual
                // date/time policy). The type filter matters: a group that
                // was reordered or transferred before being deferred also
                // carries those rows, and the newest of *those* is not when
                // it was deferred.
                'presentationAttempt.attemptSchedule.queueEntry.queueAdjustments' => fn ($q) => $q->orderByDesc('adjusted_at'),
                'presentationAttempt.attemptSchedule.queueEntry.queueAdjustments.adjustmentType',
                'presentationAttempt.attemptSchedule.queueEntry.queueAdjustments.reason',
                // Only submitted/finalized sheets count — a draft is not
                // something the Completed tab's View can render.
                'presentationAttempt.evaluationSubmissions' => fn ($q) => $q->whereHas(
                    'submissionStatus',
                    fn ($sq) => $sq->whereIn('code', ['SUBMITTED', 'FINALIZED'])
                ),
            ])
            ->get()
            ->filter(fn (AttemptPanelAssignment $assignment) => $assignment->presentationAttempt?->researchGroup?->category !== null)
            ->values();

        // Every category this panelist holds at least one live assignment in,
        // offered by the filter dropdown regardless of which one (if any) is
        // currently selected — so switching back to "All Categories" always
        // shows the same choices.
        $categoryOptions = $assignments
            ->map(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->researchGroup->category)
            ->unique('id')
            ->sortBy(fn ($category) => $category->name)
            ->values();

        $selectedCategoryId = $request->integer('category') ?: null;
        if ($selectedCategoryId && ! $categoryOptions->contains('id', $selectedCategoryId)) {
            $selectedCategoryId = null;
        }

        if ($selectedCategoryId) {
            $assignments = $assignments
                ->filter(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->researchGroup->category_id === $selectedCategoryId)
                ->values();
        }

        // Filter choices come from every group registered in the category (or
        // in all of this panelist's categories), not only the groups they sit
        // on, so the lists match what the category actually holds.
        $filterCategoryIds = $selectedCategoryId ? [$selectedCategoryId] : $categoryOptions->pluck('id')->all();

        $trackOptions = Student::whereHas('researchGroup', fn ($q) => $q->whereIn('category_id', $filterCategoryIds))
            ->whereNotNull('research_track_name')
            ->where('research_track_name', '!=', '')
            ->distinct()
            ->orderBy('research_track_name')
            ->pluck('research_track_name');

        $sectionOptions = Student::whereHas('researchGroup', fn ($q) => $q->whereIn('category_id', $filterCategoryIds))
            ->whereNotNull('section_name')
            ->where('section_name', '!=', '')
            ->distinct()
            ->orderBy('section_name')
            ->pluck('section_name');

        $pendingRequestAttemptIds = PanelSubstitutionRequest::where('original_panelist_user_id', $user->id)
            ->whereIn('presentation_attempt_id', $assignments->pluck('presentation_attempt_id'))
            ->whereHas('status', fn ($q) => $q->where('code', 'PENDING'))
            ->pluck('presentation_attempt_id')
            ->flip();

        // A deferred group has been pulled out of its room's queue
        // (queue_entries.removed_at) — the same marker Group & Panel
        // Assignment uses, rather than the attempt's own status, since a
        // reinserted group goes straight back to a callable status.
        $deferred = $assignments
            ->filter(fn ($a) => $a->presentationAttempt->attemptSchedule?->queueEntry?->removed_at !== null)
            ->sortByDesc(fn ($a) => $this->sortKey($this->deferredAt($a)))
            ->values();

        $deferredIds = $deferred->pluck('id')->flip();
        $remaining = $assignments->reject(fn ($a) => $deferredIds->has($a->id));

        // Anything with a recorded outcome is done with — COMPLETED normally,
        // but ABSENT/CANCELLED belong here too rather than sitting under
        // Scheduled forever, so no assignment falls out of the three tabs.
        $completed = $remaining
            ->filter(fn ($a) => $a->presentationAttempt->presentationStatus->is_terminal)
            ->sortByDesc(fn ($a) => $this->sortKey($a->presentationAttempt->completed_at ?? $this->plannedAt($a)))
            ->values();

        $stillToPresent = $remaining->reject(fn ($a) => $a->presentationAttempt->presentationStatus->is_terminal);
        $expectedTimes = $this->expectedTimes($stillToPresent, $roomQueuePreview);

        // Nearest first — on whatever time the row actually displays, so the
        // order and the Time column can never disagree.
        $scheduled = $stillToPresent
            ->sortBy(fn ($a) => $this->sortKey($this->expectedAt($a, $expectedTimes), '9999-12-31 23:59:59'))
            ->values();

        return view('panelist.assignments.index', [
            'scheduled' => $scheduled,
            'deferred' => $deferred,
            'completed' => $completed,
            'expectedTimes' => $expectedTimes,
            'pendingRequestAttemptIds' => $pendingRequestAttemptIds,
            'categoryOptions' => $categoryOptions,
            'selectedCategoryId' => $selectedCategoryId,
            'trackOptions' => $trackOptions,
            'sectionOptions' => $sectionOptions,
        ]);
    }

    /**
     * Mark Unavailable is allowed right up until the group is actually
     * presenting — user-directed 2026-09-20: an upcoming assignment on a day/
     * room that has already started is still markable, only ONGOING/PAUSED
     * blocks it. Mirrors PanelSubstitutionService::requestUnavailability()'s
     * own guard so the button's visibility never disagrees with what the
     * server will actually accept.
     */
    public static function canMarkUnavailable(AttemptPanelAssignment $assignment): bool
    {
        return ! in_array($assignment->presentationAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true);
    }

    public function requestUnavailability(Request $request, AttemptPanelAssignment $assignment, PanelSubstitutionService $service)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $result = $service->requestUnavailability($assignment, $request->user()->id, $validated['reason']);

        return $result['ok']
            ? back()->with('status', 'Your unavailability request has been sent to the Administrator for review.')
            : back()->with('error', $result['error']);
    }

    /**
     * Read-only render of the sheets this attempt's panel actually filed,
     * served as an HTML fragment into the shared evaluation-sheet modal.
     * Same eager loads and same view as Admin's
     * ReportController::evaluationSheet() — what scopes it here is the
     * assignment in the route: a panelist can only ever reach an attempt
     * they personally sat on.
     */
    public function evaluationSheet(Request $request, AttemptPanelAssignment $assignment)
    {
        $attempt = $this->loadSheetAttempt($request, $assignment);

        return view('admin.reports.partials.evaluation-sheets', [
            'attempt' => $attempt,
            'submissions' => $attempt->evaluationSubmissions,
            'letterhead' => EvaluationLetterhead::forCollege($attempt->researchGroup?->category?->college_id),
            'leadPanelistIds' => $this->leadPanelistIds($attempt),
            'pdfUrl' => route('panelist.assignments.evaluation-sheet.export-pdf', $assignment),
        ]);
    }

    /** PDF of the same sheets, one per page. */
    public function exportEvaluationSheetPdf(Request $request, AttemptPanelAssignment $assignment, GradePdfExporter $exporter)
    {
        $attempt = $this->loadSheetAttempt($request, $assignment);
        $category = $attempt->researchGroup->category->load(['academicYear', 'college']);

        $pdf = $exporter->renderEvaluationSheets(
            $category,
            $attempt,
            $attempt->evaluationSubmissions,
            $this->leadPanelistIds($attempt),
            EvaluationLetterhead::forCollege($category->college_id),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($attempt->researchGroup->group_reference) . '-evaluation-sheets-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    private function leadPanelistIds(PresentationAttempt $attempt)
    {
        return $attempt->attemptPanelAssignments
            ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
            ->where('is_lead', true)
            ->pluck('panelist_user_id');
    }

    private function loadSheetAttempt(Request $request, AttemptPanelAssignment $assignment): PresentationAttempt
    {
        if ($assignment->panelist_user_id !== $request->user()->id) {
            throw new NotFoundHttpException();
        }

        $attempt = $assignment->presentationAttempt;

        $attempt->load([
            'researchGroup.category',
            'researchGroup.students',
            'researchGroup.proposedTitles',
            'evaluationSubmissions' => fn ($q) => $q->whereHas(
                'submissionStatus',
                fn ($sq) => $sq->whereIn('code', ['SUBMITTED', 'FINALIZED'])
            ),
            'evaluationSubmissions.panelist.profile',
            'evaluationSubmissions.submissionStatus',
            'evaluationSubmissions.evaluationScores',
            'evaluationSubmissions.studentScores',
            'evaluationSubmissions.evaluationFormVersion.evaluationForm',
            'evaluationSubmissions.evaluationFormVersion.scaleLabels',
            'evaluationSubmissions.evaluationFormVersion.presentationOutcomes',
            'evaluationSubmissions.evaluationFormVersion.applicablePresentationModes',
            'evaluationSubmissions.evaluationFormVersion.evaluationCriteria.childCriteria',
            'attemptPanelAssignments.assignmentStatus',
        ]);

        return $attempt;
    }

    /** The real moment this group was pulled out of the queue. */
    private function deferredAt(AttemptPanelAssignment $assignment)
    {
        $entry = $assignment->presentationAttempt->attemptSchedule?->queueEntry;

        return $entry?->latestDeferAdjustment()?->adjusted_at ?? $entry?->removed_at;
    }

    /**
     * Expected start times, keyed by attempt_schedule id, for every room
     * these assignments sit in.
     *
     * RoomQueuePreviewService is the one place that decides what time a
     * still-to-present group is shown — the live cascade on a running day,
     * the plan on one that has not started (the 2026-09-13 rule) — and the
     * room-session tablet, Panelist Dashboard and Admin Live Monitoring all
     * read it too, so a group shows the same time here as it does there.
     * The rule itself deliberately is not re-implemented here.
     */
    private function expectedTimes(Collection $assignments, RoomQueuePreviewService $roomQueuePreview): Collection
    {
        return $assignments
            ->map(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->attemptSchedule?->presentationDateRoom)
            ->filter()
            ->unique('id')
            // Keyed only after flattening: flatMap collapses with array_merge,
            // which renumbers integer keys and would throw the ids away.
            ->flatMap(fn ($room) => $roomQueuePreview->forRoom($room)['schedules'])
            ->mapWithKeys(fn ($schedule) => [$schedule->id => $schedule->expected_start_at]);
    }

    /**
     * When this group is now expected up — drives the Time column and the
     * ordering. Null once the day it is parked on can no longer run: there is
     * no time to show, and the plan it still carries is a slot on a day that
     * is over (AttemptSchedule::isAwaitingReschedule()). Those rows sort last,
     * via the fallback key the caller passes.
     */
    private function expectedAt(AttemptPanelAssignment $assignment, Collection $expectedTimes)
    {
        $schedule = $assignment->presentationAttempt->attemptSchedule;

        if ($schedule?->isAwaitingReschedule($assignment->presentationAttempt)) {
            return null;
        }

        return ($schedule ? $expectedTimes->get($schedule->id) : null)
            ?? $schedule?->adjusted_expected_at
            ?? $schedule?->planned_start_at
            ?? $schedule?->presentationDateRoom?->presentationDate?->presentation_date;
    }

    /** The slot this group was planned into — the fallback for a done row. */
    private function plannedAt(AttemptPanelAssignment $assignment)
    {
        $schedule = $assignment->presentationAttempt->attemptSchedule;

        return $schedule?->adjusted_expected_at
            ?? $schedule?->planned_start_at
            ?? $schedule?->presentationDateRoom?->presentationDate?->presentation_date;
    }

    /**
     * Sort on a plain string rather than a Carbon instance — a collection
     * sort that mixes Carbon and null throws on comparison (the same class
     * of bug already hit in Group & Panel Assignment).
     */
    private function sortKey($value, string $fallback = '0000-00-00 00:00:00'): string
    {
        return $value?->format('Y-m-d H:i:s') ?? $fallback;
    }
}
