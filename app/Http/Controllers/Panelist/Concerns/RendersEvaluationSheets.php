<?php

namespace App\Http\Controllers\Panelist\Concerns;

use App\Models\EvaluationLetterhead;
use App\Models\PresentationAttempt;
use App\Services\GradePdfExporter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The read-only evaluation sheets a panel filed for one attempt, as the
 * fragment the shared evaluation-sheet modal loads and as a PDF. Same eager
 * loads and same view as Admin's ReportController::evaluationSheet(). Shared
 * by My Assignments and My Advisees — each controller decides who may
 * reach the attempt before calling in here.
 */
trait RendersEvaluationSheets
{
    protected function evaluationSheetView(PresentationAttempt $attempt, string $pdfUrl)
    {
        $this->loadSheetRelations($attempt);

        return view('admin.reports.partials.evaluation-sheets', [
            'attempt' => $attempt,
            'submissions' => $attempt->evaluationSubmissions,
            'letterhead' => EvaluationLetterhead::forCollege($attempt->researchGroup?->category?->college_id),
            'leadPanelistIds' => $this->leadPanelistIds($attempt),
            'pdfUrl' => $pdfUrl,
        ]);
    }

    protected function evaluationSheetPdf(PresentationAttempt $attempt, GradePdfExporter $exporter)
    {
        $this->loadSheetRelations($attempt);
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

    private function leadPanelistIds(PresentationAttempt $attempt): Collection
    {
        return $attempt->attemptPanelAssignments
            ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
            ->where('is_lead', true)
            ->pluck('panelist_user_id');
    }

    private function loadSheetRelations(PresentationAttempt $attempt): void
    {
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
    }
}
