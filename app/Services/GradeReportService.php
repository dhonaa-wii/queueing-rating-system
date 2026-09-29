<?php

namespace App\Services;

use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Generated Grades report — first piece of Reports & Analytics. User-directed
 * formula: total = 30% individual grade average + 70% group grade. Only
 * meaningful for Standard-mode categories: weighted_total_score (the group
 * component) and evaluation_submission_student_scores (the individual
 * component) both stay null for Title Proposal forms — see
 * EvaluationSubmissionService::recomputeTotals()/saveStudentScore() doc
 * comments. Computed fresh on every request, nothing persisted — same
 * "compute on demand" convention already used by CapacityAnalysisService.
 */
class GradeReportService
{
    public const UNASSIGNED_SECTION = '__unassigned__';

    public const SORT_NAME = 'name';
    public const SORT_LAST_NAME = 'last_name';

    public function __construct(
        private readonly PaymentVerificationService $paymentVerificationService,
    ) {
    }

    /**
     * The category's configured payment types, for the Payment column(s) —
     * empty when the category doesn't require payment at all, so a view can
     * key showing the column(s) off whether this is empty rather than
     * re-checking payment_required itself.
     */
    public function paymentTypesFor(PresentationCategory $category): Collection
    {
        if (! $this->paymentVerificationService->isRequired($category)) {
            return collect();
        }

        return $this->paymentVerificationService->typesFor($category);
    }

