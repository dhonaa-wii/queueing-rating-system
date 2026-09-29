<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PresentationCategory extends Model
{
    protected $fillable = [
        'created_by',
        'academic_year_id',
        'semester_id',
        'college_id',
        'name',
        'description',
        'subject_or_research_type',
        'category_status_id',
        'presentation_mode_id',
        'required_proposed_title_count',
        'registration_opens_at',
        'registration_closes_at',
        'maximum_members',
        'panelist_count',
        'technical_adviser_required',
        'research_track_required',
        'public_queue_visible',
        'archived_at',
        'status_before_archive_id',
    ];

    protected function casts(): array
    {
        return [
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'technical_adviser_required' => 'boolean',
            'research_track_required' => 'boolean',
            'public_queue_visible' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function categoryStatus(): BelongsTo
    {
        return $this->belongsTo(CategoryStatus::class);
    }

    /**
     * The status this category held right before it was archived — set by
     * CategoryController::archive(), read back by unarchive() to restore it
     * exactly. Null for a category archived before this existed.
     */
    public function statusBeforeArchive(): BelongsTo
    {
        return $this->belongsTo(CategoryStatus::class, 'status_before_archive_id');
    }

    public function presentationMode(): BelongsTo
    {
        return $this->belongsTo(PresentationMode::class);
    }

    public function categoryAnnouncements(): HasMany
    {
        return $this->hasMany(CategoryAnnouncement::class, 'category_id');
    }

    public function presentationDates(): HasMany
    {
        return $this->hasMany(PresentationDate::class, 'category_id');
    }

    public function categoryRooms(): HasMany
    {
        return $this->hasMany(CategoryRoom::class, 'category_id');
    }

    public function researchTracks(): HasMany
    {
        return $this->hasMany(CategoryResearchTrack::class, 'category_id')->orderBy('name');
    }

    /** Active track names in display order: the registration dropdown's options. */
    public function activeTrackNames(): array
    {
        return $this->researchTracks()->where('is_active', true)->pluck('name')->all();
    }

    public function researchGroups(): HasMany
    {
        return $this->hasMany(ResearchGroup::class, 'category_id');
    }

    public function categoryEvaluationForms(): HasMany
    {
        return $this->hasMany(CategoryEvaluationForm::class, 'category_id');
    }

    public function categoryQueueSetting(): HasOne
    {
        return $this->hasOne(CategoryQueueSetting::class, 'category_id');
    }

    public function categoryScheduleSetting(): HasOne
    {
        return $this->hasOne(CategoryScheduleSetting::class, 'category_id');
    }

    public function categoryPaymentSetting(): HasOne
    {
        return $this->hasOne(CategoryPaymentSetting::class, 'category_id');
    }

    public function categoryPaymentTypes(): HasMany
    {
        return $this->hasMany(CategoryPaymentType::class, 'category_id')->orderBy('sort_order');
    }

    public function isRegistrationConfigured(): bool
    {
        return ! is_null($this->registration_opens_at) && ! is_null($this->registration_closes_at);
    }

    /**
     * DRAFT (registration dates not even configured yet) isn't ready for public
     * view, ARCHIVED is retired — everything else, including SETUP_INCOMPLETE
     * (registration is configured and open/closed on schedule, but Event/Queue/
     * Evaluation setup isn't done yet), stays publicly visible per
     * functional-spec §4.1's "closed registration may remain publicly
     * accessible" rule. Registration itself only needs its own dates configured
     * — it does not wait on the rest of setup (see deriveStatus()).
     */
    public function isPubliclyVisible(): bool
    {
        return ! in_array($this->categoryStatus?->code, ['DRAFT', 'ARCHIVED'], true);
    }

    /**
     * User-directed 2026-09-17: once "End Category" (EventActivationService::
     * completeCategory()) has run, nothing new gets added to it — no new
     * registrations, no new groups from Admin, no new dates/rooms — even
     * though the category and everything already in it stays fully intact
     * and still readable everywhere (Presentation Setup's list, Reports).
     * Checked at each of those write paths individually rather than via one
     * blanket middleware, since which categories a screen even lists
     * (Group & Panel Assignment / Event Control drop it; Reports keeps it)
     * is its own separate decision made where each list is built.
     */
    /**
     * The user an automatic change on this category (end-of-day sweeps,
     * registration queue placement) is recorded as. The category's creator,
     * unless that admin was deleted — then another active admin of the same
     * college, then any Super Admin.
     */
    public function actingUserId(): int
    {
        if ($this->created_by) {
            return (int) $this->created_by;
        }

        $admin = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'ADMIN'))
            ->whereHas('administratorProfile', fn ($q) => $q->where('college_id', $this->college_id))
            ->orderBy('id')
            ->value('id');

        return (int) ($admin ?? User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'SUPER_ADMIN'))->orderBy('id')->value('id'));
    }

    public function isCompleted(): bool
    {
        return $this->categoryStatus?->code === 'COMPLETED';
    }

    /**
     * Ended, including an ended category that was then archived — archiving
     * swaps the status to ARCHIVED and keeps the old one in
     * status_before_archive_id, so isCompleted() alone would unlock it.
     * Drives Presentation Setup's view-only state (CategorySetupLock).
     */
    public function isEnded(): bool
    {
        if ($this->isCompleted()) {
            return true;
        }

        return $this->categoryStatus?->code === 'ARCHIVED'
            && $this->statusBeforeArchive?->code === 'COMPLETED';
    }

    /**
     * True once any of this category's presentation dates has actually been
     * started (PresentationDate::activated_at — stamped by the first Start
     * Room on that day, see EventActivationService::startRoom()). Reads the
     * real timestamp rather than a derived status code, so it stays true for
     * a day that has since finished: a category that has already run one of
     * its days has started, whether or not any day is running right now.
     *
     * Used to freeze the presentation mode (user-directed 2026-09-21): the
     * mode decides what a registration even holds (one project title vs. N
     * proposed titles) and what shape of evaluation form applies, so once
     * groups have actually presented under it, switching would reinterpret
     * real recorded work. Before the first day starts it stays freely
     * editable.
     */
    public function hasStarted(): bool
    {
        return $this->presentationDates()->whereNotNull('activated_at')->exists();
    }

    public function isEventConfigured(): bool
    {
        if (! $this->categoryScheduleSetting || is_null($this->categoryScheduleSetting->duration_minutes)) {
            return false;
        }

        return $this->presentationDates()->whereHas('presentationDateRooms')->exists();
    }

    public function isQueueConfigured(): bool
    {
        return ! is_null($this->categoryQueueSetting);
    }

    public function isEvaluationConfigured(): bool
    {
        return $this->categoryEvaluationForms()->exists();
    }

    public function setupCompletionStatus(): string
    {
        $missing = array_filter([
            'Registration' => ! $this->isRegistrationConfigured(),
            'Event' => ! $this->isEventConfigured(),
            'Queue' => ! $this->isQueueConfigured(),
            'Evaluation' => ! $this->isEvaluationConfigured(),
        ]);

        return $missing === [] ? 'Complete' : 'Incomplete';
    }

    public function missingSetupSections(): array
    {
        return array_keys(array_filter([
            'Registration Configuration' => ! $this->isRegistrationConfigured(),
            'Event Configuration' => ! $this->isEventConfigured(),
            'Queue Configuration' => ! $this->isQueueConfigured(),
            'Evaluation Configuration' => ! $this->isEvaluationConfigured(),
        ]));
    }

    public function registrationWindowStatus(): string
    {
        // An ended category is sticky-terminal (deriveStatus() never
        // downgrades it back), so its registration window dates are no
        // longer the deciding factor once it's COMPLETED — registration
        // reads as Closed regardless of what registration_opens_at/
        // closes_at still say.
        if ($this->isCompleted()) {
            return 'Closed';
        }

        if (! $this->isRegistrationConfigured()) {
            return 'Not configured';
        }

        $now = now();

        if ($now->lt($this->registration_opens_at)) {
            return 'Scheduled';
        }

        if ($now->gt($this->registration_closes_at)) {
            return 'Closed';
        }

        return 'Open';
    }

    public function queueGenerationStatus(): string
    {
        $hasAttempts = PresentationAttempt::whereHas('researchGroup', function ($query) {
            $query->where('category_id', $this->id);
        })->exists();

        return $hasAttempts ? 'Generated' : 'Not generated';
    }

    public function eventStatus(): string
    {
        $statuses = $this->presentationDates()->with('eventDateStatus')->get()
            ->pluck('eventDateStatus.code')
            ->filter();

        if ($statuses->isEmpty()) {
            return 'Not scheduled';
        }

        if ($statuses->contains('ACTIVE')) {
            return 'Active';
        }

        if ($statuses->every(fn ($code) => $code === 'COMPLETED')) {
            return 'Completed';
        }

        if ($statuses->contains('CANCELLED') && $statuses->every(fn ($code) => in_array($code, ['CANCELLED', 'COMPLETED']))) {
            return 'Cancelled';
        }

        return 'Upcoming';
    }

    public function hasUnresolvedAttempts(): bool
    {
        return PresentationAttempt::whereHas('researchGroup', function ($query) {
            $query->where('category_id', $this->id);
        })->whereHas('presentationStatus', function ($query) {
            $query->where('is_terminal', false);
        })->exists();
    }

    /**
     * Derives what category_statuses.code this category SHOULD be, purely from
     * date/time and setup completeness — no manual override exists (Decision Log
     * 2026-07-28 #2). ARCHIVED/ACTIVE/COMPLETED are never auto-assigned here: ARCHIVED
     * is set only by the explicit Archive action, and ACTIVE/COMPLETED belong to
     * modules (Event Activation, Scheduling & Queue) that don't exist yet, so once a
     * category reaches one of those it's left alone rather than downgraded back.
     *
     * Registration opening/closing is driven only by registration_opens_at/
     * closes_at — it does NOT wait on Event (Schedule/Room)/Queue/Evaluation setup
     * being complete. Once registration closes the category sits at
     * SETUP_INCOMPLETE regardless of whether setup is actually done —
     * READY_FOR_QUEUE used to be a separate status for the "setup complete"
     * case but was merged into SETUP_INCOMPLETE (2026-08-25, user-directed) since
     * the two only ever differed by whether queue generation was currently
     * allowed, and that's a derived fact (see setupCompletionStatus()),
     * not something worth a whole extra status code. Whether queue
     * generation is actually allowed right now is decided live by
     * QueueGenerationService::autoGenerateIfEligible() via
     * setupCompletionStatus(), not by a status flag.
     */
    public function deriveStatus(): string
    {
        $currentCode = $this->categoryStatus?->code;

        if (in_array($currentCode, ['ARCHIVED', 'ACTIVE', 'COMPLETED'], true)) {
            return $currentCode;
        }

        return $this->deriveRegistrationBasedStatus();
    }

    /**
     * The non-sticky half of deriveStatus() on its own — what this category
     * would be purely from registration/setup, ignoring whatever its current
     * stored status is. Used by unarchive() (CategoryController) when there's
     * no status_before_archive_id snapshot to restore instead (a category
     * archived before that column existed), so it lands on a fresh,
     * real-time-correct status rather than a stale ARCHIVED-adjacent guess.
     */
    public function deriveRegistrationBasedStatus(): string
    {
        if (! $this->isRegistrationConfigured()) {
            return 'DRAFT';
        }

        return match ($this->registrationWindowStatus()) {
            'Scheduled' => 'UPCOMING',
            'Closed' => 'SETUP_INCOMPLETE',
            default => 'REGISTRATION_OPEN',
        };
    }

    /**
     * Display label for the admin-facing status badge. The stored
     * SETUP_INCOMPLETE code now covers both "registration closed, still
     * missing setup" and "registration closed, setup complete" (the old
     * READY_FOR_QUEUE case, merged in 2026-08-25 — see deriveStatus()).
     * Distinguishes the two for display only, without a second status
     * row. Queue generation is fully automatic (no manual trigger
     * anywhere in this app — see QueueGenerationService), so once setup
     * is complete this reports what actually already happened
     * ("Queue Generated") rather than a pending action to take
     * ("Ready for Queue" read as if there were a button to press, which
     * there isn't — user-directed correction, 2026-08-25). The one gap
     * — setup complete but no groups registered, so auto-generation has
     * nothing to generate yet — gets its own honest label rather than
     * lying about either state.
     */
    public function statusDisplayName(): string
    {
        if ($this->categoryStatus?->code === 'SETUP_INCOMPLETE' && $this->setupCompletionStatus() === 'Complete') {
            return $this->queueGenerationStatus() === 'Generated' ? 'Queue Generated' : 'Awaiting Groups';
        }

        return $this->categoryStatus?->name ?? 'N/A';
    }

    public function refreshStatus(): void
    {
        $derivedCode = $this->deriveStatus();

        if ($this->categoryStatus?->code === $derivedCode) {
            return;
        }

        $status = CategoryStatus::where('code', $derivedCode)->first();

        if ($status) {
            $this->update(['category_status_id' => $status->id]);
        }
    }
}
