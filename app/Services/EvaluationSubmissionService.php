<?php

namespace App\Services;

use App\Models\AttemptPanelParticipation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\EvaluationSubmission;
use App\Models\EvaluationSubmissionStudentScore;
use App\Models\PresentationAttempt;
use App\Models\ProposedTitle;
use App\Models\RoomSession;
use App\Models\Student;
use App\Models\SubmissionStatus;

/**
 * First writer for evaluation_submissions/evaluation_scores (confirmed
 * greenfield — zero ::create() calls anywhere before this). One submission
 * row per (attempt, panelist), created the moment PresentationControlService::
 * start() records that panelist's attempt_panel_participation row; scored
 * live from the room-session tablet while the presentation is ONGOING/PAUSED.
 *
 * The builder (EvaluationFormBuilderService) only ever produces GROUP-scope
 * criteria today (individual-scope scoring has no admin UI to drive it, per
 * CLAUDE.md's Evaluation Builder Workspace Redesign notes), so scoring here
 * never sets evaluation_scores.student_id — that column stays reserved for
 * a future INDIVIDUAL_STUDENT-scope UI.
 */
class EvaluationSubmissionService
{
    /**
     * Called from PresentationControlService::start(), inside its own
     * transaction, right after recordPanelParticipation(). Blocks Start
     * entirely (by returning failure) when the category has no active
     * evaluation form assigned — there would be nothing for a panelist to
     * fill out otherwise.
     */
    public function startFor(PresentationAttempt $attempt, RoomSession $session): array
    {
        $room = $session->presentationDateRoom;
        $category = $room->presentationDate->category;

        $formVersion = $category->categoryEvaluationForms()
            ->whereNull('effective_until')
            ->first()
            ?->evaluationFormVersion;

        if (! $formVersion) {
            return $this->failure('This category has no evaluation form assigned — assign one before starting.');
        }

        $draftStatusId = SubmissionStatus::where('code', 'DRAFT')->firstOrFail()->id;

        $participations = AttemptPanelParticipation::where('presentation_attempt_id', $attempt->id)
            ->whereNull('participation_ended_at')
            ->get();

        foreach ($participations as $participation) {
            EvaluationSubmission::firstOrCreate(
                [
                    'presentation_attempt_id' => $attempt->id,
                    'panelist_user_id' => $participation->panelist_user_id,
                ],
                [
                    'evaluation_form_version_id' => $formVersion->id,
                    'attempt_panel_participation_id' => $participation->id,
                    'submission_status_id' => $draftStatusId,
                ]
            );
        }

        return ['ok' => true];
    }

    public function saveScore(EvaluationSubmission $submission, EvaluationCriterion $criterion, float $score, ?ProposedTitle $proposedTitle = null): array
    {
        if ($blocked = $this->blockedByStatus($submission)) {
            return $this->failure($blocked);
        }

        $submission->loadMissing('evaluationFormVersion');
        $version = $submission->evaluationFormVersion;

        if ($score < $version->scale_min || $score > $version->scale_max) {
            return $this->failure("Rating must be between {$version->scale_min} and {$version->scale_max}.");
        }

        EvaluationScore::updateOrCreate(
            [
                'evaluation_submission_id' => $submission->id,
                'evaluation_criterion_id' => $criterion->id,
                'proposed_title_id' => $proposedTitle?->id,
            ],
            ['score' => $score]
        );

        $totals = $this->recomputeTotals($submission);

        return ['ok' => true, 'totals' => $totals];
    }

    /**
     * The sample sheet's "Individual (Raw Score)" column — a holistic
     * per-researcher number the panel types directly, not derived from any
     * criterion (Standard mode only; Title Proposal's researchers table has
     * no such column). Stored in evaluation_submission_student_scores, kept
     * separate from evaluation_scores which stays purely criterion-based.
     */
    public function saveStudentScore(EvaluationSubmission $submission, Student $student, ?float $score): array
    {
        if ($blocked = $this->blockedByStatus($submission)) {
            return $this->failure($blocked);
        }

        if ($score !== null && ($score < 0 || $score > 100)) {
            return $this->failure('Individual raw score must be between 0 and 100.');
        }

        EvaluationSubmissionStudentScore::updateOrCreate(
            [
                'evaluation_submission_id' => $submission->id,
                'student_id' => $student->id,
            ],
            ['score' => $score]
        );

        return ['ok' => true];
    }

