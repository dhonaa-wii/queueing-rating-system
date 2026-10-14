<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicatePaymentReferenceException;
use App\Http\Controllers\Controller;
use App\Models\EvaluationLetterhead;
use App\Models\EvaluationSubmission;
use App\Models\PaymentVerification;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Services\EvaluationSubmissionService;
use App\Services\GradePdfExporter;
use App\Services\GradeReportService;
use App\Services\GradeSpreadsheetExporter;
use App\Services\PanelistDaySheetService;
use App\Services\PaymentVerificationService;
use App\Services\RecommendationSummaryService;
use App\Support\DefaultCategoryPicker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportController extends Controller
{
    private const TOP_TYPES = ['groups', 'presenters'];

    private const TOP_LIMIT = 5;

    /**
     * Reports opens straight on a category's Generated Grades rather than a
     * picker grid (user-directed 2026-09-17) — the admin almost always wants
     * the one that is running now, and picking it again on every visit was a
     * step with no decision in it. The picker moved onto the grades page
     * itself, as a dropdown. This route only renders something of its own
     * when there is genuinely nothing to report on yet.
     */
    public function index()
    {
        $category = DefaultCategoryPicker::pick($this->reportableCategories());

        if (! $category) {
            return view('admin.reports.index');
        }

        return redirect()->route('admin.reports.grades', $category);
    }

    /**
     * Categories the Generated Grades report has something to show for. Only
     * Standard-mode categories qualify (Title Proposal forms never populate a
     * group/individual grade, see GradeReportService's own doc comment) —
     * and either at least one submitted evaluation exists, or the category
     * has ended (isCompleted()). User-directed 2026-09-17: an ended category
     * stays reportable even with zero submissions (e.g. it was ended with no
     * groups ever registered, or before any evaluation was filed) — Reports
     * is the one place an ended category's data is still meant to be worked
     * with, per CategorySetupLock's own doc comment, so it shouldn't vanish
     * from here just because there's nothing to show yet.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PresentationCategory>
     */
    private function reportableCategories()
    {
        return PresentationCategory::forAdminCollege()->whereHas('presentationMode', fn ($q) => $q->where('code', 'STANDARD'))
            ->where(function ($query) {
                $query->whereHas('researchGroups.presentationAttempts.evaluationSubmissions')
                    ->orWhereHas('categoryStatus', fn ($q) => $q->where('code', 'COMPLETED'));
            })
            ->with(['academicYear', 'semester', 'college', 'presentationDates.eventDateStatus'])
            ->orderByDesc('created_at')
            ->get();
    }


    public function grades(Request $request, PresentationCategory $category, GradeReportService $gradeReportService)
    {
        $category->load(['academicYear', 'semester', 'college']);

        $filters = $this->filters($request);
        $sections = $gradeReportService->sectionsFor($category);
        $hasUnassignedSection = $gradeReportService->hasUnassignedSection($category);
        $dates = $gradeReportService->datesFor($category);
        $rows = $gradeReportService->generate($category, $filters);
        $paymentTypes = $gradeReportService->paymentTypesFor($category);
        // Deliberately unfiltered: the Best Group is the category's running
        // leader (§2.11), not the best of whatever the table is filtered to.
        $bestGroups = $gradeReportService->bestGroups($category);
        $bestPresenters = $gradeReportService->bestPresenters($category);
        $filterSummary = $this->filterSummary($filters, $category);

        // Feeds the header's category picker, which replaced the old "Back to
        // Reports" button.
        $categories = DefaultCategoryPicker::withCurrent($this->reportableCategories(), $category);

        // ?top=groups|presenters swaps the grades table for the top-5 cards
        // behind the matching Best card's View button (user-directed
        // 2026-09-25). Like the Best cards, it ignores the table's filters.
        $top = in_array($request->query('top'), self::TOP_TYPES, true) ? $request->query('top') : null;
        $topRows = match ($top) {
            'groups' => $gradeReportService->topGroups($category, self::TOP_LIMIT),
            'presenters' => $gradeReportService->topPresenters($category, self::TOP_LIMIT),
            default => collect(),
        };

        return view('admin.reports.grades', compact(
            'category',
            'categories',
            'sections',
            'hasUnassignedSection',
            'dates',
            'filters',
            'rows',
            'paymentTypes',
            'bestGroups',
            'bestPresenters',
            'top',
            'topRows',
            'filterSummary',
        ));
    }

    /**
     * The grades table's filter state, shared by the page and its export so
     * the file always matches what the admin is looking at.
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('q', '')),
            'section' => $request->query('section') ?: null,
            'date' => $request->query('date') ?: null,
            'sort' => $request->query('sort') === GradeReportService::SORT_LAST_NAME
                ? GradeReportService::SORT_LAST_NAME
                : GradeReportService::SORT_NAME,
        ];
    }

    /**
     * A short "which filters produced this" line, e.g. "Section: 4B · Date:
     * Sep 13, 2026 · Sorted by last name" — only the filters actually set,
     * unlike the export's label which always states the section. Shared
     * shape with exportGrades()'s own label so the wording matches.
     */
    private function filterSummary(array $filters, PresentationCategory $category): string
    {
        $sectionLabel = match ($filters['section']) {
            null => null,
            GradeReportService::UNASSIGNED_SECTION => 'Unassigned',
            default => $filters['section'],
        };

        $date = $filters['date']
            ? PresentationDate::where('category_id', $category->id)->find($filters['date'])
            : null;

        return collect([
            $sectionLabel ? 'Section: ' . $sectionLabel : null,
            $date ? 'Date: ' . $date->presentation_date->format('M j, Y') : null,
            $filters['search'] !== '' ? 'Search: ' . $filters['search'] : null,
            $filters['sort'] === GradeReportService::SORT_LAST_NAME ? 'Sorted by last name' : null,
        ])->filter()->implode('   ·   ');
    }

    /**
     * Excel download of the grades table, honouring the same filters the page
     * is showing.
     */
    public function exportGrades(Request $request, PresentationCategory $category, GradeReportService $gradeReportService, GradeSpreadsheetExporter $exporter)
    {
        $category->load(['academicYear', 'semester', 'college']);

        $filters = $this->filters($request);
        [$filterLabel, $filename] = $this->exportLabels($filters, $category);

        $spreadsheet = $exporter->build($category, $gradeReportService->generate($category, $filters), $filterLabel, $gradeReportService->paymentTypesFor($category));

        return response()->streamDownload(
            fn () => $exporter->writer($spreadsheet)->save('php://output'),
            $filename . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * PDF download of the same table and filters, under the college's
     * letterhead (user-directed 2026-09-25). The Evaluation column is left
     * out — it is a View button, not data.
     */
    public function exportGradesPdf(Request $request, PresentationCategory $category, GradeReportService $gradeReportService, GradePdfExporter $exporter)
    {
        $category->load(['academicYear', 'semester', 'college']);

        $filters = $this->filters($request);
        [$filterLabel, $filename] = $this->exportLabels($filters, $category);

        $pdf = $exporter->render(
            $category,
            $gradeReportService->generate($category, $filters),
            $filterLabel,
            $gradeReportService->paymentTypesFor($category),
            EvaluationLetterhead::forCollege($category->college_id),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }

    /**
     * PDF of the top-5 list the admin is looking at — best groups by Group
     * Grade or best presenters by Individual Grade Avg. — under the college's
     * letterhead.
     */
    public function exportTopPdf(PresentationCategory $category, string $type, GradeReportService $gradeReportService, GradePdfExporter $exporter)
    {
        abort_unless(in_array($type, self::TOP_TYPES, true), 404);

        $category->load(['academicYear', 'semester', 'college']);

        $rows = $type === 'groups'
            ? $gradeReportService->topGroups($category, self::TOP_LIMIT)
            : $gradeReportService->topPresenters($category, self::TOP_LIMIT);

        $pdf = $exporter->renderTop(
            $category,
            $type,
            $rows,
            EvaluationLetterhead::forCollege($category->college_id),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($category->name) . '-top-' . $type . '-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    /**
     * HTML fragment for the Recommendations modal — the running list of
     * completed groups with their panelists' comments. Fetched each time the
     * modal opens so it always reflects what has finished since.
     */
    public function recommendations(PresentationCategory $category, RecommendationSummaryService $service)
    {
        return view('admin.reports.partials.recommendations', [
            'rows' => $service->rows($category),
        ]);
    }

    /**
     * PDF of the same list, under the college's letterhead. Available at any
     * point — it simply contains whatever has completed so far.
     */
    public function exportRecommendationsPdf(PresentationCategory $category, RecommendationSummaryService $service, GradePdfExporter $exporter)
    {
        $category->load(['academicYear', 'semester', 'college']);

        $pdf = $exporter->renderRecommendations(
            $category,
            $service->rows($category),
            EvaluationLetterhead::forCollege($category->college_id),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($category->name) . '-recommendations-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    /**
     * The "which filters produced this" line and the download's base filename,
     * shared by the Excel and PDF exports so both files read the same. Unlike
     * filterSummary() this always states the section ("All" when unfiltered).
     *
     * @return array{0: string, 1: string}
     */
    private function exportLabels(array $filters, PresentationCategory $category): array
    {
        $sectionLabel = match ($filters['section']) {
            null => null,
            GradeReportService::UNASSIGNED_SECTION => 'Unassigned',
            default => $filters['section'],
        };

        $date = $filters['date']
            ? PresentationDate::where('category_id', $category->id)->find($filters['date'])
            : null;

        $filterLabel = collect([
            'Section: ' . ($sectionLabel ?? 'All'),
            $date ? 'Date: ' . $date->presentation_date->format('M j, Y') : null,
            $filters['search'] !== '' ? 'Search: ' . $filters['search'] : null,
            $filters['sort'] === GradeReportService::SORT_LAST_NAME ? 'Sorted by last name' : null,
        ])->filter()->implode('   ·   ');

        $filename = Str::slug($category->name) . '-grades' . ($sectionLabel ? '-' . Str::slug($sectionLabel) : '') . '-' . now()->format('Y-m-d');

        return [$filterLabel, $filename];
    }

    /**
     * The running panelist sign-off sheet for the whole category — available
     * at any point, it simply contains whatever has been evaluated so far.
     * `?embed=1` drops the page's own toolbar, for the iframe the Reports page
     * shows it in; without it, it is a standalone printable page.
     */
    public function panelistSheet(Request $request, PresentationCategory $category, PanelistDaySheetService $daySheetService)
    {
        $category->load(['academicYear', 'semester', 'college']);

        return view('admin.reports.panelist-sheet', [
            'category' => $category,
            'sheet' => $daySheetService->sheet($category),
            'letterhead' => EvaluationLetterhead::forCollege($category->college_id),
            'embed' => $request->boolean('embed'),
        ]);
    }

    /**
     * PDF of the same sheet, under the college's letterhead.
     */
    public function exportPanelistSheetPdf(PresentationCategory $category, PanelistDaySheetService $daySheetService, GradePdfExporter $exporter)
    {
        $category->load(['academicYear', 'semester', 'college']);

        $pdf = $exporter->renderPanelistSheet(
            $category,
            $daySheetService->sheet($category),
            EvaluationLetterhead::forCollege($category->college_id),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($category->name) . '-panelist-signoff-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    /**
     * HTML fragment for the grades table's View modal — every submitted
     * evaluation sheet the panel filed for one completed attempt, rendered
     * read-only. Fetched on demand rather than rendered inline for every
     * row: a category's report can list dozens of groups, each with one
     * sheet per panelist, and only one is ever looked at at a time.
     */
    public function evaluationSheet(PresentationCategory $category, PresentationAttempt $attempt)
    {
        $this->loadSheetAttempt($category, $attempt);

        return view('admin.reports.partials.evaluation-sheets', [
            'attempt' => $attempt,
            'submissions' => $attempt->evaluationSubmissions,
            'letterhead' => EvaluationLetterhead::forCollege($category->college_id),
            // Drives each sheet's "Lead Panelist" / "Panelist" sign-off
            // label — attempt_panel_assignments.is_lead is the designation
            // Group & Panel Assignment writes (§2.7.4), and a swapped-out
            // panelist's superseded row must not still claim the seat.
            'leadPanelistIds' => $this->leadPanelistIds($attempt),
            'pdfUrl' => route('admin.reports.evaluation-sheet.export-pdf', [$category, $attempt]),
            'editUrl' => fn ($submission) => route('admin.reports.evaluation-sheet.edit', [$category, $attempt, $submission]),
        ]);
    }

    /**
     * The Edit form for one submitted sheet, swapped into the same modal
     * page it was opened from (user-directed 2026-10-13: Admins can correct
     * a submitted evaluation from where they view it).
     */
    public function editEvaluationSubmission(PresentationCategory $category, PresentationAttempt $attempt, EvaluationSubmission $submission)
    {
        $this->loadSheetAttempt($category, $attempt);

        $submission = $attempt->evaluationSubmissions->firstWhere('id', $submission->id)
            ?? throw new NotFoundHttpException();

        return view('admin.reports.partials.evaluation-sheet-edit', [
            'attempt' => $attempt,
            'submission' => $submission,
            'updateUrl' => route('admin.reports.evaluation-sheet.update', [$category, $attempt, $submission]),
        ]);
    }

    public function updateEvaluationSubmission(Request $request, PresentationCategory $category, PresentationAttempt $attempt, EvaluationSubmission $submission, EvaluationSubmissionService $service)
    {
        $this->loadSheetAttempt($category, $attempt);

        if ($submission->presentation_attempt_id !== $attempt->id) {
            throw new NotFoundHttpException();
        }

        $validated = $request->validate([
            'scores' => ['array'],
            'student_scores' => ['array'],
            'presentation_outcome_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $result = $service->adminEdit(
            $submission,
            $validated['scores'] ?? [],
            $validated['student_scores'] ?? [],
            isset($validated['presentation_outcome_id']) ? (int) $validated['presentation_outcome_id'] : null,
            $validated['remarks'] ?? null,
            $request->user()->id,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Evaluation updated.']);
    }

    /**
     * Correct a group's recorded payment reference number from the Payment/s
     * modal on Generated Grades. The PDF/Excel exports are untouched by this.
     */
    public function updatePaymentReceipt(Request $request, PresentationCategory $category, PresentationAttempt $attempt, PaymentVerification $verification, PaymentVerificationService $service)
    {
        if ($attempt->researchGroup?->category_id !== $category->id
            || $verification->presentation_attempt_id !== $attempt->id
            || $verification->receipt_code_encrypted === null) {
            throw new NotFoundHttpException();
        }

        $referenceNumber = $this->validatedReceiptNumber($request);

        try {
            $service->updateReceiptByAdmin($verification, $referenceNumber);
        } catch (DuplicatePaymentReferenceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Receipt number updated.',
            'verification_id' => $verification->id,
            'receipt' => $verification->receipt_code_encrypted,
        ]);
    }

    /**
     * Record a payment type that has no receipt number yet, from the same
     * modal. Saved as RESOLVED by the Admin, the same as Group & Panel
     * Assignment's Verify Payment.
     */
    public function storePaymentReceipt(Request $request, PresentationCategory $category, PresentationAttempt $attempt, PaymentVerificationService $service)
    {
        if ($attempt->researchGroup?->category_id !== $category->id) {
            throw new NotFoundHttpException();
        }

        $type = $service->typesFor($category)->firstWhere('id', (int) $request->input('category_payment_type_id'))
            ?? throw new NotFoundHttpException();

        $existing = $attempt->paymentVerifications()->where('category_payment_type_id', $type->id)->first();
        if ($existing?->receipt_code_encrypted !== null) {
            return response()->json(['message' => 'This payment already has a receipt number. Edit it instead.'], 422);
        }

        $referenceNumber = $this->validatedReceiptNumber($request);

        try {
            $service->resolveByAdmin($attempt, $category, [$type->id => $referenceNumber], null, $request->user()->id);
        } catch (DuplicatePaymentReferenceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $verification = $attempt->paymentVerifications()->where('category_payment_type_id', $type->id)->firstOrFail();

        return response()->json([
            'message' => 'Payment recorded.',
            'verification_id' => $verification->id,
            'receipt' => $verification->receipt_code_encrypted,
        ]);
    }

    private function validatedReceiptNumber(Request $request): string
    {
        return trim($request->validate([
            'reference_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
        ], [
            'reference_number.regex' => 'The receipt number may only contain letters, numbers, spaces, hyphens and slashes.',
        ], [
            'reference_number' => 'receipt number',
        ])['reference_number']);
    }

    /** PDF of the same sheets, one per page. */
    public function exportEvaluationSheetPdf(PresentationCategory $category, PresentationAttempt $attempt, GradePdfExporter $exporter)
    {
        $this->loadSheetAttempt($category, $attempt);
        $category->load(['academicYear', 'college']);

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

    private function loadSheetAttempt(PresentationCategory $category, PresentationAttempt $attempt): void
    {
        $attempt->load('researchGroup');

        if ($attempt->researchGroup?->category_id !== $category->id) {
            throw new NotFoundHttpException();
        }

        $attempt->load([
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
