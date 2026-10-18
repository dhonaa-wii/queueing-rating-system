<?php

namespace App\Services;

use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\ProposedTitle;
use App\Models\ResearchGroup;
use App\Models\ResearchGroupPanelistChoice;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared by Student\RegistrationController (public self-registration, Screen
 * B) and Admin\PanelAssignmentController (Admin-entered groups + edits to
 * existing registrations) so both write research_groups/students/
 * proposed_titles through the exact same rules — one leader row, a fixed
 * number of member slots (blank-or-fully-filled), and the category's own
 * mode-specific project field.
 *
 * Every student (leader and members alike) carries a last, first and middle
 * name, a sex, a section and — when the category asks for one — their own
 * research track. Names are normalized (trimmed, one space, each word
 * capitalised) before anything is validated or compared.
 */
class ResearchGroupRegistrationService
{
    public const SECTION_PATTERN = '/^[1-9][A-Z]$/';

    public const SECTION_FORMAT_MESSAGE = 'Enter the section as a year level and a letter, like 4B.';

    public const NAME_PATTERN = "/^\\p{L}[\\p{L} .'’\\-]*$/u";

    public const NAME_FORMAT_MESSAGE = 'Use letters only (spaces, hyphens, apostrophes and periods are allowed).';

    private const NAME_FIELDS = ['last_name', 'first_name', 'middle_name'];

