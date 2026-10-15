<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panelist\Concerns\RendersEvaluationSheets;
use App\Models\EvaluationLetterhead;
use App\Models\PresentationAttempt;
use App\Models\ResearchGroup;
use App\Services\AdviseeService;
use App\Services\GradePdfExporter;
use App\Services\RecommendationSummaryService;
use App\Services\RoomQueuePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The signed-in panelist's advisees — groups they advise (user-directed 2026-10-11).
 * Which groups count as an advisee is decided by AdviseeService, shared with
 * the Panelist Dashboard's advisee summary.
 */
class AdviseeController extends Controller
{
    use RendersEvaluationSheets;

    public function index(Request $request, RoomQueuePreviewService $roomQueuePreview, AdviseeService $advisees)
    {
        $groups = $advisees->groupsFor($request->user());

        // One row per attempt — a group that went to Re-Defense has its first
        // attempt under Completed and the follow-up under Upcoming.
        // A group with no attempt yet (not queued) still belongs on the list.
        $rows = $groups->flatMap(function (ResearchGroup $group) {
            return $group->presentationAttempts->isEmpty()
                ? [(object) ['group' => $group, 'attempt' => null]]
                : $group->presentationAttempts->map(fn ($attempt) => (object) ['group' => $group, 'attempt' => $attempt])->all();
        });

        $isDone = fn ($row) => (bool) $row->attempt?->presentationStatus?->is_terminal;

        $completed = $rows->filter($isDone)
            ->sortByDesc(fn ($row) => $this->sortKey($row->attempt->completed_at))
            ->values();

        $upcomingRows = $rows->reject($isDone);
        $forecast = $this->forecast($upcomingRows, $roomQueuePreview);
        $expectedTimes = $forecast->map(fn ($schedule) => $schedule->expected_start_at);
        $pastSessionEnd = $forecast->filter(fn ($schedule) => $schedule->isPastSessionEnd())->map(fn ($schedule) => $schedule->session_end_at);

        $upcoming = $upcomingRows
            ->sortBy(fn ($row) => $this->sortKey($this->expectedAt($row, $expectedTimes), '9999-12-31 23:59:59'))
            ->values();

        $categoryOptions = $groups->pluck('category')->unique('id')->sortBy('name')->values();

        return view('panelist.advisees.index', [
            'upcoming' => $upcoming,
            'completed' => $completed,
            'expectedTimes' => $expectedTimes,
            'pastSessionEnd' => $pastSessionEnd,
            'hasGroups' => $groups->isNotEmpty(),
            'categoryOptions' => $categoryOptions,
            'trackOptions' => $groups->flatMap(fn ($g) => $g->students->pluck('research_track_name'))->filter()->unique()->sort()->values(),
            'sectionOptions' => $groups->flatMap(fn ($g) => $g->students->pluck('section_name'))->filter()->unique()->sort()->values(),
        ]);
    }

    public function evaluationSheet(Request $request, PresentationAttempt $attempt)
    {
        return $this->evaluationSheetView(
            $this->advisedAttempt($request, $attempt),
            route('panelist.advisees.evaluation-sheet.export-pdf', $attempt),
        );
    }

    public function exportEvaluationSheetPdf(Request $request, PresentationAttempt $attempt, GradePdfExporter $exporter)
    {
        return $this->evaluationSheetPdf($this->advisedAttempt($request, $attempt), $exporter);
    }

    /**
     * Summary of Recommendations & Comments for this panelist's advisees only
     * — the same list Admin Reports shows per category, across every category
     * the panelist advises in.
     */
    public function recommendations(Request $request, RecommendationSummaryService $service)
    {
        return view('admin.reports.partials.recommendations', [
            'rows' => $service->rowsForAdviser($request->user()),
            'showCategory' => true,
            'formUrls' => fn ($row) => [
                'print' => route('panelist.advisees.recommendation-form', $row->attempt_id),
                'pdf' => route('panelist.advisees.recommendation-form.export-pdf', $row->attempt_id),
            ],
        ]);
    }

