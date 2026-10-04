<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CategoryAnnouncement;
use App\Models\CategoryPaymentType;
use App\Models\CategoryResearchTrack;
use App\Models\CategoryStatus;
use App\Models\EvaluationFormVersion;
use App\Models\AttemptSchedule;
use App\Models\PresentationCategory;
use App\Models\PresentationDateRoom;
use App\Models\PresentationMode;
use App\Models\QueueStrategy;
use App\Models\Semester;
use App\Services\CapacityAnalysisService;
use App\Services\CategoryDeletionService;
use App\Services\QueueGenerationService;
use App\Services\ResearchGroupRegistrationService;
use App\Support\CategorySetupLock;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = PresentationCategory::forAdminCollege()->with([
            'academicYear',
            'semester',
            'college',
            'categoryStatus',
            'presentationMode',
            'presentationDates.eventDateStatus',
            'categoryScheduleSetting',
            'categoryQueueSetting',
            'categoryEvaluationForms',
        ])->orderByDesc('created_at')->get();

        // Real-time-derived (registration window, event dates) — recompute on every
        // view, not just after a write, so status reflects the clock as it ticks.
        $categories->each(function (PresentationCategory $category) {
            $category->presentationDates->each->refreshStatus();
            $category->refreshStatus();
        });
        $categories->load('categoryStatus', 'presentationDates.eventDateStatus');

        // No manual trigger exists anywhere for queue generation — it fires
        // itself the moment a category becomes eligible, same as status
        // recomputation above (there's no scheduler/queue worker in this
        // app, so "automatic" means "checked on every Admin page load").
        $queueService = app(QueueGenerationService::class);
        $categories->each(fn (PresentationCategory $category) => $queueService->autoGenerateIfEligible($category, auth()->id()));

        $activeCategories = $categories->filter(fn (PresentationCategory $category) => $category->categoryStatus->code !== 'ARCHIVED')->values();
        $archivedCategories = $categories->filter(fn (PresentationCategory $category) => $category->categoryStatus->code === 'ARCHIVED')->values();

        return view('admin.categories.index', compact('activeCategories', 'archivedCategories'));
    }

    public function create()
    {
        return view('admin.categories.show', $this->workspaceData(null));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategoryInfo($request);

        $draftStatus = CategoryStatus::where('code', 'DRAFT')->firstOrFail();

        $category = PresentationCategory::create([
            ...$validated,
            ...$this->resolveIdentityIds($request),
            'created_by' => $request->user()->id,
            'category_status_id' => $draftStatus->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category created. The rest of the setup is now unlocked.',
                'redirect' => route('admin.categories.show', $category),
                'id' => $category->id,
            ]);
        }

        return redirect()->route('admin.categories.show', $category)->with('status', 'Category created. Continue configuring it below.');
    }

    public function show(PresentationCategory $category)
    {
        return view('admin.categories.show', $this->workspaceData($category));
    }

    public function update(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'update this category');

        $validated = $this->validateCategoryInfo($request);

        // The presentation mode is fixed once the category has actually
        // started (user-directed 2026-09-21) — see
        // PresentationCategory::hasStarted(). Everything else on this form
        // stays editable; only the mode is frozen.
        if ($category->hasStarted() && (int) $validated['presentation_mode_id'] !== (int) $category->presentation_mode_id) {
            throw ValidationException::withMessages([
                'presentation_mode_id' => 'Cannot change the presentation mode — this category has already started presenting. The mode stays as it is for the rest of the category.',
            ]);
        }

        $category->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Category information updated.']);
        }

        return back()->with('status', 'Category information updated.');
    }

    public function archive(PresentationCategory $category)
    {
        $archivedStatus = CategoryStatus::where('code', 'ARCHIVED')->firstOrFail();

        $category->update([
            // Snapshotted so unarchive() can restore it exactly — deriveStatus()
            // treats ARCHIVED as sticky, so nothing re-derives this on its own
            // once it's set below.
            'status_before_archive_id' => $category->category_status_id,
            'category_status_id' => $archivedStatus->id,
            'archived_at' => now(),
        ]);

        return redirect()->route('admin.categories.index')->with('status', 'Category archived.');
    }

    /**
     * User-directed 2026-09-17: an archived category can be brought back.
     * Restores status_before_archive_id exactly as it was the moment before
     * Archive was pressed — a category can be archived from any status (a
     * still-open one, or COMPLETED after it ended), and there's no other way
     * to recover which one it was, since deriveStatus() never re-derives a
     * sticky code (ARCHIVED/ACTIVE/COMPLETED) on its own. A category archived
     * before this snapshot existed has no value to restore; it falls back to
     * a fresh registration-window derivation instead (self-healing for every
     * status except COMPLETED, which can't be recovered retroactively — this
     * matches this app's existing "flag, don't guess" posture for data that
     * predates a feature).
     */
    public function unarchive(PresentationCategory $category)
    {
        if ($category->categoryStatus?->code !== 'ARCHIVED') {
            return back()->with('error', 'This category is not archived.');
        }

        $restoredStatus = $category->statusBeforeArchive ?? CategoryStatus::where('code', 'DRAFT')->firstOrFail();

        $category->update([
            'category_status_id' => $restoredStatus->id,
            'archived_at' => null,
            'status_before_archive_id' => null,
        ]);

        // Non-terminal statuses are always freshly derived from real time on
        // every page load anyway (registration window, setup completeness) —
        // do it immediately so the category doesn't show a stale snapshot
        // until the next page load. load() (not loadMissing()) forces a fresh
        // read of the relation just written above, matching the same
        // stale-cached-relation fix EventActivationService::start() needed.
        $category->load('categoryStatus');
        $category->refreshStatus();

        return redirect()->route('admin.categories.index')->with('status', 'Category unarchived.');
    }

    /**
     * Permanently removes a category and its own setup data (dates, rooms, breaks,
     * announcements, queue/schedule/payment settings, evaluation-form assignments).
     * Blocked if research groups have already registered under it — archive it
     * instead. An ended (or archived) category is the exception: it is deleted
     * with all of its records, groups, evaluations and grades included
     * (user-directed 2026-09-30).
     */
    public function destroy(PresentationCategory $category, CategoryDeletionService $deletion)
    {
        if (in_array($category->categoryStatus?->code, ['COMPLETED', 'ARCHIVED'], true)) {
            $result = $deletion->deleteCompletedCategory($category);

            return $result['ok']
                ? redirect()->route('admin.categories.index')->with('status', 'Category and all of its records deleted.')
                : redirect()->route('admin.categories.index')->with('error', $result['error']);
        }

        try {
            DB::transaction(function () use ($category) {
                $category->load('presentationDates');

                foreach ($category->presentationDates as $date) {
                    $date->deleteWithChildren();
                }

                $category->categoryAnnouncements()->delete();
                $category->categoryEvaluationForms()->delete();
                $category->categoryQueueSetting()->delete();
                $category->categoryScheduleSetting()->delete();
                $category->categoryPaymentSetting()->delete();
                $category->categoryRooms()->delete();

                $category->delete();
            });
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return redirect()->route('admin.categories.index')
                    ->with('error', 'Cannot delete this category — research groups are already registered under it. Archive it instead.');
            }

            throw $e;
        }

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    public function updateProjectInfoConfig(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'update the requirement checklist');

        $validated = $request->validate([
            'technical_adviser_required' => ['sometimes', 'boolean'],
            'research_track_required' => ['sometimes', 'boolean'],
        ]);

        $category->update([
            'technical_adviser_required' => $request->boolean('technical_adviser_required'),
            'research_track_required' => $request->boolean('research_track_required'),
            // Always public — not an admin-configurable option (user-directed 2026-09-15).
            'public_queue_visible' => true,
        ]);

        $category->refreshStatus();

        return $this->respond($request, 'Requirement checklist saved.');
    }

    /**
     * A single category-wide panel count, set once in Project Information
     * instead of per room in the Schedules tab. Saving it overwrites every
     * already-registered room's default (category_rooms.default_panelist_count)
     * and every already-placed room across every presentation date
     * (presentation_date_rooms.panelist_count) — user-directed: "applied to
     * all schedules and rooms once set".
     *
     * The only rooms left untouched are the ones that already produced a real
     * recorded outcome (any attempt in a terminal presentation_status), since
     * a finished presentation's real panel size shouldn't move retroactively.
     * This used to skip on the *date's* status (COMPLETED/CANCELLED) instead,
     * which was wrong for the case this codebase now creates routinely: a day
     * that auto-ended (§2.8.6) or auto-cancelled (§2.8.7) with unfinished
     * groups still parked in its rooms left those rooms permanently frozen at
     * the old count, so the still-pending groups could never be assigned the
     * new panel size. Whether a group has presented yet is the thing that
     * matters here, not what the calendar day it is parked on is labelled.
     */
    public function updatePanelistCountConfig(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'update the panel count');

        $validated = $request->validate([
            'panelist_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $panelistCount = $validated['panelist_count'] ?? null;
        $category->update(['panelist_count' => $panelistCount]);

        $applied = 0;
        $skippedLocked = 0;
        $skippedClosedDays = 0;

        if ($panelistCount !== null) {
            $category->categoryRooms()->update(['default_panelist_count' => $panelistCount]);

            $rooms = PresentationDateRoom::whereHas('presentationDate', fn ($query) => $query->where('category_id', $category->id))
                ->whereHas('roomUseStatus', fn ($query) => $query->where('code', '!=', 'REMOVED'))
                ->with('presentationDate.eventDateStatus')
                ->withCount(['attemptSchedules as recorded_outcome_count' => fn ($query) => $query
                    ->whereHas('presentationAttempt.presentationStatus', fn ($status) => $status->where('is_terminal', true))])
                ->get();

            foreach ($rooms as $room) {
                // Only days that can still run get the new count — the same
                // isOpenForScheduling() rule that already decides where rooms
                // may be added (§2.10.1) and which rooms Event Control still
                // lists. A finished/cancelled/passed-unstarted day's rooms
                // describe what was, not what's about to happen; groups still
                // parked in one are moved onto an open day, whose rooms carry
                // the new count.
                if (! $room->presentationDate->isOpenForScheduling()) {
                    $skippedClosedDays++;
                    continue;
                }

                if ($room->recorded_outcome_count > 0) {
                    $skippedLocked++;
                    continue;
                }

                $room->update(['panelist_count' => $panelistCount]);
                $applied++;
            }
        }

        $category->refreshStatus();

        $message = $panelistCount === null
            ? 'Panel count configuration cleared.'
            : "Panel count set to {$panelistCount} — applied to {$applied} room(s) on ongoing and upcoming dates.";

        if ($skippedClosedDays > 0) {
            $message .= " {$skippedClosedDays} room(s) on finished or past dates were left unchanged.";
        }

        if ($skippedLocked > 0) {
            $message .= " {$skippedLocked} room(s) with an already-recorded presentation were left unchanged.";
        }

        return $this->respond($request, $message);
    }

    public function updateScheduleConfig(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'update the schedule configuration');

        $validated = $request->validate([
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:32000'],
        ]);

        $category->update([
            'registration_opens_at' => $validated['registration_opens_at'] ?? null,
            'registration_closes_at' => $validated['registration_closes_at'] ?? null,
        ]);

        $category->categoryScheduleSetting()->updateOrCreate(
            ['category_id' => $category->id],
            [
                'duration_minutes' => $validated['duration_minutes'],
                // Extended time is always allowed — not an admin-configurable option (user-directed 2026-08-04).
                'allow_extended_time' => true,
            ]
        );

        $category->refreshStatus();

        return $this->respond($request, 'Schedule configuration saved.');
    }

    public function updateQueueConfig(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'update the queue configuration');

        $validated = $request->validate([
            'queue_strategy_id' => ['required', Rule::exists('queue_strategies', 'id')],
            'called_waiting_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        // Same-day reinsertion is always allowed — not an admin-configurable option (user-directed 2026-08-04).
        // Deferring late groups and marking unresolved groups absent at end of
        // day are likewise always on for every category (user-directed 2026-09-15).
        $validated['allow_same_day_reinsertion'] = true;
        $validated['late_defer_enabled'] = true;
        $validated['unresolved_absent_end_of_day'] = true;

        // Random Draw: the seed is drawn once, the first time the strategy is
        // saved, and kept on every later save so re-saving the tab never
        // re-rolls the order. Switching to another strategy discards it, so
        // coming back to Random Draw is a fresh draw.
        $settings = $category->categoryQueueSetting?->settings_json ?? [];
        $strategyCode = QueueStrategy::find($validated['queue_strategy_id'])->code;

        if ($strategyCode === 'RANDOM_DRAW') {
            $settings['random_draw_seed'] ??= bin2hex(random_bytes(32));
        } else {
            unset($settings['random_draw_seed']);
        }

        // Section Based: the Section Order field, same section format as
        // registration (4B). Blank segments are ignored; an empty order means
        // sections run A–Z. Like the draw seed, it only lives while the
        // category is on this strategy.
        if ($strategyCode === 'SECTION_BASED') {
            $settings['section_order'] = $this->validatedSectionOrder($request);
        } else {
            unset($settings['section_order']);
        }

        $validated['settings_json'] = $settings ?: null;

        $category->categoryQueueSetting()->updateOrCreate(
            ['category_id' => $category->id],
            $validated
        );

        $category->refreshStatus();

        // Changing the strategy has no effect on its own until this runs — it
        // rebuilds the queue in place if one already exists, but only while
        // no presentation date has started yet (registration being open no
        // longer blocks this — see QueueGenerationService's own notes).
        $regenOutcome = app(QueueGenerationService::class)->regenerateIfStrategyChanged($category, auth()->id());

        $message = match ($regenOutcome['state']) {
            'regenerated' => 'Queue configuration saved. The queue was automatically regenerated to match.',
            'blocked' => "Queue configuration saved. The existing queue was left unchanged because {$regenOutcome['reason']}.",
            default => 'Queue configuration saved.',
        };

        return $this->respond($request, $message);
    }

    private function validatedSectionOrder(Request $request): array
    {
        $order = collect((array) $request->input('section_order', []))
            ->map(fn ($section) => ResearchGroupRegistrationService::normalizeSection(is_string($section) ? $section : ''))
            ->filter(fn ($section) => $section !== '')
            ->values();

        foreach ($order as $section) {
            if (! preg_match(ResearchGroupRegistrationService::SECTION_PATTERN, $section)) {
                throw ValidationException::withMessages([
                    'section_order' => "\"{$section}\" is not a section. ".ResearchGroupRegistrationService::SECTION_FORMAT_MESSAGE,
                ]);
            }
        }

        $duplicate = $order->duplicates()->first();

        if ($duplicate !== null) {
            throw ValidationException::withMessages(['section_order' => "{$duplicate} is listed more than once."]);
        }

        return $order->all();
    }

    /**
     * User-directed 2026-09-20: payment verification required is a checklist
     * item first — nothing else on this form renders until it's checked.
     * Once checked, at least one named payment type is required
     * (payment_types[], e.g. "Defense Fee") — a category can require more
     * than one, added/removed via the tab's own "+ Add Payment Type"
     * button — and the verification instructions textarea only shows (and
     * is only saved) alongside them.
     */
    public function updatePaymentConfig(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'update the payment configuration');

        $paymentRequired = $request->boolean('payment_required');

        $validated = $request->validate([
            'verification_instructions' => ['nullable', 'string'],
            'payment_types' => [Rule::requiredIf($paymentRequired), 'array', 'min:1'],
            // "the field will hold number and letters" — letters, numbers,
            // and spaces only, so a name like "Defense Fee" or "OR Fee 2"
            // is valid but stray punctuation isn't.
            'payment_types.*' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9 ]+$/'],
            // Parallel to payment_types[] — the id of the existing row a
            // renamed field belongs to, blank for a newly added one via
            // "+ Add Payment Type". Lets a rename/reorder/remove be told
            // apart from the position-only guess a plain name list would
            // force, so an already-referenced row is never mistaken for a
            // brand new (or a different existing) one.
            'payment_type_ids' => ['sometimes', 'array'],
            'payment_type_ids.*' => ['nullable', 'integer'],
        ]);

        $category->categoryPaymentSetting()->updateOrCreate(
            ['category_id' => $category->id],
            [
                'payment_required' => $paymentRequired,
                'verification_instructions' => $paymentRequired ? ($validated['verification_instructions'] ?? null) : null,
                // Referring payment concerns to an administrator is always allowed — not an admin-configurable option (user-directed 2026-08-04).
                'allow_admin_referral' => true,
            ]
        );

        $this->syncPaymentTypes(
            $category,
            $paymentRequired ? array_values($validated['payment_types']) : [],
            $paymentRequired ? array_values($validated['payment_type_ids'] ?? []) : []
        );

        $this->syncPaymentAnnouncement(
            $category,
            $paymentRequired ? trim((string) ($validated['verification_instructions'] ?? '')) : '',
            $request->user()->id
        );

        return $this->respond($request, 'Payment verification configuration saved.');
    }

    /**
     * The verification instructions are published as the category's own
     * announcement (one row, keyed by source) so students and panelists see
     * them wherever announcements show. The text is edited only here; an
     * admin's Activate/Deactivate choice on the announcement survives a
     * re-save. No instructions, or payment turned off, removes it.
     */
    private function syncPaymentAnnouncement(PresentationCategory $category, string $instructions, int $userId): void
    {
        $announcement = $category->categoryAnnouncements()
            ->where('source', CategoryAnnouncement::SOURCE_PAYMENT)
            ->first();

        if ($instructions === '') {
            $announcement?->delete();

            return;
        }

        if ($announcement) {
            $announcement->update(['message' => $instructions]);

            return;
        }

        $category->categoryAnnouncements()->create([
            'title' => CategoryAnnouncement::PAYMENT_INSTRUCTIONS_TITLE,
            'message' => $instructions,
            'source' => CategoryAnnouncement::SOURCE_PAYMENT,
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    /**
     * Rows already referenced by a real payment_verifications row (a Lead
     * verified it, or an Admin resolved it) are kept even when the admin
     * removes them from the list here — deleting them would silently lose
     * which fee an already-recorded reference number was for. Matched by
     * payment_type_ids[] (blank for a new row) rather than by position, so
     * a rename/reorder/removal can't be confused with each other.
     */
    private function syncPaymentTypes(PresentationCategory $category, array $names, array $ids): void
    {
        $existingById = $category->categoryPaymentTypes->keyBy('id');
        $keptIds = [];

        foreach ($names as $index => $name) {
            $id = $ids[$index] ?? null;
            $existing = $id ? $existingById->get((int) $id) : null;

            if ($existing) {
                $existing->update(['name' => $name, 'sort_order' => $index]);
                $keptIds[] = $existing->id;
            } else {
                $created = $category->categoryPaymentTypes()->create(['name' => $name, 'sort_order' => $index]);
                $keptIds[] = $created->id;
            }
        }

        $category->categoryPaymentTypes()
            ->whereNotIn('id', $keptIds)
            ->withCount('paymentVerifications')
            ->get()
            ->each(fn (CategoryPaymentType $type) => $type->payment_verifications_count === 0 ? $type->delete() : null);
    }

    public function updateEvaluationConfig(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'assign an evaluation form');

        $validated = $request->validate([
            'evaluation_form_version_id' => ['required', Rule::exists('evaluation_form_versions', 'id')],
        ]);

        // Only a form of the category's own college (AdminCollege).
        abort_unless(
            EvaluationFormVersion::whereKey($validated['evaluation_form_version_id'])
                ->whereHas('evaluationForm', fn ($q) => $q->where('college_id', $category->college_id))
                ->exists(),
            422,
            'That evaluation form belongs to another college.'
        );

        $category->categoryEvaluationForms()->update(['effective_until' => now()]);

        $category->categoryEvaluationForms()->create([
            'evaluation_form_version_id' => $validated['evaluation_form_version_id'],
            'effective_from' => now(),
            'assigned_by' => $request->user()->id,
        ]);

        $category->refreshStatus();

        return $this->respond($request, 'Evaluation form assigned.');
    }

    /**
     * A track's own evaluation form (user-directed 2026-10-04), used instead
     * of the category-wide form while the category requires a track. A group
     * already started keeps the sheet it was given.
     */
    public function updateTrackEvaluationConfig(Request $request, PresentationCategory $category, CategoryResearchTrack $track)
    {
        abort_unless($track->category_id === $category->id && $track->is_active, 404);
        CategorySetupLock::guard($category, 'assign an evaluation form');

        $validated = $request->validate([
            'evaluation_form_version_id' => ['required', Rule::exists('evaluation_form_versions', 'id')],
        ]);

        abort_unless(
            EvaluationFormVersion::whereKey($validated['evaluation_form_version_id'])
                ->whereHas('evaluationForm', fn ($q) => $q->where('college_id', $category->college_id))
                ->exists(),
            422,
            'That evaluation form belongs to another college.'
        );

        $track->update(['evaluation_form_version_id' => $validated['evaluation_form_version_id']]);

        $category->refreshStatus();

        return $this->respond($request, "Evaluation form assigned to the {$track->name} track.");
    }

    private function respond(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    private function validateCategoryInfo(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'subject_or_research_type' => ['nullable', 'string', 'max:150'],
            'presentation_mode_id' => ['required', Rule::exists('presentation_modes', 'id')],
            'required_proposed_title_count' => ['nullable', 'integer', 'min:1', 'max:255'],
            'maximum_members' => ['required', 'integer', 'min:1', 'max:255'],
        ]);

        $mode = PresentationMode::findOrFail($validated['presentation_mode_id']);

        if ($mode->code !== 'TITLE_PROPOSAL') {
            $validated['required_proposed_title_count'] = null;
        } elseif (empty($validated['required_proposed_title_count'])) {
            $validated['required_proposed_title_count'] = 1;
        }

        return $validated;
    }

    /**
     * Academic Year, Semester, and College are no longer chosen per-category —
     * Academic Year/Semester come from the single "active" row managed by the
     * Super Administrator in Application Settings, and College comes from the
     * creating Admin's own administratorProfile, assigned by the Super
     * Administrator (see CLAUDE.md's 2026-08-04 Decision Log).
     */
    private function resolveIdentityIds(Request $request): array
    {
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();
        $collegeId = $request->user()->administratorProfile?->college_id;

        $missing = array_filter([
            'an active Academic Year' => ! $activeAcademicYear,
            'an active Semester' => ! $activeSemester,
            'a College assigned to your Admin account' => ! $collegeId,
        ]);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'name' => 'Cannot create a category: missing '.implode(', ', array_keys($missing)).'. Ask your Super Administrator to set this up.',
            ]);
        }

        return [
            'academic_year_id' => $activeAcademicYear->id,
            'semester_id' => $activeSemester->id,
            'college_id' => $collegeId,
        ];
    }

    private function workspaceData(?PresentationCategory $category): array
    {
        $queueOutcome = ['state' => 'not_eligible'];
        $queueAssignments = collect();
        $capacityAnalysis = ['configured' => false];

        if ($category) {
            $category->load([
                'academicYear',
                'semester',
                'college',
                'categoryStatus',
                'presentationMode',
                'categoryQueueSetting.queueStrategy',
                'categoryScheduleSetting',
                'categoryPaymentSetting',
                'categoryPaymentTypes',
                'categoryRooms' => fn ($query) => $query->where('is_active', true)->orderBy('room_name'),
                'categoryRooms.researchTracks',
                'researchTracks.evaluationFormVersion.evaluationForm',
                'categoryEvaluationForms.evaluationFormVersion.evaluationForm',
                'categoryAnnouncements' => fn ($query) => $query->orderByDesc('created_at'),
                'presentationDates' => fn ($query) => $query->chronological(),
                'presentationDates.eventDateStatus',
                'presentationDates.presentationDateRooms.roomUseStatus',
                'presentationDates.presentationDateRooms.scheduleBreaks',
                'presentationDates.presentationDateRooms.attemptSchedules',
                // Room removal needs to name the groups still queued in the room
                // it's about to close, and refuse outright while one of them is
                // actually presenting — see the Rooms cell in partials/schedule.blade.php.
                'presentationDates.presentationDateRooms.attemptSchedules.queueEntry',
                'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.presentationStatus',
                'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.researchGroup',
            ]);

            // Real-time-derived, not manually settable — recompute on every view (mirrors PresentationCategory::refreshStatus()).
            $category->presentationDates->each->refreshStatus();
            $category->refreshStatus();
            $category->load('presentationDates.eventDateStatus', 'categoryStatus');

            // No manual "Generate Queue" button exists — this is the other half
            // of the auto-trigger (see index()): fires the moment this specific
            // category becomes eligible, checked on every workspace page load.
            $queueOutcome = app(QueueGenerationService::class)->autoGenerateIfEligible($category, auth()->id());

            if ($queueOutcome['state'] === 'generated') {
                $queueAssignments = AttemptSchedule::whereHas(
                    'presentationAttempt.researchGroup',
                    fn ($query) => $query->where('category_id', $category->id)
                )
                    ->with(['presentationAttempt.researchGroup.students', 'presentationDateRoom', 'queueEntry'])
                    ->get()
                    ->sortBy(fn ($schedule) => $schedule->queueEntry->queue_number)
                    ->values();
            }

            // Rough, always-on health check — recomputed on every view, same
            // as queue auto-generation above; not the precise round-robin
            // math QueueGenerationService::plan() uses at actual generation
            // time, just an early warning while setup is still in progress.
            $capacityAnalysis = app(CapacityAnalysisService::class)->analyze($category);
        }

        return [
            'category' => $category,
            'activeAcademicYear' => AcademicYear::where('is_active', true)->first(),
            'activeSemester' => Semester::where('is_active', true)->first(),
            'ownCollege' => auth()->user()->administratorProfile?->college,
            'presentationModes' => PresentationMode::orderBy('name')->get(),
            'queueStrategies' => QueueStrategy::where('is_active', true)->orderBy('name')->get(),
            // A version with no applicable_presentation_modes rows at all predates this
            // restriction (e.g. the original seeded form) and is treated as unrestricted,
            // rather than silently disappearing from every category's dropdown. Only
            // meaningful once a category (and its mode) exists — the Evaluation
            // Configuration tab is locked on the create() screen, so this stays empty
            // for a not-yet-created category rather than querying a null mode id.
            'availableFormVersions' => $category
                ? EvaluationFormVersion::with('evaluationForm')
                    ->whereHas('status', fn ($query) => $query->where('code', 'ACTIVE'))
                    // Only the category's own college's forms (AdminCollege).
                    ->whereHas('evaluationForm', fn ($query) => $query->where('college_id', $category->college_id))
                    ->where(function ($query) use ($category) {
                        $query->whereDoesntHave('applicablePresentationModes')
                            ->orWhereHas('applicablePresentationModes', fn ($q) => $q->where('presentation_modes.id', $category->presentation_mode_id));
                    })
                    ->get()
                : collect(),
            'queueOutcome' => $queueOutcome,
            'queueAssignments' => $queueAssignments,
            'capacityAnalysis' => $capacityAnalysis,
        ];
    }
}