    public function saveOutcome(EvaluationSubmission $submission, ?int $outcomeId): array
    {
        if ($blocked = $this->blockedByStatus($submission)) {
            return $this->failure($blocked);
        }

        $submission->update(['presentation_outcome_id' => $outcomeId]);

        return ['ok' => true];
    }

    public function saveRemarks(EvaluationSubmission $submission, ?string $remarks): array
    {
        if ($blocked = $this->blockedByStatus($submission)) {
            return $this->failure($blocked);
        }

        $submission->update(['remarks' => $remarks]);

        return ['ok' => true];
    }

    public function submit(EvaluationSubmission $submission): array
    {
        if ($blocked = $this->blockedByStatus($submission)) {
            return $this->failure($blocked);
        }

        $missing = $this->countMissingScores($submission);

        if ($missing > 0) {
            return $this->failure("{$missing} rating(s) still missing — score every item before submitting.");
        }

        if ($this->outcomeMissing($submission)) {
            return $this->failure('Select a remark before submitting.');
        }

        $submission->update([
            'submission_status_id' => SubmissionStatus::where('code', 'SUBMITTED')->firstOrFail()->id,
            'submitted_at' => now(),
        ]);

        return ['ok' => true];
    }

    /**
     * Whether the panelist still owes a pick in the sheet's Remarks column
     * (evaluation_submissions.presentation_outcome_id) — user-directed
     * 2026-09-13: a verdict is required before submitting, alongside the
     * existing every-rating-scored rule. A form version with no outcomes
     * configured has nothing to pick, so it never blocks. Shared by
     * submit() and the tablet's own pre-emptive Submit-button disable, the
     * same way countMissingScores() already is.
     */
    public function outcomeMissing(EvaluationSubmission $submission): bool
    {
        $submission->loadMissing('evaluationFormVersion.presentationOutcomes');

        return $submission->evaluationFormVersion->presentationOutcomes->isNotEmpty()
            && $submission->presentation_outcome_id === null;
    }

    /**
     * Number of (criterion, title) pairs that still have no score — 0 means
     * the submission is complete and may be submitted. Title Proposal mode
     * requires one score per leaf criterion per the group's actual
     * registered proposed title; Standard mode requires one per leaf
     * criterion.
     */
    public function countMissingScores(EvaluationSubmission $submission): int
    {
        $submission->loadMissing('evaluationScores', 'evaluationFormVersion.applicablePresentationModes', 'presentationAttempt.researchGroup.proposedTitles');

        $leafCriterionIds = $submission->evaluationFormVersion->evaluationCriteria
            ->whereNotNull('parent_criterion_id')
            ->pluck('id');

        $isTitleProposal = $submission->evaluationFormVersion->applicablePresentationModes->first()?->code === 'TITLE_PROPOSAL';

        $titleIds = $isTitleProposal
            ? $submission->presentationAttempt->researchGroup->proposedTitles->pluck('id')
            : collect([null]);

        $required = 0;
        $have = 0;

        foreach ($titleIds as $titleId) {
            foreach ($leafCriterionIds as $criterionId) {
                $required++;

                if ($submission->evaluationScores->contains(fn ($s) => $s->evaluation_criterion_id === $criterionId && $s->proposed_title_id === $titleId)) {
                    $have++;
                }
            }
        }

        return $required - $have;
    }

    /**
     * Gates PresentationControlService::complete() — user-directed
     * 2026-08-18: every connected panelist must submit their own evaluation
     * before the Lead can mark the presentation complete. False when no
     * submissions exist at all (Start should always have created at least
     * one, but an attempt with nothing to submit is not "all submitted").
     */
    /**
     * The attempt's official outcome, taken from the Lead/Chair panelist's
     * own submitted verdict — user-directed 2026-09-13, chosen over a
     * majority vote or an admin-recorded consolidation. Every panelist must
     * already pick a remark before submit() lets their sheet through
     * (outcomeMissing()), so by the time complete() runs the Lead's pick is
     * always present; this just promotes it to
     * presentation_attempts.final_outcome_id, which is what the whole
     * pass/re-defense/failed split downstream branches on.
     *
     * Lead is attempt_panel_assignments.is_lead (§2.7.4), ignoring a
     * REPLACED/WITHDRAWN row so a swapped-out panelist's superseded
     * designation can't still decide the verdict — the same filter
     * ReportController::evaluationSheet() uses for its sign-off labels.
     * Returns null when there is no live Lead designation or the Lead filed
     * no sheet: the attempt still completes, it just carries no official
     * outcome until an Admin records one.
     */
    public function leadOutcomeFor(PresentationAttempt $attempt): ?int
    {
        $attempt->loadMissing('attemptPanelAssignments.assignmentStatus', 'evaluationSubmissions');

        $leadIds = $attempt->attemptPanelAssignments
            ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
            ->where('is_lead', true)
            ->pluck('panelist_user_id');

        if ($leadIds->isEmpty()) {
            return null;
        }

        return $attempt->evaluationSubmissions
            ->whereIn('panelist_user_id', $leadIds)
            ->firstWhere('presentation_outcome_id', '!=', null)
            ?->presentation_outcome_id;
    }

