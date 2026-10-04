<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Support\AdminCollege;
use App\Models\PanelAssignmentKind;
use App\Models\PanelAssignmentStatus;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\ResearchGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manual panelist assignment for Group & Panel Assignment (functional-spec
 * §6.6/§9.7). Room's panelist_count (set in Presentation Setup) is the
 * required/actual panel size; user-directed 2026-08-05: exactly one backup
 * seat is always added on top (3 required -> 4 total, 1 backup) — no
 * separate configurable "allowed backup panelists" field, same "always"
 * treatment already used for other hardcoded rules in this codebase.
 *
 * User-directed 2026-09-07: the Admin must also explicitly designate one of
 * the assigned (non-backup) panelists as the Lead/Chair
 * (attempt_panel_assignments.is_lead) — this replaces the old rule that
 * whoever occupied the physical Terminal 1 seat was automatically the Lead.
 * Flow-control eligibility on the room-session tablet now follows this flag
 * instead of a fixed terminal (see TerminalConnectionService::isLead()) —
 * the designated Lead can log in at any terminal seat and still get control.
 */
class PanelAssignmentService
{
    public const BACKUP_SEATS = 1;

    public function __construct(private PanelistConflictService $conflicts, private PanelistNotifier $panelistNotifier)
    {
    }

    /**
     * How many live (non-REPLACED/WITHDRAWN) assigned seats an attempt holds
     * versus how many its room currently requires, plus whether a Lead is
     * designated among them. Pure computation over already-loaded relations
     * so the Group & Panel Assignment roster can call it once per row
     * without an extra query.
     *
     * Exists because the room's panelist_count can change *after* a panel
     * was assigned (Presentation Setup's category-wide Panel Count applies
     * to every ongoing/upcoming room), which silently left an already-
     * assigned group short of — or over — the new requirement.
     * `required` is null when the room has no count configured yet, in
     * which case there is nothing to comply with.
     */
    public function panelCompliance(PresentationAttempt $attempt): array
    {
        $required = $attempt->attemptSchedule?->presentationDateRoom?->panelist_count;

        $live = $attempt->attemptPanelAssignments
            ->reject(fn ($pa) => in_array($pa->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true));

        $assigned = $live->where('assignmentKind.code', 'ASSIGNED_PANELIST');

        return [
            'required' => $required === null ? null : (int) $required,
            'assigned' => $assigned->count(),
            'hasLead' => $assigned->contains(fn ($pa) => (bool) $pa->is_lead),
            'compliant' => $required === null || ($assigned->count() === (int) $required && $assigned->contains(fn ($pa) => (bool) $pa->is_lead)),
        ];
    }

    /**
     * Null when the attempt's panel still matches its room's requirement,
     * otherwise the reason it doesn't — shared by the roster's Panel column
     * indicator and PresentationControlService::start()'s block, so the
     * warning an Admin sees and the reason a panel can't start are always
     * the same rule.
     */
    public function panelRequirementIssue(PresentationAttempt $attempt): ?string
    {
        $state = $this->panelCompliance($attempt);

        if ($state['compliant']) {
            return null;
        }

        if ($state['assigned'] === 0) {
            return "This group has no assigned panel — assign {$state['required']} panelist(s) in Group & Panel Assignment.";
        }

        if ($state['assigned'] !== $state['required']) {
            return "This group's panel has {$state['assigned']} panelist(s) but its room now requires {$state['required']} — reassign the panel in Group & Panel Assignment.";
        }

        return "This group's panel has no Chair designated — reassign the panel in Group & Panel Assignment.";
    }

