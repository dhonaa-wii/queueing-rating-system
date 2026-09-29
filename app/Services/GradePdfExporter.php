<?php

namespace App\Services;

use App\Models\EvaluationLetterhead;
use App\Models\PresentationCategory;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * PDF export of the Generated Grades table (user-directed 2026-09-25): the
 * same rows and filters as the page and the Excel export, minus the
 * Evaluation column (a View button, nothing to print), under the college's
 * letterhead, with a muted centered "ARPQRS · college · academic year" line
 * repeated at the foot of every page. Also renders the recommendations
 * summary, which shares that letterhead/footer chrome.
 */
class GradePdfExporter
{
    private const LOGO_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    /**
     * $paymentTypes become trailing columns named after each type, like the
     * page and the spreadsheet. Wide tables (more than two payment types) go
     * landscape so the columns don't get squeezed.
     */
    public function render(
        PresentationCategory $category,
        Collection $rows,
        ?string $filterLabel,
        Collection $paymentTypes,
        ?EvaluationLetterhead $letterhead,
    ): string {
        $showLetterhead = $letterhead && $letterhead->hasContent();

        $html = view('admin.reports.grades-pdf', [
            'category' => $category,
            'rows' => $rows,
            'filterLabel' => $filterLabel,
            'paymentTypes' => $paymentTypes,
            'letterhead' => $showLetterhead ? $letterhead : null,
            'logo' => $showLetterhead ? $this->logoDataUri($letterhead->logo_path) : null,
            'secondaryLogo' => $showLetterhead ? $this->logoDataUri($letterhead->secondary_logo_path) : null,
            'footerText' => $this->footerText($category),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])->render();

        return $this->toPdf($html, $paymentTypes->count() > 2 ? 'landscape' : 'portrait');
    }

    /**
     * The recommendations & comments summary (one row per completed group, in
     * presentation order) under the same letterhead and footer.
     */
    public function renderRecommendations(
        PresentationCategory $category,
        Collection $rows,
        ?EvaluationLetterhead $letterhead,
    ): string {
        $showLetterhead = $letterhead && $letterhead->hasContent();

        $html = view('admin.reports.recommendations-pdf', [
            'category' => $category,
            'rows' => $rows,
            'letterhead' => $showLetterhead ? $letterhead : null,
            'logo' => $showLetterhead ? $this->logoDataUri($letterhead->logo_path) : null,
            'secondaryLogo' => $showLetterhead ? $this->logoDataUri($letterhead->secondary_logo_path) : null,
            'footerText' => $this->footerText($category),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])->render();

        return $this->toPdf($html, 'portrait');
    }

    /**
     * The top-5 list behind a Best card — $type is 'groups' (by Group Grade)
     * or 'presenters' (by Individual Grade Avg.), $rows already ranked by
     * GradeReportService.
     */
    public function renderTop(
        PresentationCategory $category,
        string $type,
        Collection $rows,
        ?EvaluationLetterhead $letterhead,
    ): string {
        $showLetterhead = $letterhead && $letterhead->hasContent();

        $html = view('admin.reports.top-pdf', [
            'category' => $category,
            'type' => $type,
            'rows' => $rows,
            'letterhead' => $showLetterhead ? $letterhead : null,
            'logo' => $showLetterhead ? $this->logoDataUri($letterhead->logo_path) : null,
            'secondaryLogo' => $showLetterhead ? $this->logoDataUri($letterhead->secondary_logo_path) : null,
            'footerText' => $this->footerText($category),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])->render();

        return $this->toPdf($html, 'portrait');
    }

    /**
     * The panelist sign-off sheet (see PanelistDaySheetService::sheet()) under
     * the same letterhead and footer.
     */
    public function renderPanelistSheet(
        PresentationCategory $category,
        object $sheet,
        ?EvaluationLetterhead $letterhead,
    ): string {
        $showLetterhead = $letterhead && $letterhead->hasContent();

        $html = view('admin.reports.panelist-sheet-pdf', [
            'category' => $category,
            'sheet' => $sheet,
            'letterhead' => $showLetterhead ? $letterhead : null,
            'logo' => $showLetterhead ? $this->logoDataUri($letterhead->logo_path) : null,
            'secondaryLogo' => $showLetterhead ? $this->logoDataUri($letterhead->secondary_logo_path) : null,
            'footerText' => $this->footerText($category),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])->render();

        return $this->toPdf($html, 'portrait');
    }

    /**
     * Every submitted evaluation sheet one completed attempt's panel filed, one
     * per page, each under its own form's letterhead setting, with the same
     * footer as the other exports. $attempt must already carry the relations
     * ReportController::loadSheetAttempt() loads.
     */
    public function renderEvaluationSheets(
        PresentationCategory $category,
        \App\Models\PresentationAttempt $attempt,
        Collection $submissions,
        Collection $leadPanelistIds,
        ?EvaluationLetterhead $letterhead,
    ): string {
        $showLetterhead = $letterhead && $letterhead->hasContent();

        $html = view('admin.reports.evaluation-sheets-pdf', [
            'attempt' => $attempt,
            'submissions' => $submissions,
            'leadPanelistIds' => $leadPanelistIds,
            'letterhead' => $showLetterhead ? $letterhead : null,
            'logo' => $showLetterhead ? $this->logoDataUri($letterhead->logo_path) : null,
            'secondaryLogo' => $showLetterhead ? $this->logoDataUri($letterhead->secondary_logo_path) : null,
            'footerText' => $this->footerText($category),
        ])->render();

        return $this->toPdf($html, 'portrait');
    }

    private function toPdf(string $html, string $orientation): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /** "ARPQRS · College of … · 2025-2026" — whichever parts exist. */
    private function footerText(PresentationCategory $category): string
    {
        return implode(' · ', array_filter([
            'ARPQRS',
            $category->college->name ?? null,
            $category->academicYear->name ?? null,
        ]));
    }

    /**
     * Inlined as a data URI: Dompdf has no way to fetch a `/storage/...` URL
     * with remote access off, and reading the file directly is what keeps it
     * off.
     */
    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $type = self::LOGO_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        return $type
            ? 'data:' . $type . ';base64,' . base64_encode(Storage::disk('public')->get($path))
            : null;
    }
}