    public function allSubmitted(PresentationAttempt $attempt): bool
    {
        $submissions = EvaluationSubmission::where('presentation_attempt_id', $attempt->id)
            ->with('submissionStatus')
            ->get();

        if ($submissions->isEmpty()) {
            return false;
        }

        return $submissions->every(fn ($submission) => in_array($submission->submissionStatus?->code, ['SUBMITTED', 'FINALIZED'], true));
    }

    /**
     * True once at least one panelist has genuinely filed a sheet against
     * this attempt — a DRAFT row left behind by a started-then-deferred
     * presentation doesn't count. Gates both
     * PresentationAttemptAdminActionService::delete() (a real evaluation
     * can never be deleted out from under its own attempt) and, user-
     * directed 2026-09-17, QueueAdjustmentService::defer() (a group can no
     * longer be sent back to the queue once one of its assigned panel has
     * already scored it).
     */
    public function hasRealSubmission(PresentationAttempt $attempt): bool
    {
        return EvaluationSubmission::where('presentation_attempt_id', $attempt->id)
            ->whereHas('submissionStatus', fn ($query) => $query->whereIn('code', ['SUBMITTED', 'FINALIZED', 'INVALIDATED']))
            ->exists();
    }

    /**
     * Standard mode only — a single submission can't hold "the" total for a
     * Title Proposal form covering several titles, so weighted/raw totals
     * stay null there; per-title subtotals are computed for display only in
     * the view layer, never persisted. scoring_methods currently seeds
     * exactly one placeholder row (WEIGHTED_AVERAGE, "pending department
     * confirmation" per its own seeder) so this formula is intentionally
     * isolated here for easy replacement later.
     */
    private function recomputeTotals(EvaluationSubmission $submission): array
    {
        $submission->loadMissing('evaluationScores', 'evaluationFormVersion.applicablePresentationModes', 'evaluationFormVersion.sections.childCriteria');
        $version = $submission->evaluationFormVersion;
        $isTitleProposal = $version->applicablePresentationModes->first()?->code === 'TITLE_PROPOSAL';

        if ($isTitleProposal) {
            $submission->update(['raw_total_score' => null, 'weighted_total_score' => null]);

            return ['raw' => null, 'weighted' => null];
        }

        $scoresByCriterion = $submission->evaluationScores->keyBy('evaluation_criterion_id');
        $rawTotal = 0.0;
        $weightedTotal = 0.0;

        foreach ($version->sections as $section) {
            $itemScores = $section->childCriteria
                ->map(fn ($item) => $scoresByCriterion->get($item->id)?->score)
                ->filter(fn ($score) => $score !== null);

            if ($itemScores->isEmpty()) {
                continue;
            }

            $rawTotal += $itemScores->sum();
            $sectionAverage = $itemScores->avg();
            $sectionPercent = $version->scale_max > 0 ? ($sectionAverage / $version->scale_max) * 100 : 0;
            $weightedTotal += $sectionPercent * ((float) ($section->weight ?? 0) / 100);
        }

        $submission->update([
            'raw_total_score' => round($rawTotal, 2),
            'weighted_total_score' => round($weightedTotal, 2),
        ]);

        return ['raw' => round($rawTotal, 2), 'weighted' => round($weightedTotal, 2)];
    }

    private function blockedByStatus(EvaluationSubmission $submission): ?string
    {
        $submission->loadMissing('submissionStatus');
        $code = $submission->submissionStatus?->code;

        if (in_array($code, ['SUBMITTED', 'FINALIZED', 'INVALIDATED'], true)) {
            return 'This evaluation has already been submitted.';
        }

        return null;
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