    /**
     * Multi-select assignment: applies the identical panelist set (assigned +
     * backup) to every selected attempt in one action — user-directed
     * "same panel set for every selected group" (e.g. one panel sitting
     * through a whole room's queue for the day).
     */
    public function assign(PresentationCategory $category, array $attemptIds, array $assignedPanelistUserIds, int $backupPanelistUserId, int $leadPanelistUserId, int $performedByUserId): array
    {
        // A form posts every id as a string, while $backupPanelistUserId /
        // $leadPanelistUserId arrive already coerced to int by this method's
        // own signature. The membership checks below are strict (in_array
        // ..., true), so without normalizing here they compare int against
        // string and never match — which silently made every assignment fail
        // the "Lead must be one of the assigned panelists" check, and made
        // the backup-vs-assigned check never fire at all.
        $attemptIds = array_values(array_unique(array_map('intval', $attemptIds)));
        $assignedPanelistUserIds = array_map('intval', $assignedPanelistUserIds);

        if (empty($attemptIds)) {
            return $this->failure('Select at least one group.');
        }

        $attempts = PresentationAttempt::whereIn('id', $attemptIds)
            ->with([
                'researchGroup',
                'presentationStatus',
                'attemptSchedule.presentationDateRoom.presentationDate',
            ])
            ->get();

        if ($attempts->count() !== count($attemptIds)) {
            return $this->failure('One or more selected groups could not be found.');
        }

        // A panel is drawn only from the category's own college (AdminCollege).
        $outside = AdminCollege::panelistsOutside([...$assignedPanelistUserIds, $backupPanelistUserId], $category->college_id);
        if ($outside !== []) {
            return $this->failure(implode(', ', $outside) . ' is not a panelist of this college.');
        }

        foreach ($attempts as $attempt) {
            if ($attempt->researchGroup->category_id !== $category->id) {
                return $this->failure('Selected groups must all belong to this category.');
            }

            if ($attempt->presentationStatus?->is_terminal) {
                return $this->failure("{$attempt->researchGroup->group_reference} already has a recorded outcome and can no longer be reassigned.");
            }

            // The panel is already sitting and scoring it — nothing about a
            // presentation in progress can be changed from here.
            if (in_array($attempt->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)) {
                return $this->failure("{$attempt->researchGroup->group_reference} is presenting right now — its panel can't be changed until it is completed or deferred.");
            }

            if (! $attempt->attemptSchedule) {
                return $this->failure("{$attempt->researchGroup->group_reference} has no schedule yet.");
            }
        }

        $requiredCounts = $attempts->map(fn ($attempt) => $attempt->attemptSchedule->presentationDateRoom->panelist_count)->unique();

        if ($requiredCounts->contains(null) || $requiredCounts->contains(0)) {
            return $this->failure('One or more selected groups are in a room with no panelist count configured yet — set it in Presentation Setup first.');
        }

        if ($requiredCounts->count() > 1) {
            return $this->failure('Selected groups require different numbers of panelists (different rooms) — select groups that need the same panel size.');
        }

        $requiredCount = (int) $requiredCounts->first();

        if (count($assignedPanelistUserIds) !== $requiredCount) {
            return $this->failure("This room requires exactly {$requiredCount} assigned panelist(s) — plus 1 backup.");
        }

        if (count(array_unique($assignedPanelistUserIds)) !== count($assignedPanelistUserIds)) {
            return $this->failure('The same panelist was selected more than once for the assigned seats.');
        }

        if (in_array($backupPanelistUserId, $assignedPanelistUserIds, true)) {
            return $this->failure('The alternate panel must be different from the chair and members.');
        }

        if (! in_array($leadPanelistUserId, $assignedPanelistUserIds, true)) {
            return $this->failure('The chair must be one of the selected panelists.');
        }

        $panelUserIds = [...$assignedPanelistUserIds, $backupPanelistUserId];

        $adviserConflict = $this->findTechnicalAdviserConflict($category, $attempts, $panelUserIds);

        if ($adviserConflict) {
            return $this->failure($adviserConflict);
        }

        $overlapMessage = $this->findBatchOverlap($attempts);

        if ($overlapMessage) {
            return $this->failure($overlapMessage);
        }

        $conflict = $this->findExternalConflicts($panelUserIds, $attempts, $attemptIds);

        if ($conflict) {
            return $this->failure($conflict['message'], ['conflict' => $conflict]);
        }

        // panelist id => attempt ids they were not already seated on.
        $newlySeated = [];

        DB::transaction(function () use ($attempts, $assignedPanelistUserIds, $backupPanelistUserId, $leadPanelistUserId, $performedByUserId, &$newlySeated) {
            $assignedKind = PanelAssignmentKind::where('code', 'ASSIGNED_PANELIST')->firstOrFail();
            $backupKind = PanelAssignmentKind::where('code', 'BACKUP_PANELIST')->firstOrFail();
            $assignedStatus = PanelAssignmentStatus::where('code', 'ASSIGNED')->firstOrFail();
            $replacedStatus = PanelAssignmentStatus::where('code', 'REPLACED')->firstOrFail();
            $now = now();

            $target = [];
            foreach ($assignedPanelistUserIds as $panelistId) {
                $target[$panelistId] = $assignedKind->id;
            }
            $target[$backupPanelistUserId] = $backupKind->id;

            foreach ($attempts as $attempt) {
                $existing = AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)
                    ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
                    ->get();

                foreach ($existing as $row) {
                    if (! array_key_exists($row->panelist_user_id, $target)) {
                        $row->update(['assignment_status_id' => $replacedStatus->id, 'ended_at' => $now]);
                    }
                }

                foreach ($target as $panelistId => $kindId) {
                    if (! $existing->contains('panelist_user_id', $panelistId)) {
                        $newlySeated[$panelistId][] = $attempt->id;
                    }

                    AttemptPanelAssignment::updateOrCreate(
                        ['presentation_attempt_id' => $attempt->id, 'panelist_user_id' => $panelistId],
                        [
                            'assignment_kind_id' => $kindId,
                            'is_lead' => $panelistId === $leadPanelistUserId,
                            'assignment_status_id' => $assignedStatus->id,
                            'assigned_by' => $performedByUserId,
                            'assigned_at' => $now,
                            'ended_at' => null,
                        ]
                    );
                }
            }
        });

