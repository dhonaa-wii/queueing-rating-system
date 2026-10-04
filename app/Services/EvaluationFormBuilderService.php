<?php

namespace App\Services;

use App\Models\CategoryEvaluationForm;
use App\Models\CriterionScope;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationForm;
use App\Models\EvaluationFormVersion;
use App\Models\EvaluationScaleLabel;
use App\Models\EvaluationScore;
use App\Models\EvaluationSubmission;
use App\Models\FormVersionStatus;
use App\Models\PresentationCategory;
use App\Models\ScoringMethod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvaluationFormBuilderService
{
    private const DEFAULT_SECTION_COUNT = 5;

    /**
     * Fixed 1-5 scale, listed low to high so the seeded rows (and every
     * table/legend that renders them in insertion order) read 1 2 3 4 5,
     * not 5 4 3 2 1 - user-directed 2026-09-13, which also retired the
     * original 0-5 range the sample sheet had used for its lowest band.
     */
    private const DEFAULT_SCALE_LABELS = [
        1 => 'Very Insufficient',
        2 => 'Insufficient',
        3 => 'Mildly Sufficient',
        4 => 'Sufficient',
        5 => 'Very Sufficient',
    ];

    public function createForm(array $data, User $admin): EvaluationForm
    {
        return DB::transaction(function () use ($data, $admin) {
            $form = EvaluationForm::create([
                // A form belongs to its creator's college (AdminCollege).
                'college_id' => $admin->administratorProfile?->college_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            $draft = $this->createDraftVersion($form, 1, $admin);
            $this->seedDefaultSections($draft);

            return $form;
        });
    }

    public function updateForm(EvaluationForm $form, array $data): EvaluationForm
    {
        $form->update(['name' => $data['name']]);

        return $form;
    }

    private function seedDefaultSections(EvaluationFormVersion $version): void
    {
        $scopeId = $this->defaultScopeId();
        $defaultWeight = round(100 / self::DEFAULT_SECTION_COUNT, 2);

        for ($i = 1; $i <= self::DEFAULT_SECTION_COUNT; $i++) {
            $version->evaluationCriteria()->create([
                'parent_criterion_id' => null,
                'name' => "Section {$i}",
                'description' => null,
                'criterion_scope_id' => $scopeId,
                'weight' => $defaultWeight,
                'maximum_score' => $version->scale_max,
                'sort_order' => $i,
                'is_required' => true,
            ]);
        }
    }

    /**
     * Item/section scope, required-ness, and description are no longer
     * admin-editable through the workspace UI (the printed sheet only shows
     * a name + weight per row) — every new row silently gets these defaults.
     */
    private function defaultScopeId(): ?int
    {
        return (CriterionScope::where('code', 'GROUP')->first() ?? CriterionScope::first())?->id;
    }

    private function createDraftVersion(EvaluationForm $form, int $versionNumber, User $admin): EvaluationFormVersion
    {
        $draftStatus = FormVersionStatus::where('code', 'DRAFT')->firstOrFail();
        $scoringMethod = ScoringMethod::where('code', 'WEIGHTED_AVERAGE')->firstOrFail();

        $version = $form->evaluationFormVersions()->create([
            'version_number' => $versionNumber,
            'status_id' => $draftStatus->id,
            'scoring_method_id' => $scoringMethod->id,
            'scale_min' => 1,
            'scale_max' => 5,
            'show_letterhead' => false,
            'created_by' => $admin->id,
        ]);

        $this->seedDefaultScaleLabels($version);

        return $version;
    }

    private function seedDefaultScaleLabels(EvaluationFormVersion $version): void
    {
        foreach (self::DEFAULT_SCALE_LABELS as $value => $label) {
            $version->scaleLabels()->create(['value' => $value, 'label' => $label]);
        }
    }

    /**
     * Only publish() needs this — promoting a draft to ACTIVE is strictly a
     * publish-only action: an unpublished form has exactly one version, and
     * publishing it is what makes it assignable to a category. Every other
     * mutation below uses assertEditable() instead, which also allows editing
     * a published form in place - that is the whole point of removing
     * versions (2026-09-22).
     */
    private function assertDraft(EvaluationFormVersion $version): void
    {
        if (! $version->isDraft()) {
            throw ValidationException::withMessages([
                'version' => 'This form is already published.',
            ]);
        }
    }

    private function assertEditable(EvaluationFormVersion $version): void
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages([
                'version' => 'This form is retired and can no longer be edited.',
            ]);
        }
    }

    /**
     * A published form stays editable even once real evaluations have been
     * recorded against it, but a criterion those scores point at cannot be
     * removed: evaluation_scores.evaluation_criterion_id is a hard,
     * non-cascading FK, so deleting it would either throw a raw FK violation
     * or - if it ever cascaded - silently destroy a panelist's recorded
     * score. Renaming the row is always allowed; that is how a mislabelled
     * criterion gets corrected without touching the scores under it.
     */
    private function assertNoRecordedScores(EvaluationCriterion $criterion, string $label): void
    {
        $ids = $criterion->childCriteria()->pluck('id')->push($criterion->id);

        $scoreCount = EvaluationScore::whereIn('evaluation_criterion_id', $ids)->count();

        if ($scoreCount > 0) {
            throw ValidationException::withMessages([
                'criterion' => "\"{$criterion->name}\" already has {$scoreCount} recorded "
                    .Str::plural('score', $scoreCount)
                    ." against it and cannot be removed. Rename this {$label} instead.",
            ]);
        }
    }

    public function addSection(EvaluationFormVersion $version, array $data, ?string $position = null, ?int $referenceId = null): EvaluationCriterion
    {
        $this->assertEditable($version);

        return DB::transaction(function () use ($version, $data, $position, $referenceId) {
            $nextOrder = (int) $version->sections()->max('sort_order') + 1;

            $section = $version->evaluationCriteria()->create([
                'parent_criterion_id' => null,
                'name' => $data['name'],
                'criterion_scope_id' => $this->defaultScopeId(),
                'weight' => $data['weight'] ?? 0,
                'maximum_score' => $version->scale_max,
                'sort_order' => $nextOrder,
                'is_required' => true,
            ]);

            if ($position !== null) {
                $this->reorderAfterInsert($version->sections()->orderBy('sort_order')->get(), $section, $position, $referenceId);
            }

            return $section->fresh();
        });
    }

    public function updateSection(EvaluationCriterion $section, array $data): EvaluationCriterion
    {
        $this->assertEditable($section->evaluationFormVersion);

        $section->update([
            'name' => $data['name'],
            'weight' => $data['weight'],
        ]);

        return $section;
    }

    public function deleteSection(EvaluationCriterion $section): void
    {
        $this->assertEditable($section->evaluationFormVersion);
        $this->assertNoRecordedScores($section, 'section');

        DB::transaction(function () use ($section) {
            $section->childCriteria()->delete();
            $section->delete();
        });
    }

    public function moveSection(EvaluationCriterion $section, string $direction): void
    {
        $this->assertEditable($section->evaluationFormVersion);

        $this->swapWithNeighbor(
            $section->evaluationFormVersion->sections()->orderBy('sort_order')->get(),
            $section,
            $direction
        );
    }

    public function addItem(EvaluationCriterion $section, array $data, ?string $position = null, ?int $referenceId = null): EvaluationCriterion
    {
        $version = $section->evaluationFormVersion;
        $this->assertEditable($version);

        return DB::transaction(function () use ($section, $version, $data, $position, $referenceId) {
            $nextOrder = (int) $section->childCriteria()->max('sort_order') + 1;

            $item = $section->childCriteria()->create([
                'evaluation_form_version_id' => $version->id,
                'name' => $data['name'],
                'criterion_scope_id' => $this->defaultScopeId(),
                'weight' => null,
                'maximum_score' => $version->scale_max,
                'sort_order' => $nextOrder,
                'is_required' => true,
            ]);

            if ($position !== null) {
                $this->reorderAfterInsert($section->childCriteria()->orderBy('sort_order')->get(), $item, $position, $referenceId);
            }

            return $item->fresh();
        });
    }

    public function updateItem(EvaluationCriterion $item, array $data): EvaluationCriterion
    {
        $this->assertEditable($item->evaluationFormVersion);

        $item->update(['name' => $data['name']]);

        return $item;
    }

    public function deleteItem(EvaluationCriterion $item): void
    {
        $this->assertEditable($item->evaluationFormVersion);
        $this->assertNoRecordedScores($item, 'criterion');

        $item->delete();
    }

    public function moveItem(EvaluationCriterion $item, string $direction): void
    {
        $this->assertEditable($item->evaluationFormVersion);

        $this->swapWithNeighbor(
            $item->parentCriterion->childCriteria()->orderBy('sort_order')->get(),
            $item,
            $direction
        );
    }

    /**
     * Places a freshly-created row (already appended last with the next
     * sort_order) at the position implied by an admin's row selection —
     * directly before/after a chosen sibling, or at the very start/end when
     * no sibling was selected — then renumbers every sibling 1..N so
     * sort_order stays contiguous (same "always renumbered" convention
     * QueueAdjustmentService uses for queue positions).
     */
    private function reorderAfterInsert($orderedSiblings, EvaluationCriterion $newRow, string $position, ?int $referenceId): void
    {
        $siblings = $orderedSiblings->reject(fn ($row) => $row->id === $newRow->id)->values();

        $index = $siblings->count();

        if ($referenceId !== null) {
            $refIndex = $siblings->search(fn ($row) => $row->id === $referenceId);
            if ($refIndex !== false) {
                $index = $position === 'before' ? $refIndex : $refIndex + 1;
            }
        } elseif ($position === 'before') {
            $index = 0;
        }

        $siblings->splice($index, 0, [$newRow]);

        DB::transaction(function () use ($siblings) {
            foreach ($siblings->values() as $i => $row) {
                if ((int) $row->sort_order !== $i + 1) {
                    $row->update(['sort_order' => $i + 1]);
                }
            }
        });
    }

    private function swapWithNeighbor($orderedSiblings, EvaluationCriterion $target, string $direction): void
    {
        $index = $orderedSiblings->search(fn ($item) => $item->id === $target->id);
        $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $orderedSiblings->has($neighborIndex)) {
            return;
        }

        $neighbor = $orderedSiblings[$neighborIndex];

        DB::transaction(function () use ($target, $neighbor) {
            $targetOrder = $target->sort_order;
            $target->update(['sort_order' => $neighbor->sort_order]);
            $neighbor->update(['sort_order' => $targetOrder]);
        });
    }

    public function updateShowLetterhead(EvaluationFormVersion $version, bool $show): void
    {
        $this->assertEditable($version);

        $version->update(['show_letterhead' => $show]);
    }

    public function syncOutcomes(EvaluationFormVersion $version, array $orderedOutcomeIds): void
    {
        $this->assertEditable($version);

        $sync = [];
        foreach (array_values($orderedOutcomeIds) as $index => $outcomeId) {
            $sync[$outcomeId] = ['sort_order' => $index + 1];
        }

        $version->presentationOutcomes()->sync($sync);
    }

    public function syncApplicableModes(EvaluationFormVersion $version, array $modeIds): void
    {
        $this->assertEditable($version);

        $version->applicablePresentationModes()->sync($modeIds);
    }

    public function validateForPublish(EvaluationFormVersion $version): array
    {
        $errors = [];

        $sections = $version->sections()->with('childCriteria')->get();

        if ($sections->isEmpty()) {
            $errors[] = 'Add at least one section before publishing.';
        }

        foreach ($sections as $section) {
            if ($section->childCriteria->isEmpty()) {
                $errors[] = "Section \"{$section->name}\" needs at least one item.";
            }
        }

        $weightTotal = round((float) $sections->sum('weight'), 2);
        if ($sections->isNotEmpty() && abs($weightTotal - 100) > 0.01) {
            $errors[] = "Section weights must add up to 100% (currently {$weightTotal}%).";
        }

        if ($version->applicablePresentationModes()->count() === 0) {
            $errors[] = 'Select a presentation mode (Standard or Title Proposal).';
        }

        if ($version->presentationOutcomes()->count() === 0) {
            $errors[] = 'Select at least one presentation outcome for the remarks/verdict section.';
        }

        return $errors;
    }

    public function publish(EvaluationFormVersion $version): EvaluationFormVersion
    {
        $this->assertDraft($version);

        $errors = $this->validateForPublish($version);
        if ($errors !== []) {
            throw ValidationException::withMessages(['publish' => $errors]);
        }

        return DB::transaction(function () use ($version) {
            $activeStatus = FormVersionStatus::where('code', 'ACTIVE')->firstOrFail();
            $retiredStatus = FormVersionStatus::where('code', 'RETIRED')->firstOrFail();

            // Unreachable in the current flow: a form has exactly one
            // version, so publishing its draft means nothing is active yet.
            // Kept for the few forms built before versions were removed
            // (2026-09-22), which can still carry a leftover draft beside a
            // published row - publishing that must still retire the old one.
            $currentActive = $version->evaluationForm->activeVersion();
            if ($currentActive) {
                $currentActive->update(['status_id' => $retiredStatus->id, 'retired_at' => now()]);
            }

            $version->update([
                'status_id' => $activeStatus->id,
                'total_weight' => 100,
                'maximum_total_score' => $version->scale_max,
                'activated_at' => now(),
            ]);

            return $version->fresh();
        });
    }

    public function archiveForm(EvaluationForm $form): void
    {
        $form->update(['is_active' => false]);
    }

    /**
     * Categories currently (effective_until null) using one of this form's
     * versions whose own status is anything other than DRAFT (not yet live)
     * or COMPLETED/ARCHIVED (already finished) — i.e. registration open,
     * setup incomplete, queue generated/ready, upcoming, or active. Originally
     * this only checked UPCOMING/ACTIVE, but category_status_id is never
     * actually set to ACTIVE anywhere in the codebase (PresentationCategory::
     * deriveStatus() never derives it — that status is reserved for a later
     * module) and UPCOMING only covers the narrow pre-registration window, so
     * a category that had already closed registration and generated its
     * queue (READY_FOR_QUEUE) was never blocked. Broadened so a form actually
     * in use by a real, in-progress category can't be deleted out from under it.
     */
    public function blockingCategoriesForDeletion(EvaluationForm $form): Collection
    {
        $versionIds = $form->evaluationFormVersions()->pluck('id');

        // In use as the category's form, or as one of its tracks' forms.
        return PresentationCategory::where(function ($query) use ($versionIds) {
            $query->whereHas('categoryEvaluationForms', function ($query) use ($versionIds) {
                $query->whereNull('effective_until')->whereIn('evaluation_form_version_id', $versionIds);
            })->orWhereHas('researchTracks', function ($query) use ($versionIds) {
                $query->where('is_active', true)->whereIn('evaluation_form_version_id', $versionIds);
            });
        })
            ->whereHas('categoryStatus', fn ($query) => $query->whereNotIn('code', ['DRAFT', 'COMPLETED', 'ARCHIVED']))
            ->with('categoryStatus')
            ->get();
    }

    public function deleteForm(EvaluationForm $form): void
    {
        $blocking = $this->blockingCategoriesForDeletion($form);

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'delete' => 'Assigned to a category that is still in progress ('
                    .$blocking->pluck('name')->implode(', ')
                    .'). Change that category\'s assigned evaluation form first.',
            ]);
        }

        $versionIds = $form->evaluationFormVersions()->pluck('id');

        if (EvaluationSubmission::whereIn('evaluation_form_version_id', $versionIds)->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'This form has real submitted evaluations recorded against it and cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($form, $versionIds) {
            DB::table('evaluation_form_version_outcomes')->whereIn('evaluation_form_version_id', $versionIds)->delete();
            DB::table('evaluation_form_version_presentation_modes')->whereIn('evaluation_form_version_id', $versionIds)->delete();
            CategoryEvaluationForm::whereIn('evaluation_form_version_id', $versionIds)->delete();
            EvaluationCriterion::whereIn('evaluation_form_version_id', $versionIds)->whereNotNull('parent_criterion_id')->delete();
            EvaluationCriterion::whereIn('evaluation_form_version_id', $versionIds)->whereNull('parent_criterion_id')->delete();
            EvaluationScaleLabel::whereIn('evaluation_form_version_id', $versionIds)->delete();
            EvaluationFormVersion::whereIn('id', $versionIds)->delete();
            $form->delete();
        });
    }
}