    public function exportRecommendationsPdf(Request $request, RecommendationSummaryService $service, GradePdfExporter $exporter)
    {
        $user = $request->user()->loadMissing(['profile', 'panelistProfile.college']);
        $college = $user->panelistProfile?->college;
        $name = trim(($user->profile?->first_name ?? '') . ' ' . ($user->profile?->last_name ?? '')) ?: $user->username;

        $pdf = $exporter->renderAdviseeRecommendations(
            $name,
            $college?->name,
            $service->rowsForAdviser($user),
            EvaluationLetterhead::forCollege($college?->id),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="my-advisees-recommendations-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    public function recommendationForm(Request $request, PresentationAttempt $attempt, RecommendationSummaryService $service)
    {
        $form = $service->form($this->completedAdvisedAttempt($request, $attempt));

        return view('admin.reports.recommendation-form', [
            'form' => $form,
            'letterhead' => $this->formLetterhead($form->category->college_id),
            'forPrint' => true,
        ]);
    }

    public function exportRecommendationForm(Request $request, PresentationAttempt $attempt, RecommendationSummaryService $service, GradePdfExporter $exporter)
    {
        $form = $service->form($this->completedAdvisedAttempt($request, $attempt));

        return response($exporter->renderRecommendationForm($form, $this->formLetterhead($form->category->college_id)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($form->group_reference) . '-recommendation-form.pdf"',
        ]);
    }

    private function completedAdvisedAttempt(Request $request, PresentationAttempt $attempt): PresentationAttempt
    {
        $attempt = $this->advisedAttempt($request, $attempt);

        if ($attempt->presentationStatus()->value('code') !== 'COMPLETED') {
            throw new NotFoundHttpException();
        }

        return $attempt;
    }

    private function formLetterhead(?int $collegeId): ?EvaluationLetterhead
    {
        $letterhead = EvaluationLetterhead::forCollege($collegeId);

        return $letterhead && $letterhead->hasContent() ? $letterhead : null;
    }

    /** Only an attempt of a group this panelist advises — anything else 404s. */
    private function advisedAttempt(Request $request, PresentationAttempt $attempt): PresentationAttempt
    {
        $group = $attempt->researchGroup()->with('category')->first();

        if (! $group || ! app(AdviseeService::class)->advises($request->user(), $group)) {
            throw new NotFoundHttpException();
        }

        return $attempt;
    }

    /**
     * The forecast schedules (expected times, past-session-end flag) keyed
     * by attempt_schedule id — the same
     * RoomQueuePreviewService forecast My Assignments, the tablet and Event
     * Control read, so a group shows the same time everywhere.
     */
    private function forecast(Collection $rows, RoomQueuePreviewService $roomQueuePreview): Collection
    {
        return $rows
            ->map(fn ($row) => $row->attempt?->attemptSchedule?->presentationDateRoom)
            ->filter()
            ->unique('id')
            // Keyed only after flattening: flatMap collapses with array_merge,
            // which renumbers integer keys and would throw the ids away.
            ->flatMap(fn ($room) => $roomQueuePreview->forRoom($room)['schedules'])
            ->keyBy('id');
    }

    private function expectedAt($row, Collection $expectedTimes)
    {
        $schedule = $row->attempt?->attemptSchedule;

        if (! $schedule || $schedule->isAwaitingReschedule($row->attempt) || $schedule->queueEntry?->removed_at) {
            return null;
        }

        return $expectedTimes->get($schedule->id)
            ?? $schedule->adjusted_expected_at
            ?? $schedule->planned_start_at;
    }

    /** Sort on a string — a sort mixing Carbon and null throws. */
    private function sortKey($value, string $fallback = '0000-00-00 00:00:00'): string
    {
        return $value?->format('Y-m-d H:i:s') ?? $fallback;
    }
}
