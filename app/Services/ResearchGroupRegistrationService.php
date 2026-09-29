<?php

namespace App\Services;

use App\Models\PresentationCategory;
use App\Models\ProposedTitle;
use App\Models\ResearchGroup;
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
     */
    public function validate(Request $request, PresentationCategory $category, bool $isTitleProposal): array
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
            'leader_middle_name' => ['required', ...$nameRules],
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
            $rules['technical_adviser_name'] = ['required', 'string', 'max:200'];
        }

        $validated = $request->validate($rules, [
            'leader_section.regex' => self::SECTION_FORMAT_MESSAGE,
            'members.*.section.regex' => self::SECTION_FORMAT_MESSAGE,
            '*.regex' => self::NAME_FORMAT_MESSAGE,
            'leader_sex.in' => 'Select Male or Female.',
            'members.*.sex.in' => 'Select Male or Female.',
            'leader_research_track_name.in' => 'Select one of the listed tracks.',
            'members.*.research_track_name.in' => 'Select one of the listed tracks.',
        ]);

        $leader = [
            'last_name' => $validated['leader_last_name'],
            'first_name' => $validated['leader_first_name'],
            'middle_name' => $validated['leader_middle_name'],
            'sex' => $validated['leader_sex'],
            'section' => trim($validated['leader_section']),
            'research_track_name' => $trackRequired ? trim($validated['leader_research_track_name']) : null,
        ];

        $members = [];
        $seen = [$this->identityKey($leader)];
        $required = ['last_name', 'first_name', 'middle_name', 'sex', 'section'];

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
            $members[] = $person;
        }

        return [
            'leader' => $leader,
            'members' => $members,
            'project_title' => $isTitleProposal ? null : trim($validated['project_title']),
            'proposed_titles' => $isTitleProposal ? array_values(array_map('trim', $validated['proposed_titles'])) : [],
            'technical_adviser_name' => $category->technical_adviser_required ? trim($validated['technical_adviser_name']) : null,
        ];
    }

    private function identityKey(array $person): string
    {
        return mb_strtolower(implode('|', [$person['last_name'], $person['first_name'], $person['middle_name']]));
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
                    ->whereRaw("LOWER(TRIM(COALESCE(middle_name, ''))) = ?", [mb_strtolower(trim($leader['middle_name']))]));

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
            $group->update([
                'current_project_title' => $data['project_title'],
                'technical_adviser_name' => $data['technical_adviser_name'],
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
        });
    }
}