    public static function normalizeSection(?string $section): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', (string) $section));
    }

    /**
     * Validates the leader and mode-specific fields via the normal
     * validator, then hand-processes the member rows: a fixed number of slots
     * is always submitted, each row is either fully blank (ignored) or fully
     * filled (kept) — anything in between is rejected — and no member may
     * duplicate the leader or another member.
     *
     * Returns 'leader' and each entry of 'members' as
     * [last_name, first_name, middle_name, sex, section, research_track_name].
     *
     * $withPanelistChoices is false for the Admin's Edit Group: once a group
     * is registered its panel is changed through Assign Panel, not its picks.
     */
    public function validate(Request $request, PresentationCategory $category, bool $isTitleProposal, bool $withPanelistChoices = true, ?string $keepAdviser = null): array
    {
        $memberSlots = max(0, $category->maximum_members - 1);
        $requiredTitleCount = $isTitleProposal ? ($category->required_proposed_title_count ?? 1) : 0;
        $trackRequired = (bool) $category->research_track_required;

        // Sections are a year level and a letter (4B) — user-directed
        // 2026-09-15 — and names are normalized, both before validating, so
        // "4b" is accepted and stored as "4B", "dela cruz" as "Dela Cruz".
        $merge = ['leader_section' => self::normalizeSection($request->input('leader_section'))];

        foreach (self::NAME_FIELDS as $field) {
            $merge["leader_{$field}"] = Student::normalizeName($request->input("leader_{$field}"));
        }

        $merge['members'] = collect($request->input('members', []))
            ->map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }

                foreach (self::NAME_FIELDS as $field) {
                    $row[$field] = Student::normalizeName($row[$field] ?? null);
                }

                $row['section'] = self::normalizeSection($row['section'] ?? null);

                return $row;
            })
            ->all();

        $request->merge($merge);

        $nameRules = ['string', 'max:100', 'regex:'.self::NAME_PATTERN];

        $rules = [
            'leader_last_name' => ['required', ...$nameRules],
            'leader_first_name' => ['required', ...$nameRules],
            'leader_middle_name' => ['nullable', ...$nameRules],
            'leader_sex' => ['required', 'in:'.implode(',', Student::SEXES)],
            'leader_section' => ['required', 'string', 'regex:'.self::SECTION_PATTERN],
            'members' => ['array', 'size:'.$memberSlots],
            'members.*.last_name' => ['nullable', ...$nameRules],
            'members.*.first_name' => ['nullable', ...$nameRules],
            'members.*.middle_name' => ['nullable', ...$nameRules],
            'members.*.sex' => ['nullable', 'in:'.implode(',', Student::SEXES)],
            'members.*.section' => ['nullable', 'string', 'regex:'.self::SECTION_PATTERN],
            'members.*.research_track_name' => ['nullable', 'string', 'max:150'],
        ];

        if ($trackRequired) {
            $rules['leader_research_track_name'] = ['required', 'string', 'max:150'];
        }

        // A category with a track list takes only its own tracks, so every
        // group can be routed to its track's room (TrackRouting).
        $trackOptions = $category->activeTrackNames();

        if ($trackRequired && $trackOptions !== []) {
            $rules['leader_research_track_name'][] = Rule::in($trackOptions);
            $rules['members.*.research_track_name'][] = Rule::in($trackOptions);
        }

        if ($isTitleProposal) {
            $rules['proposed_titles'] = ['required', 'array', 'size:'.$requiredTitleCount];
            $rules['proposed_titles.*'] = ['required', 'string', 'max:300'];
        } else {
            $rules['project_title'] = ['required', 'string', 'max:255'];
        }

        if ($category->technical_adviser_required) {
            // An edit may keep the group's current adviser even when that name
            // is not (or no longer) in the Panelist Registry, so fixing a
            // member's name doesn't force an adviser change.
            $adviserChoices = $category->adviserOptions();
            if (filled($keepAdviser)) {
                $adviserChoices[] = $keepAdviser;
            }
            $rules['technical_adviser_name'] = ['required', 'string', 'max:200', Rule::in($adviserChoices)];
        }

        // The group's own panel (user-directed 2026-10-11): exactly panel-count
        // different panelists of the category's college, the first one Chair.
        $choosePanelists = $withPanelistChoices && $category->groupsChoosePanelists();

        if ($choosePanelists) {
            $rules += $this->panelistChoiceRules($category);
        }

        $validated = $request->validate($rules, self::PANELIST_CHOICE_MESSAGES + [
            'technical_adviser_name.in' => 'Choose the technical adviser from the list.',
            'leader_section.regex' => self::SECTION_FORMAT_MESSAGE,
            'members.*.section.regex' => self::SECTION_FORMAT_MESSAGE,
            '*.regex' => self::NAME_FORMAT_MESSAGE,
            'leader_sex.in' => 'Select Male or Female.',
            'members.*.sex.in' => 'Select Male or Female.',
            'leader_research_track_name.in' => 'Select one of the listed tracks.',
            'members.*.research_track_name.in' => 'Select one of the listed tracks.',
        ], [
            'leader_research_track_name' => 'track',
            'members.*.research_track_name' => 'track',
        ]);

        $leader = [
            'last_name' => $validated['leader_last_name'],
            'first_name' => $validated['leader_first_name'],
            'middle_name' => ($validated['leader_middle_name'] ?? '') ?: null,
            'sex' => $validated['leader_sex'],
            'section' => trim($validated['leader_section']),
            'research_track_name' => $trackRequired ? trim($validated['leader_research_track_name']) : null,
        ];

        $members = [];
        $seen = [$this->identityKey($leader)];
        // Middle name is optional (user-directed 2026-10-19).
        $required = ['last_name', 'first_name', 'sex', 'section'];

        if ($trackRequired) {
            $required[] = 'research_track_name';
        }

        foreach ($request->input('members', []) as $index => $row) {
            $person = [
                'last_name' => trim($row['last_name'] ?? ''),
                'first_name' => trim($row['first_name'] ?? ''),
                'middle_name' => trim($row['middle_name'] ?? ''),
                'sex' => trim($row['sex'] ?? ''),
                'section' => trim($row['section'] ?? ''),
                'research_track_name' => trim($row['research_track_name'] ?? ''),
            ];

            if (! $trackRequired) {
                $person['research_track_name'] = '';
            }

            if (implode('', $person) === '') {
                continue;
            }

            foreach ($required as $field) {
                if ($person[$field] === '') {
                    throw ValidationException::withMessages([
                        "members.$index.$field" => 'Complete every field for this member, or leave the whole row blank.',
                    ]);
                }
            }

            $key = $this->identityKey($person);

            if (in_array($key, $seen, true)) {
                throw ValidationException::withMessages([
                    "members.$index.last_name" => $key === $seen[0]
                        ? 'A member cannot have the same name as the group leader.'
                        : 'This member name is entered more than once.',
                ]);
            }

            $seen[] = $key;
            $person['research_track_name'] = $person['research_track_name'] ?: null;
            $person['middle_name'] = $person['middle_name'] ?: null;
            $members[] = $person;
        }

        $adviserName = $category->technical_adviser_required ? trim($validated['technical_adviser_name']) : null;
        $panelistChoices = $choosePanelists ? array_values(array_map('intval', $validated['panelist_choices'])) : null;

        if ($panelistChoices) {
            $this->assertAdviserNotPicked($category, $adviserName, $panelistChoices);
        }

        return [
            'leader' => $leader,
            'members' => $members,
            'project_title' => $isTitleProposal ? null : trim($validated['project_title']),
            'proposed_titles' => $isTitleProposal ? array_values(array_map('trim', $validated['proposed_titles'])) : [],
            'technical_adviser_name' => $adviserName,
            'technical_adviser_user_id' => $this->adviserUserId($category, $adviserName),
            // null = the category doesn't let groups choose; leave any picks alone.
            'panelist_choices' => $panelistChoices,
        ];
    }

    private const PANELIST_CHOICE_MESSAGES = [
        'panelist_choices.required' => 'Choose a panelist for every seat.',
        'panelist_choices.*.required' => 'Choose a panelist for every seat.',
        'panelist_choices.*.distinct' => 'Choose a different panelist for each seat.',
        'panelist_choices.*.in' => 'Choose one of the listed panelists.',
    ];

    /** Exactly panel-count different panelists of the category's college. */
    private function panelistChoiceRules(PresentationCategory $category): array
    {
        return [
            'panelist_choices' => ['required', 'array', 'size:'.(int) $category->panelist_count],
            'panelist_choices.*' => ['required', 'integer', 'distinct', Rule::in($category->choosablePanelists()->pluck('id')->all())],
        ];
    }

    /** A group's adviser can't sit on its own panel (PanelAssignmentService). */
    private function assertAdviserNotPicked(PresentationCategory $category, ?string $adviserName, array $panelistIds): void
    {
        if (! $adviserName) {
            return;
        }

        $panelists = $category->choosablePanelists()->keyBy('id');

        foreach ($panelistIds as $index => $panelistId) {
            $panelist = $panelists->get($panelistId);

            if ($panelist && PanelAssignmentService::nameMatchesPanelist($adviserName, $panelist)) {
                throw ValidationException::withMessages([
                    "panelist_choices.$index" => 'Your technical adviser cannot be one of your panelists.',
                ]);
            }
        }
    }

    /**
     * Select Panel on the public schedule (user-directed 2026-10-11): a group
     * that registered before the category let groups choose (so it has no
     * picks) and has no panel yet chooses it once. There is no edit — after
     * this the panel is the Admin's (Edit Panel).
     */
    public function canChoosePanelLater(ResearchGroup $group, ?PresentationAttempt $attempt): bool
    {
        if (! $group->category?->groupsChoosePanelists() || $group->panelistChoices()->exists()) {
            return false;
        }

        if (! $attempt) {
            return true;
        }

        $attempt->loadMissing('presentationStatus');

        return $attempt->attempt_number === 1
            && ! $attempt->presentationStatus?->is_terminal
            && ! in_array($attempt->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)
            && ! $attempt->attemptPanelAssignments()->exists();
    }

    /** Saves the picks and seats them at once if the group is already queued. */
    public function choosePanelLater(ResearchGroup $group, Request $request): void
    {
        $category = $group->category;
        $attempt = $group->presentationAttempts()->orderByDesc('attempt_number')->first();

        if (! $this->canChoosePanelLater($group, $attempt)) {
            throw ValidationException::withMessages([
                'panelist_choices' => 'This group can no longer choose its panel.',
            ]);
        }

        $validated = $request->validate($this->panelistChoiceRules($category), self::PANELIST_CHOICE_MESSAGES);
        $panelistIds = array_values(array_map('intval', $validated['panelist_choices']));

        $this->assertAdviserNotPicked($category, $group->technical_adviser_name, $panelistIds);

        DB::transaction(function () use ($group, $panelistIds) {
            // Locks the group so two submissions at once can't both save.
            ResearchGroup::whereKey($group->id)->lockForUpdate()->first();

            if ($group->panelistChoices()->exists()) {
                throw ValidationException::withMessages([
                    'panelist_choices' => 'This group has already chosen its panel.',
                ]);
            }

            $this->savePanelistChoices($group, $panelistIds);
        });

        app(PanelAssignmentService::class)->applyGroupChoices($category, $category->actingUserId());
    }

    /** Replaces the group's chosen panel with $panelistIds, in order. */
    private function savePanelistChoices(ResearchGroup $group, ?array $panelistIds): void
    {
        if ($panelistIds === null) {
            return;
        }

        $group->panelistChoices()->delete();

        foreach ($panelistIds as $index => $panelistId) {
            ResearchGroupPanelistChoice::create([
                'research_group_id' => $group->id,
                'panelist_user_id' => $panelistId,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function identityKey(array $person): string
    {
        return mb_strtolower(implode('|', [$person['last_name'], $person['first_name'], $person['middle_name'] ?? '']));
    }

    /**
     * Standard mode rejects a matching leader name OR a matching project
     * title within the same category; Title Proposal mode checks the leader
     * name only (proposed titles are expected to vary/overlap across
     * groups). $excludeGroupId lets an edit re-save a group's own unchanged
     * name/title without tripping over itself.
     */
    public function guardAgainstDuplicate(PresentationCategory $category, array $leader, ?string $projectTitle, bool $isTitleProposal, ?int $excludeGroupId = null): void
    {
        $duplicateExists = ResearchGroup::where('category_id', $category->id)
            ->when($excludeGroupId, fn ($query) => $query->where('id', '!=', $excludeGroupId))
            // A group that FAILED is finished — it never gets another attempt
            // on that registration, so registering again is the only way back
            // into the queue and must not be blocked by its old record
            // (user-directed 2026-09-13). The new registration goes through
            // the exact same flow as the original one. requires_new_attempt
            // is checked too because RE_DEFENSE is stored is_successful = 0
            // as well, and that group still has its next attempt coming.
            ->whereDoesntHave('presentationAttempts', fn ($q) => $q
                ->whereHas('presentationStatus', fn ($sq) => $sq->where('code', 'COMPLETED'))
                ->whereHas('finalOutcome', fn ($oq) => $oq
                    ->where('is_successful', false)
                    ->where('requires_new_attempt', false)))
            ->where(function ($query) use ($leader, $projectTitle, $isTitleProposal) {
                $query->whereHas('students', fn ($q) => $q->where('is_leader', true)
                    ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower(trim($leader['last_name']))])
                    ->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower(trim($leader['first_name']))])
                    ->whereRaw("LOWER(TRIM(COALESCE(middle_name, ''))) = ?", [mb_strtolower(trim($leader['middle_name'] ?? ''))]));

                if (! $isTitleProposal && $projectTitle) {
                    $query->orWhereRaw('LOWER(TRIM(current_project_title)) = ?', [strtolower(trim($projectTitle))]);
                }
            })
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'leader_last_name' => 'An active registration with matching information already exists for this category.',
            ]);
        }
    }

    /**
     * The panelist account behind the chosen adviser name — the dropdown
     * lists the category's college panelists by name (adviserOptions()).
     * My Advisees matches on this id, so renaming the panelist later keeps
     * the group on their list.
     */
    private function adviserUserId(PresentationCategory $category, ?string $adviserName): ?int
    {
        if (! filled($adviserName)) {
            return null;
        }

        return $category->choosablePanelists()
            ->first(fn (\App\Models\User $panelist) => PresentationCategory::panelistName($panelist) === $adviserName)
            ?->id;
    }

    private function studentAttributes(array $person, bool $isLeader): array
    {
        return [
            'last_name' => $person['last_name'],
            'first_name' => $person['first_name'],
            'middle_name' => $person['middle_name'],
            'sex' => $person['sex'],
            'section_name' => $person['section'],
            'research_track_name' => $person['research_track_name'] ?? null,
            'is_leader' => $isLeader,
        ];
    }

    public function create(PresentationCategory $category, array $data, bool $isTitleProposal): ResearchGroup
    {
        return DB::transaction(function () use ($category, $data, $isTitleProposal) {
            $researchGroup = ResearchGroup::create([
                'group_reference' => app(ResearchGroupReferenceGenerator::class)->generate($category),
                'category_id' => $category->id,
                'current_project_title' => $data['project_title'],
                'technical_adviser_name' => $data['technical_adviser_name'],
                'technical_adviser_user_id' => $data['technical_adviser_user_id'] ?? null,
                'registered_at' => now(),
            ]);

            Student::create($this->studentAttributes($data['leader'], true) + ['research_group_id' => $researchGroup->id]);

            foreach ($data['members'] as $member) {
                Student::create($this->studentAttributes($member, false) + ['research_group_id' => $researchGroup->id]);
            }

            if ($isTitleProposal) {
                foreach ($data['proposed_titles'] as $index => $title) {
                    ProposedTitle::create([
                        'research_group_id' => $researchGroup->id,
                        'title_text' => $title,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            $this->savePanelistChoices($researchGroup, $data['panelist_choices'] ?? null);

            return $researchGroup;
        });
    }

    /**
     * Updates an existing group in place rather than delete-and-recreate, so
     * member/title rows that didn't change keep their ids. Existing rows are
     * matched to submitted rows by position (member/title order has no other
     * identity in this schema); leftover rows beyond what was submitted are
     * deleted, extra submitted rows beyond what existed are created.
     */
    public function update(ResearchGroup $group, array $data, bool $isTitleProposal): void
    {
        DB::transaction(function () use ($group, $data, $isTitleProposal) {
            // An unchanged adviser name that is no longer in the registry
            // (keepAdviser) keeps the account it was already linked to.
            $adviserUserId = $data['technical_adviser_user_id'] ?? null;
            if (! $adviserUserId && $data['technical_adviser_name'] === $group->technical_adviser_name) {
                $adviserUserId = $group->technical_adviser_user_id;
            }

            $group->update([
                'current_project_title' => $data['project_title'],
                'technical_adviser_name' => $data['technical_adviser_name'],
                'technical_adviser_user_id' => $adviserUserId,
            ]);

            $leader = $group->students()->where('is_leader', true)->first();

            if ($leader) {
                $leader->update($this->studentAttributes($data['leader'], true));
            } else {
                Student::create($this->studentAttributes($data['leader'], true) + ['research_group_id' => $group->id]);
            }

            $existingMembers = $group->students()->where('is_leader', false)->orderBy('id')->get()->values();

            foreach ($data['members'] as $index => $member) {
                if ($existingMembers->has($index)) {
                    $existingMembers[$index]->update($this->studentAttributes($member, false));
                } else {
                    Student::create($this->studentAttributes($member, false) + ['research_group_id' => $group->id]);
                }
            }

            $existingMembers->slice(count($data['members']))->each->delete();

            if ($isTitleProposal) {
                $existingTitles = $group->proposedTitles()->orderBy('sort_order')->get()->values();

                foreach ($data['proposed_titles'] as $index => $title) {
                    if ($existingTitles->has($index)) {
                        $existingTitles[$index]->update(['title_text' => $title, 'sort_order' => $index + 1]);
                    } else {
                        ProposedTitle::create([
                            'research_group_id' => $group->id,
                            'title_text' => $title,
                            'sort_order' => $index + 1,
                        ]);
                    }
                }

                $existingTitles->slice(count($data['proposed_titles']))->each->delete();
            }

            $this->savePanelistChoices($group, $data['panelist_choices'] ?? null);
        });
    }
}