    public function sectionsFor(PresentationCategory $category): Collection
    {
        return $category->researchGroups()
            ->with('students')
            ->get()
            ->flatMap->students
            ->pluck('section_name')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function hasUnassignedSection(PresentationCategory $category): bool
    {
        return $category->researchGroups()
            ->whereHas('students', fn ($q) => $q->whereNull('section_name'))
            ->exists();
    }

    /**
     * Every presentation day of the category that at least one completed
     * attempt was presented on, latest first — the Date filter's options.
     * Filtering by a day means "every group that presented in any of that
     * day's rooms" (user-directed 2026-09-17), which is why this is keyed on
     * the day and not on a single room.
     */
    public function datesFor(PresentationCategory $category): Collection
    {
        // Taken from the report's own rows rather than re-derived from the
        // schedule: generate() resolves a group's day through the room
        // session it actually presented in, and drops a group still awaiting
        // a re-defense entirely. Deriving the options separately let the
        // dropdown offer a day with no rows behind it — and miss the day a
        // carried-over group really presented on.
        $dateIds = $this->generate($category)->pluck('presentation_date_id')->filter()->unique();

        return PresentationDate::whereIn('id', $dateIds)
            ->orderByDesc('presentation_date')
            ->get();
    }

    /**
     * One row per registered student whose group has actually presented.
     * Groups with no COMPLETED attempt are excluded entirely (user-directed
     * 2026-09-13) — this report is about results, so a group that hasn't
     * presented has nothing to report here. A completed group whose panel
     * hasn't submitted evaluations yet still gets rows, with null grade
     * fields.
     *
     * $filters (all optional, user-directed 2026-09-17):
     *   section — one section name, or self::UNASSIGNED_SECTION for students
     *             with no section recorded
     *   search  — case-insensitive substring of the student's name
     *   date    — presentation_dates.id the group presented on
     *   sort    — self::SORT_NAME (section, then name) or SORT_LAST_NAME
     */
    public function generate(PresentationCategory $category, array $filters = []): Collection
    {
        $section = $filters['section'] ?? null;
        $search = Str::lower(trim((string) ($filters['search'] ?? '')));
        $dateId = ($filters['date'] ?? null) !== null && $filters['date'] !== '' ? (int) $filters['date'] : null;
        $sort = $filters['sort'] ?? self::SORT_NAME;

        $category->loadMissing([
            'categoryPaymentSetting',
            'categoryPaymentTypes',
            'researchGroups.students',
            'researchGroups.presentationAttempts' => fn ($q) => $q->orderByDesc('attempt_number'),
            'researchGroups.presentationAttempts.presentationStatus',
            'researchGroups.presentationAttempts.finalOutcome',
            'researchGroups.presentationAttempts.attemptSchedule.presentationDateRoom.presentationDate',
            'researchGroups.presentationAttempts.presentationRun.roomSession.presentationDateRoom.presentationDate',
            'researchGroups.presentationAttempts.evaluationSubmissions' => fn ($q) => $q->whereHas(
                'submissionStatus',
                fn ($sq) => $sq->whereIn('code', ['SUBMITTED', 'FINALIZED'])
            ),
            'researchGroups.presentationAttempts.evaluationSubmissions.studentScores',
            'researchGroups.presentationAttempts.paymentVerifications.paymentStatus',
        ]);

        $paymentTypes = $this->paymentTypesFor($category);
        $rows = collect();

        foreach ($category->researchGroups as $group) {
            $gradedAttempt = $group->presentationAttempts
                ->first(fn ($attempt) => $attempt->presentationStatus?->code === 'COMPLETED');

            // A Re-Defense verdict isn't a final grade — the group has
            // another attempt ahead of it, and only that attempt's result
            // (a pass or FAILED) belongs in this report. User-directed
            // 2026-09-13. Its own evaluations stay reachable from Group &
            // Panel Assignment's Re-Defense tab.
            if ($gradedAttempt === null || $gradedAttempt->finalOutcome?->requires_new_attempt) {
                continue;
            }

            // The room the group actually presented in: its run's own room
            // session. A completed attempt never relocates
            // (QueueAdjustmentService refuses to move a terminal one), but an
            // attempt that presented on one day and was only completed days
            // later was carried to a later day's room in between — so its
            // current placement can name a room it never presented in. The
            // schedule is the fallback for an Admin "Mark Complete" override,
            // which records no run. Same rule as PanelistDaySheetService and
            // ScheduleAnalyticsService.
            $room = $gradedAttempt->presentationRun?->roomSession?->presentationDateRoom
                ?? $gradedAttempt->attemptSchedule?->presentationDateRoom;
            $presentationDate = $room?->presentationDate;

            if ($dateId !== null && $presentationDate?->id !== $dateId) {
                continue;
            }

            $submissions = $gradedAttempt->evaluationSubmissions;

            $groupGrade = $submissions
                ->pluck('weighted_total_score')
                ->filter(fn ($value) => $value !== null);
            $groupGrade = $groupGrade->isNotEmpty() ? round($groupGrade->avg(), 2) : null;

            $studentScoresBySubmission = $submissions->flatMap->studentScores;

            // One entry per configured payment type — category_payment_type_id
            // => whether it's been verified/resolved for this group's graded
            // attempt. Empty when the category doesn't require payment.
            $payment = $paymentTypes->mapWithKeys(function ($type) use ($gradedAttempt) {
                $verification = $gradedAttempt->paymentVerifications->firstWhere('category_payment_type_id', $type->id);

                return [$type->id => in_array($verification?->paymentStatus?->code, ['VERIFIED', 'RESOLVED'], true)];
            });

            foreach ($group->students as $student) {
                if (! $this->matchesSection($student->section_name, $section)) {
                    continue;
                }

                if ($search !== '' && ! $this->matchesSearch($student, $search)) {
                    continue;
                }

                $individualScores = $studentScoresBySubmission
                    ->where('student_id', $student->id)
                    ->pluck('score')
                    ->filter(fn ($value) => $value !== null);
                $individualAvg = $individualScores->isNotEmpty() ? round($individualScores->avg(), 2) : null;

                $total = ($individualAvg !== null && $groupGrade !== null)
                    ? round(($individualAvg * 0.30) + ($groupGrade * 0.70), 2)
                    : null;

                $rows->push([
                    'student_id' => $student->id,
                    'name' => $student->full_name,
                    'last_name' => (string) $student->last_name,
                    'section' => $student->section_name,
                    'presentation_date_id' => $presentationDate?->id,
                    'presentation_date' => $presentationDate?->presentation_date,
                    'room_name' => $room?->room_name,
                    'group_reference' => $group->group_reference,
                    'project_title' => $group->current_project_title,
                    'attempt_id' => $gradedAttempt->id,
                    'submission_count' => $submissions->count(),
                    // The attempt's official outcome (the Lead/Chair's
                    // verdict — EvaluationSubmissionService::leadOutcomeFor()).
                    // A FAILED group still belongs in this report with its
                    // computed grades, just marked as failed and barred from
                    // another attempt — user-directed 2026-09-13.
                    'outcome' => $gradedAttempt->finalOutcome?->name,
                    'outcome_failed' => $gradedAttempt->finalOutcome?->is_successful === false,
                    'attempt_number' => $gradedAttempt->attempt_number,
                    'individual_avg' => $individualAvg,
                    'group_grade' => $groupGrade,
                    'total' => $total,
                    'payment' => $payment,
                ]);
            }
        }

        // Multi-key sortBy() takes [key, direction] pairs or two-argument
        // comparators — single-argument closures were being treated as
        // comparators, so rows never actually sorted by section.
        if ($sort === self::SORT_LAST_NAME) {
            return $rows->sortBy([
                fn ($a, $b) => strnatcasecmp($a['last_name'], $b['last_name']),
                fn ($a, $b) => ($b['total'] ?? -1) <=> ($a['total'] ?? -1),
                fn ($a, $b) => strnatcasecmp($a['name'], $b['name']),
            ])->values();
        }

        return $rows->sortBy([
            fn ($a, $b) => strnatcasecmp($a['section'] ?? '', $b['section'] ?? ''),
            fn ($a, $b) => strnatcasecmp($a['name'], $b['name']),
        ])->values();
    }

    /**
     * Running Best Group for the whole category (user-directed 2026-09-13):
     * the highest Group Grade among every group this report lists, recomputed
     * on each read so it moves as evaluations come in. Every group sharing
     * that top grade is returned — a tie shows them all. A FAILED group is
     * never a best group, and a group with no submitted grade yet can't rank.
     * Re-Defense groups are already absent from generate().
     */
    public function bestGroups(PresentationCategory $category): Collection
    {
        return $this->topGroups($category, 1);
    }

    /**
     * Running Best Presenter: the highest Individual Grade Avg. among every
     * student this report lists (user-directed 2026-09-25). Same rules as
     * bestGroups() — ties all returned, FAILED groups and students with no
     * individual grade yet never rank.
     */
    public function bestPresenters(PresentationCategory $category): Collection
    {
        return $this->topPresenters($category, 1);
    }

    /**
     * The top $limit groups by Group Grade, ranked (1, 2, 2, 4 …). Whoever is
     * tied with the last place is kept, so a tie is never cut in half — the
     * list can run past $limit only by exactly those ties.
     */
    public function topGroups(PresentationCategory $category, int $limit = 5): Collection
    {
        $groups = $this->generate($category)
            ->groupBy('group_reference')
            ->map(fn (Collection $members) => (object) [
                'group_reference' => $members->first()['group_reference'],
                'project_title' => $members->first()['project_title'],
                'members' => $members->pluck('name')->values(),
                'group_grade' => $members->first()['group_grade'],
                'failed' => $members->first()['outcome_failed'],
                'attempt_id' => $members->first()['attempt_id'],
            ])
            ->reject(fn ($group) => $group->failed || $group->group_grade === null)
            ->values();

        return $this->rank($groups, 'group_grade', 'group_reference', $limit);
    }

    /** The top $limit students by Individual Grade Avg., ranked like topGroups(). */
    public function topPresenters(PresentationCategory $category, int $limit = 5): Collection
    {
        $presenters = $this->generate($category)
            ->reject(fn ($row) => $row['outcome_failed'] || $row['individual_avg'] === null)
            ->map(fn ($row) => (object) [
                'name' => $row['name'],
                'section' => $row['section'],
                'group_reference' => $row['group_reference'],
                'project_title' => $row['project_title'],
                'individual_avg' => $row['individual_avg'],
                'group_grade' => $row['group_grade'],
                'total' => $row['total'],
                'attempt_id' => $row['attempt_id'],
            ])
            ->values();

        return $this->rank($presenters, 'individual_avg', 'name', $limit);
    }

    /**
     * Highest first, competition-ranked on the score at 2 dp, cut at $limit
     * rows but never through a tie. Each item gains a `rank`.
     */
    private function rank(Collection $items, string $scoreKey, string $tieKey, int $limit): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $sorted = $items
            ->sort(function ($a, $b) use ($scoreKey, $tieKey) {
                $byScore = round($b->{$scoreKey}, 2) <=> round($a->{$scoreKey}, 2);

                return $byScore !== 0 ? $byScore : strnatcasecmp((string) $a->{$tieKey}, (string) $b->{$tieKey});
            })
            ->values();

        $cutoff = round($sorted->get(min($limit, $sorted->count()) - 1)->{$scoreKey}, 2);
        $previous = null;
        $rank = 0;

        return $sorted
            ->filter(fn ($item) => round($item->{$scoreKey}, 2) >= $cutoff)
            ->values()
            ->each(function ($item, $index) use ($scoreKey, &$previous, &$rank) {
                $score = round($item->{$scoreKey}, 2);

                if ($score !== $previous) {
                    $rank = $index + 1;
                    $previous = $score;
                }

                $item->rank = $rank;
            });
    }

    /** Every word of the search must appear in the student's first, middle or last name. */
    private function matchesSearch($student, string $search): bool
    {
        $haystack = Str::lower(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));

        foreach (preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            if (! str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    private function matchesSection(?string $studentSection, ?string $filter): bool
    {
        if ($filter === null || $filter === '') {
            return true;
        }

        if ($filter === self::UNASSIGNED_SECTION) {
            return $studentSection === null;
        }

        return $studentSection === $filter;
    }
}
