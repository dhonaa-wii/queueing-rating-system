<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountStatus;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptSchedule;
use App\Models\PanelistProfile;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use App\Services\PanelistCredentialGenerator;
use App\Services\PanelistConflictService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanelistController extends Controller
{
    public function index(Request $request, PanelistConflictService $conflicts)
    {
        $search = $request->query('q');
        $selectedStatuses = array_values(array_filter((array) $request->query('status', [])));

        $panelists = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->with(['profile', 'accountStatus', 'panelistProfile.college'])
            ->withCount(['attemptPanelAssignments as assigned_groups_count' => function ($q) {
                $q->whereHas('assignmentStatus', fn ($s) => $s->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
                    ->whereHas('presentationAttempt.presentationStatus', fn ($s) => $s->where('is_terminal', false));
            }])
            ->orderBy('username')
            ->get();

        // Panelist Management (2026-08-25): a small "Conflict" badge next to
        // any panelist currently double-booked (PanelistConflictService::
        // scanAllConflicts()) — same system-wide scan the notification sync
        // and Group & Panel Assignment's Needs Attention card use.
        $conflictedPanelistIds = $conflicts->scanAllConflicts()->pluck('panelistId')->unique();

        $panelists->each(function ($panelist) use ($conflictedPanelistIds) {
            $panelist->has_conflict = $conflictedPanelistIds->contains($panelist->id);
        });

        if ($search) {
            $needle = strtolower($search);
            $panelists = $panelists->filter(function ($panelist) use ($needle) {
                return str_contains(strtolower($panelist->username), $needle)
                    || str_contains(strtolower($panelist->profile->first_name ?? ''), $needle)
                    || str_contains(strtolower($panelist->profile->last_name ?? ''), $needle);
            });
        }

        if (! empty($selectedStatuses)) {
            $panelists = $panelists->filter(fn ($panelist) => in_array($panelist->accountStatus->code, $selectedStatuses, true));
        }

        $panelists = $panelists->values();

        return view('admin.panelists.index', [
            'panelists' => $panelists,
            'panelistIds' => $panelists->pluck('id')->values()->all(),
            'search' => $search,
            'statuses' => AccountStatus::orderBy('name')->get(),
            'selectedStatuses' => $selectedStatuses,
        ]);
    }

    public function store(Request $request, PanelistCredentialGenerator $credentials)
    {
        try {
            $validated = $this->validatePanelist($request);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $username = $credentials->username($validated['first_name']);
        $password = $credentials->password($validated['first_name'], $validated['last_name']);

        $temporaryStatus = AccountStatus::where('code', 'TEMPORARY_CREDENTIALS_ISSUED')->firstOrFail();
        $panelistRole = Role::where('code', 'PANELIST')->firstOrFail();

        $panelist = DB::transaction(function () use ($validated, $username, $password, $temporaryStatus, $panelistRole, $request) {
            $user = User::create([
                'username' => $username,
                'password' => $password,
                'account_status_id' => $temporaryStatus->id,
                'must_change_password' => true,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $panelistRole->id,
                'assigned_by' => $request->user()->id,
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'sex' => $validated['sex'],
                'contact_number' => $validated['contact_number'] ?? null,
            ]);

            PanelistProfile::create([
                'user_id' => $user->id,
                // Auto-tied to the registering Admin's own College — a
                // panelist is only ever registered under whichever College
                // the Admin who registers them belongs to; it isn't a choice
                // made on this form.
                'college_id' => $request->user()->administratorProfile?->college_id,
                'specialization' => $validated['specialization'] ?? null,
                'temporary_password' => $password,
                'registered_by' => $request->user()->id,
                'registered_at' => now(),
            ]);

            return $user;
        });

        return redirect()->route('admin.panelists.index')
            ->with('status', "Panelist \"{$panelist->username}\" registered.")
            ->with('credential_reveal', [
                'title' => 'Panelist Registered',
                'rows' => [
                    ['label' => 'Username', 'value' => $username],
                    ['label' => 'Temporary Password', 'value' => $password],
                ],
                'note' => 'This password is shown once and cannot be retrieved again. If lost, use Reset Password to issue a new one.',
            ]);
    }

    /**
     * Panelist Management's sole data source (2026-08-25) — the old
     * full-page "Assignment History — not available yet" view is gone,
     * superseded by the index page's slide-in drawer, which fetches this
     * with Accept: application/json. Returns profile fields, summary
     * counts, the cross-category assigned-groups list, and whether this
     * panelist currently holds a live scheduling conflict.
     */
    public function show(Request $request, User $panelist, PanelistConflictService $conflicts)
    {
        $this->ensureIsPanelist($panelist);

        $panelist->load(['profile', 'accountStatus', 'panelistProfile.college']);

        $assignmentData = $this->assignedGroupsPayload($panelist);

        $panelistConflicts = $conflicts->scanAllConflicts()->filter(fn (array $item) => $item['panelistId'] === $panelist->id);

        $fullName = trim(implode(' ', array_filter([
            $panelist->profile->first_name ?? null,
            $panelist->profile->middle_name ?? null,
            $panelist->profile->last_name ?? null,
            $panelist->profile->suffix ?? null,
        ])));

        return response()->json([
            'id' => $panelist->id,
            'fullName' => $fullName !== '' ? $fullName : $panelist->username,
            'username' => $panelist->username,
            'contactNumber' => $panelist->profile->contact_number,
            'sex' => $panelist->profile->sex,
            'college' => $panelist->panelistProfile?->college?->name,
            'specialization' => $panelist->panelistProfile?->specialization,
            'accountStatus' => $panelist->accountStatus?->code,
            'accountStatusName' => $panelist->accountStatus?->name,
            'summary' => $assignmentData['summary'],
            'assignedGroups' => $assignmentData['rows'],
            'hasConflict' => $panelistConflicts->isNotEmpty(),
            'conflictMessages' => $panelistConflicts->pluck('message')->unique()->values(),
        ]);
    }

    public function update(Request $request, User $panelist, PanelistCredentialGenerator $credentials)
    {
        $this->ensureIsPanelist($panelist);

        try {
            $validated = $this->validatePanelist($request);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        // The username is the first name, so a corrected first name has to
        // carry the username with it (it is only generated at registration).
        $panelist->loadMissing('profile');
        if ($panelist->profile?->first_name !== $validated['first_name']) {
            $panelist->update([
                'username' => $credentials->username($validated['first_name'], $panelist->id),
            ]);
        }

        $panelist->profile()->updateOrCreate([], [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'sex' => $validated['sex'],
            'contact_number' => $validated['contact_number'] ?? null,
        ]);

        $panelist->panelistProfile()->update([
            'specialization' => $validated['specialization'] ?? null,
        ]);

        return redirect()->route('admin.panelists.index', ['panelist' => $panelist->id])->with('status', 'Panelist updated.');
    }

    public function activate(User $panelist)
    {
        $this->ensureIsPanelist($panelist);

        $activeStatus = AccountStatus::where('code', 'ACTIVE')->firstOrFail();
        $panelist->update(['account_status_id' => $activeStatus->id]);

        return redirect()->route('admin.panelists.index', ['panelist' => $panelist->id])->with('status', "\"{$panelist->username}\" activated.");
    }

    public function deactivate(User $panelist)
    {
        $this->ensureIsPanelist($panelist);

        $inactiveStatus = AccountStatus::where('code', 'INACTIVE')->firstOrFail();
        $panelist->update(['account_status_id' => $inactiveStatus->id]);

        return redirect()->route('admin.panelists.index', ['panelist' => $panelist->id])->with('status', "\"{$panelist->username}\" deactivated.");
    }

    public function resetPassword(Request $request, User $panelist, PanelistCredentialGenerator $credentials)
    {
        $this->ensureIsPanelist($panelist);

        $panelist->loadMissing('profile');

        $temporaryStatus = AccountStatus::where('code', 'TEMPORARY_CREDENTIALS_ISSUED')->firstOrFail();
        $temporaryPassword = $credentials->password($panelist->profile->first_name, $panelist->profile->last_name);

        $panelist->forceFill([
            'password' => $temporaryPassword,
            'account_status_id' => $temporaryStatus->id,
            'must_change_password' => true,
        ])->save();

        $panelist->panelistProfile()->update(['temporary_password' => $temporaryPassword]);

        return redirect()->route('admin.panelists.index', ['panelist' => $panelist->id])
            ->with('status', "Password for \"{$panelist->username}\" reset.")
            ->with('credential_reveal', [
                'title' => 'Password Reset',
                'rows' => [
                    ['label' => 'Username', 'value' => $panelist->username],
                    ['label' => 'Temporary Password', 'value' => $temporaryPassword],
                ],
                'note' => 'This password is shown once and cannot be retrieved again.',
            ]);
    }

    public function destroy(User $panelist)
    {
        $this->ensureIsPanelist($panelist);

        // Recorded work is history that Reports, grades and the presentation
        // audit trail stand on; erasing the panelist would erase it too (or
        // trip its non-cascading FKs), so a panelist who has any is refused.
        $history = DB::table('evaluation_submissions')->where('panelist_user_id', $panelist->id)->count()
            + DB::table('presentation_actions')->where('performed_by', $panelist->id)->count()
            + DB::table('presentation_pauses')
                ->where(fn ($q) => $q->where('paused_by', $panelist->id)->orWhere('resumed_by', $panelist->id))
                ->count()
            + DB::table('payment_verifications')
                ->where(fn ($q) => $q->where('initially_checked_by', $panelist->id)
                    ->orWhere('referred_by', $panelist->id)
                    ->orWhere('resolved_by', $panelist->id))
                ->count();

        if ($history > 0) {
            return redirect()->route('admin.panelists.index')
                ->with('error', "\"{$panelist->username}\" can't be deleted — they have recorded evaluations or presentation actions.");
        }

        // Hard delete (not soft): everything the panelist owns goes with them.
        DB::transaction(function () use ($panelist) {
            $id = $panelist->id;

            DB::table('attempt_panel_participations')->where('panelist_user_id', $id)->delete();
            DB::table('terminal_connections')->where('panelist_user_id', $id)->delete();
            DB::table('attempt_panel_assignments')->where('panelist_user_id', $id)->delete();
            DB::table('panel_substitution_requests')
                ->where(fn ($q) => $q->where('original_panelist_user_id', $id)
                    ->orWhere('requested_substitute_user_id', $id)
                    ->orWhere('requested_by', $id))
                ->delete();
            DB::table('notifications')->where('user_id', $id)->delete();
            DB::table('audit_logs')->where('user_id', $id)->update(['user_id' => null]);
            DB::table('sessions')->where('user_id', $id)->delete();
            DB::table('panelist_profiles')->where('user_id', $id)->delete();
            DB::table('user_profiles')->where('user_id', $id)->delete();
            DB::table('user_roles')->where('user_id', $id)->delete();

            $panelist->forceDelete();
        });

        return redirect()->route('admin.panelists.index')->with('status', 'Panelist deleted.');
    }

    /**
     * Cross-category assigned-groups list + summary counts for the drawer.
     * Scoped to categories whose derived status isn't terminal (i.e. not
     * COMPLETED/ARCHIVED — category_statuses.is_terminal) per the user's
     * "only upcoming and active categories" instruction. A deferred row's
     * attempt_schedule is stale (never recalculated while removed from the
     * queue), so it's shown as "Deferred" rather than a stale time; a
     * completed row shows the real completion timestamp, not the plan, per
     * this codebase's standing planned-vs-actual convention.
     */
    private function assignedGroupsPayload(User $panelist): array
    {
        $assignments = AttemptPanelAssignment::where('panelist_user_id', $panelist->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->whereHas('presentationAttempt.researchGroup.category.categoryStatus', fn ($q) => $q->where('is_terminal', false))
            ->with([
                'presentationAttempt.presentationStatus',
                'presentationAttempt.presentationRun',
                'presentationAttempt.researchGroup.category.presentationMode',
                'presentationAttempt.researchGroup.students' => fn ($q) => $q->orderByDesc('is_leader')->orderBy('id'),
                'presentationAttempt.attemptSchedule.queueEntry',
                // Read by AttemptSchedule::isAwaitingReschedule() below.
                'presentationAttempt.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempt.attemptPanelAssignments' => fn ($q) => $q->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
                'presentationAttempt.attemptPanelAssignments.panelist.profile',
                'presentationAttempt.attemptPanelAssignments.assignmentKind',
            ])
            ->get();

        $assignedCategories = $assignments->pluck('presentationAttempt.researchGroup.category_id')->unique()->count();
        $assignedGroups = $assignments->filter(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->presentationStatus?->code !== 'COMPLETED')->count();
        $evaluatedGroups = $assignments->filter(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->presentationStatus?->code === 'COMPLETED')->count();

        $rows = $assignments->map(function (AttemptPanelAssignment $assignment) use ($panelist) {
            $attempt = $assignment->presentationAttempt;
            $group = $attempt->researchGroup;
            $category = $group->category;
            $schedule = $attempt->attemptSchedule;
            $queueEntry = $schedule?->queueEntry;
            $isCompleted = $attempt->presentationStatus?->code === 'COMPLETED';
            $isDeferred = ! $isCompleted && $queueEntry && $queueEntry->removed_at !== null;
            $status = $isCompleted ? 'completed' : ($isDeferred ? 'deferred' : 'other');

            if ($isCompleted) {
                $realTime = $attempt->presentationRun?->completed_at ?? $attempt->completed_at;
                $dateTimeDisplay = $realTime ? $realTime->format('M j, Y g:i A') : '—';
                $sortAt = $realTime ?? $schedule?->planned_start_at;
            } elseif ($isDeferred) {
                $dateTimeDisplay = 'Deferred';
                $sortAt = $schedule?->planned_start_at;
            } elseif ($schedule?->isAwaitingReschedule($attempt)) {
                // Parked on a day that can no longer run — the slot it still
                // carries is on a day that is over, so it isn't shown.
                $dateTimeDisplay = AttemptSchedule::AWAITING_SHORT;
                $sortAt = null;
            } else {
                $dateTimeDisplay = $schedule?->planned_start_at
                    ? $schedule->planned_start_at->format('M j, Y g:i A') . '–' . $schedule->planned_end_at?->format('g:i A')
                    : '—';
                $sortAt = $schedule?->planned_start_at;
            }

            $isTitleProposal = $category?->presentationMode?->code === 'TITLE_PROPOSAL';
            $titleOrLeader = $isTitleProposal
                ? ($group->leader()?->full_name ?? '—')
                : ($group->current_project_title ?? '—');

            $otherPanels = $attempt->attemptPanelAssignments
                ->where('panelist_user_id', '!=', $panelist->id)
                ->map(function (AttemptPanelAssignment $pa) {
                    $name = trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? ''));

                    return [
                        'name' => $name !== '' ? $name : $pa->panelist->username,
                        'kind' => $pa->assignmentKind->code === 'BACKUP_PANELIST' ? 'backup' : 'assigned',
                    ];
                })->values();

            return [
                'attemptId' => $attempt->id,
                'status' => $status,
                'dateTimeDisplay' => $dateTimeDisplay,
                'sortAt' => $sortAt?->toIso8601String() ?? '',
                'titleOrLeader' => $titleOrLeader,
                'category' => $category->name ?? '—',
                'otherPanels' => $otherPanels,
            ];
        })->sortBy('sortAt')->values();

        return [
            'summary' => [
                'assignedCategories' => $assignedCategories,
                'assignedGroups' => $assignedGroups,
                'evaluatedGroups' => $evaluatedGroups,
            ],
            'rows' => $rows,
        ];
    }

    private function validatePanelist(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', 'in:'.implode(',', Student::SEXES)],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'specialization' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function ensureIsPanelist(User $user): void
    {
        abort_unless($user->hasRole('PANELIST'), 404);
    }
}