        foreach ($newlySeated as $panelistId => $attemptIds) {
            $this->panelistNotifier->assigned((int) $panelistId, $attemptIds, $performedByUserId);
        }

        return ['ok' => true, 'count' => $attempts->count()];
    }

    /**
     * A panel can't be in two places at once — checked regardless of
     * assigned/backup kind, since it's about the person's real schedule.
     */
    private function findBatchOverlap(Collection $attempts): ?string
    {
        $schedules = $attempts->map(fn ($a) => [$a, $a->attemptSchedule])->values();

        for ($i = 0; $i < $schedules->count(); $i++) {
            for ($j = $i + 1; $j < $schedules->count(); $j++) {
                [$attemptA, $scheduleA] = $schedules[$i];
                [$attemptB, $scheduleB] = $schedules[$j];

                if ($this->conflicts->intervalsConflict($scheduleA->planned_start_at, $scheduleA->planned_end_at, $scheduleB->planned_start_at, $scheduleB->planned_end_at)) {
                    return "{$attemptA->researchGroup->group_reference} and {$attemptB->researchGroup->group_reference} have overlapping schedule times — a panel can't attend both.";
                }
            }
        }

        return null;
    }

    /**
     * System-wide conflict check (functional-spec §9.7: a panelist may serve
     * across multiple categories provided no schedule conflict exists) —
     * a real time overlap only, see PanelistConflictService. Returns structured details (not
     * just a prose message) so the caller can render a modal naming the
     * panelist, the conflicting time, and the conflicting category — rather
     * than a plain flash-error sentence.
     */
    private function findExternalConflicts(array $panelistUserIds, Collection $attempts, array $excludeAttemptIds): ?array
    {
        foreach ($attempts as $attempt) {
            $schedule = $attempt->attemptSchedule;

            $conflicts = $this->conflicts->conflictingAssignments($schedule, $panelistUserIds, $excludeAttemptIds);

            if ($conflicts->isNotEmpty()) {
                $conflict = $conflicts->first();
                $otherAttempt = $conflict->presentationAttempt;
                $otherGroup = $otherAttempt->researchGroup;

                return [
                    'message' => $this->conflicts->messageFor($attempt, $conflict),
                    'panelistName' => trim(($conflict->panelist->profile->first_name ?? '') . ' ' . ($conflict->panelist->profile->last_name ?? '')) ?: 'This panelist',
                    'conflictingGroup' => $otherGroup->group_reference,
                    'conflictingCategory' => $otherGroup->category->name ?? 'another category',
                    'conflictingTime' => optional($otherAttempt->attemptSchedule?->planned_start_at)->format('M j, g:i A'),
                ];
            }
        }

        return null;
    }

    /**
     * A group's technical adviser (free-text, `research_groups.
     * technical_adviser_name`) cannot also sit on that group's own panel —
     * user-directed. Only enforced when the category actually requires a
     * technical adviser; names are compared normalized (case/whitespace
     * insensitive), checked both "first last" and "last first" since the
     * adviser field is free text and may have been entered in either order.
     */
    private function findTechnicalAdviserConflict(PresentationCategory $category, Collection $attempts, array $panelistUserIds): ?string
    {
        if (! $category->technical_adviser_required) {
            return null;
        }

        $panelists = User::whereIn('id', $panelistUserIds)->with('profile')->get()->keyBy('id');

        foreach ($attempts as $attempt) {
            $adviserName = trim((string) $attempt->researchGroup->technical_adviser_name);

            if ($adviserName === '') {
                continue;
            }

            foreach ($panelistUserIds as $panelistId) {
                $panelist = $panelists->get($panelistId);

                if ($panelist && self::nameMatchesPanelist($adviserName, $panelist)) {
                    return "{$attempt->researchGroup->group_reference}'s technical adviser ({$adviserName}) cannot also be assigned as a panelist for this group.";
                }
            }
        }

        return null;
    }

    /**
     * Swaps one or more individual panelists out of one attempt's panel,
     * keeping every other seat exactly as it is — the narrow counterpart to
     * assign(), which rewrites a whole panel (and several groups' at once).
     *
     * Built 2026-09-22 for the roster's panel-conflict rows: a scheduling
     * conflict is always about one named panelist being in two places at
     * once, so resolving it means replacing that one person, not re-picking
     * the group's entire panel. The incoming panelist inherits the outgoing
     * one's seat kind and Lead designation, so a conflicted Lead is replaced
     * by a Lead and a backup by a backup — a room is never left with no one
     * able to control flow (the same rule PanelSubstitutionService::
     * applySwap() already follows for an approved substitution request).
     *
     * $replacements is keyed outgoing panelist user id => incoming user id.
     */
    public function replacePanelists(PresentationAttempt $attempt, array $replacements, int $performedByUserId): array
    {
        $attempt->loadMissing([
            'researchGroup.category',
            'presentationStatus',
            'attemptSchedule',
        ]);

        if ($attempt->presentationStatus?->is_terminal) {
            return $this->failure("{$attempt->researchGroup->group_reference} already has a recorded outcome and can no longer be reassigned.");
        }

        if (! $attempt->attemptSchedule) {
            return $this->failure("{$attempt->researchGroup->group_reference} has no schedule yet.");
        }

        $pairs = [];

        foreach ($replacements as $outgoingId => $incomingId) {
            $outgoingId = (int) $outgoingId;
            $incomingId = (int) $incomingId;

            if ($outgoingId <= 0 || $incomingId <= 0) {
                continue;
            }

            $pairs[$outgoingId] = $incomingId;
        }

        if (empty($pairs)) {
            return $this->failure('Pick at least one replacement panelist.');
        }

        $incomingIds = array_values($pairs);

        if (count(array_unique($incomingIds)) !== count($incomingIds)) {
            return $this->failure('The same replacement panelist was picked for more than one seat.');
        }

        $live = AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with('panelist.profile')
            ->get()
            ->keyBy('panelist_user_id');

        foreach ($pairs as $outgoingId => $incomingId) {
            if (! $live->has($outgoingId)) {
                return $this->failure('One of the panelists being replaced is no longer on this panel — reload the page and try again.');
            }

            if ($live->has($incomingId)) {
                $name = $this->panelistName($live->get($incomingId)->panelist);

                return $this->failure("{$name} is already on {$attempt->researchGroup->group_reference}'s panel.");
            }
        }

        // Only a real, currently-eligible panelist account can take a seat —
        // the same two conditions the Assign Panel picker itself lists by.
        $incoming = User::whereIn('id', $incomingIds)
            ->whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->whereHas('accountStatus', fn ($q) => $q->where('is_login_allowed', true))
            ->with('profile')
            ->get()
            ->keyBy('id');

        if ($incoming->count() !== count($incomingIds)) {
            return $this->failure('One of the replacement panelists could not be found or is no longer active.');
        }

        $outside = AdminCollege::panelistsOutside($incomingIds, $attempt->researchGroup?->category?->college_id);
        if ($outside !== []) {
            return $this->failure(implode(', ', $outside) . ' is not a panelist of this college.');
        }

        $adviserConflict = $this->findTechnicalAdviserConflict(
            $attempt->researchGroup->category,
            collect([$attempt]),
            $incomingIds
        );

        if ($adviserConflict) {
            return $this->failure($adviserConflict);
        }

        // The whole point of the action is to clear a conflict, so it must
        // not create another one: the incoming panelists are held to the
        // same system-wide overlap rule assign() enforces.
        $conflicts = $this->conflicts->conflictingAssignments($attempt->attemptSchedule, $incomingIds);

        if ($conflicts->isNotEmpty()) {
            $conflict = $conflicts->first();
            $otherGroup = $conflict->presentationAttempt->researchGroup;

            return $this->failure($this->conflicts->messageFor($attempt, $conflict), ['conflict' => [
                'message' => $this->conflicts->messageFor($attempt, $conflict),
                'panelistName' => $this->panelistName($conflict->panelist),
                'conflictingGroup' => $otherGroup->group_reference,
                'conflictingCategory' => $otherGroup->category->name ?? 'another category',
                'conflictingTime' => optional($conflict->presentationAttempt->attemptSchedule?->planned_start_at)->format('M j, g:i A'),
            ]]);
        }

        DB::transaction(function () use ($attempt, $pairs, $live, $performedByUserId) {
            $assignedStatus = PanelAssignmentStatus::where('code', 'ASSIGNED')->firstOrFail();
            $replacedStatus = PanelAssignmentStatus::where('code', 'REPLACED')->firstOrFail();
            $now = now();

            foreach ($pairs as $outgoingId => $incomingId) {
                $outgoing = $live->get($outgoingId);

                $outgoing->update(['assignment_status_id' => $replacedStatus->id, 'ended_at' => $now]);

                AttemptPanelAssignment::updateOrCreate(
                    ['presentation_attempt_id' => $attempt->id, 'panelist_user_id' => $incomingId],
                    [
                        'assignment_kind_id' => $outgoing->assignment_kind_id,
                        'is_lead' => (bool) $outgoing->is_lead,
                        'assignment_status_id' => $assignedStatus->id,
                        'assigned_by' => $performedByUserId,
                        'assigned_at' => $now,
                        'ended_at' => null,
                    ]
                );
            }
        });

        foreach ($pairs as $incomingId) {
            $this->panelistNotifier->assigned($incomingId, [$attempt->id], $performedByUserId);
        }

        return [
            'ok' => true,
            'count' => count($pairs),
            'replaced' => collect($pairs)->map(fn ($incomingId, $outgoingId) => [
                'out' => $this->panelistName($live->get($outgoingId)->panelist),
                'in' => $this->panelistName($incoming->get($incomingId)),
            ])->values()->all(),
        ];
    }

    private function panelistName(?User $panelist): string
    {
        if (! $panelist) {
            return 'This panelist';
        }

        $panelist->loadMissing('profile');
        $name = trim(($panelist->profile->first_name ?? '') . ' ' . ($panelist->profile->last_name ?? ''));

        return $name !== '' ? $name : ($panelist->username ?? 'This panelist');
    }

    /**
     * Reverse direction of findTechnicalAdviserConflict(): here the panel is
     * already assigned and it's the *adviser* that just changed (Admin edited
     * the group's registration data in Group & Panel Assignment). Returns the
     * blocking message naming the panelist who would otherwise end up
     * evaluating their own advisee, so the caller can refuse the edit until
     * the panel is changed — user-directed: the Admin has to fix the panel
     * themselves. Deliberately never silently drops the offending assignment,
     * which would leave the group short a panelist with nothing on screen
     * saying why.
     */
    public function findAssignedPanelConflictForAdviser(ResearchGroup $group, ?string $adviserName): ?string
    {
        $adviserName = trim((string) $adviserName);

        if ($adviserName === '') {
            return null;
        }

        $assignments = AttemptPanelAssignment::whereHas(
            'presentationAttempt',
            fn ($q) => $q->where('research_group_id', $group->id)
                ->whereHas('presentationStatus', fn ($sq) => $sq->where('is_terminal', false))
        )
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with('panelist.profile')
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->panelist && self::nameMatchesPanelist($adviserName, $assignment->panelist)) {
                $panelistName = trim(($assignment->panelist->profile->first_name ?? '') . ' ' . ($assignment->panelist->profile->last_name ?? ''));

                return trim(($panelistName ?: $adviserName) . " is already on {$group->group_reference}'s panel — change the assigned panel before setting them as this group's technical adviser.");
            }
        }

        return null;
    }

    public static function nameMatchesPanelist(string $name, User $panelist): bool
    {
        $normalized = self::normalizeName($name);

        return $normalized !== '' && in_array($normalized, self::panelistNameVariants($panelist), true);
    }

    /**
     * Both name orders — the adviser field is free text, so it may have been
     * typed "first last" or "last first".
     */
    public static function panelistNameVariants(User $panelist): array
    {
        $firstName = $panelist->profile->first_name ?? '';
        $lastName = $panelist->profile->last_name ?? '';

        return array_values(array_filter([
            self::normalizeName(trim("{$firstName} {$lastName}")),
            self::normalizeName(trim("{$lastName} {$firstName}")),
        ]));
    }

    public static function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    private function failure(string $message, array $extra = []): array
    {
        return array_merge(['ok' => false, 'error' => $message], $extra);
    }
}
