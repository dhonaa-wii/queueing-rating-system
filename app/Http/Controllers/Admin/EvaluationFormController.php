<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationForm;
use App\Models\EvaluationLetterhead;
use App\Models\PresentationMode;
use App\Models\PresentationOutcome;
use App\Services\EvaluationFormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvaluationFormController extends Controller
{
    public function __construct(private EvaluationFormBuilderService $builder)
    {
    }

    public function index()
    {
        // The cards are drawn as miniature evaluation sheets, so each one
        // needs the same pieces the real paper shows — its sections and
        // weights, its mode, whether it carries the letterhead.
        $forms = EvaluationForm::with([
            'evaluationFormVersions.status',
            'evaluationFormVersions.evaluationCriteria',
            'evaluationFormVersions.applicablePresentationModes',
            'createdBy.profile',
        ])
            ->orderByDesc('created_at')
            ->get();

        $blockingCategoriesByForm = $forms->mapWithKeys(
            fn (EvaluationForm $form) => [$form->id => $this->builder->blockingCategoriesForDeletion($form)]
        );

        return view('admin.evaluation-library.index', compact('forms', 'blockingCategoriesByForm'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
        ]);

        $form = $this->builder->createForm([
            'name' => ($validated['name'] ?? null) ?: 'Untitled Evaluation Form',
        ], $request->user());

        return redirect()->route('admin.evaluation-library.show', $form)
            ->with('status', 'Form created. Start typing directly on the sheet below.');
    }

    public function update(Request $request, EvaluationForm $evaluationForm)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        $this->builder->updateForm($evaluationForm, $validated);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Title updated.']);
        }

        return back()->with('status', 'Title updated.');
    }

    public function show(EvaluationForm $evaluationForm)
    {
        $evaluationForm->load(['evaluationFormVersions.status']);

        $editingVersion = $evaluationForm->currentVersion();

        $editingVersion?->load([
            'evaluationCriteria' => fn ($q) => $q->orderBy('sort_order'),
            'scaleLabels',
            'presentationOutcomes',
            'applicablePresentationModes',
        ]);

        $publishErrors = $editingVersion && $editingVersion->isDraft()
            ? $this->builder->validateForPublish($editingVersion)
            : [];

        $allOutcomes = PresentationOutcome::where('is_active', true)->orderBy('name')->get();
        $allOutcomesForManagement = PresentationOutcome::orderBy('name')->get();
        $allModes = PresentationMode::orderBy('name')->get();

        // The builder previews the letterhead of the Admin's own college —
        // there is one per college (2026-09-21), and an evaluation form
        // carries no college of its own.
        $letterhead = EvaluationLetterhead::forCollege(
            request()->user()?->administratorProfile?->college_id
        );

        // Versions were removed on 2026-09-22 (user-directed): a published
        // form is edited in place rather than copied into a new draft. It
        // still opens read-only, so the live sheet a category is already
        // assigned isn't retyped by accident - the tools panel's Edit button
        // is what unlocks it, and it does so by reloading with ?edit=1. An
        // unpublished draft has nothing to protect, so it is always editable.
        $canEdit = (bool) $editingVersion?->isEditable();
        $readOnly = ! $canEdit || (! $editingVersion->isDraft() && ! request()->boolean('edit'));

        return view('admin.evaluation-library.show', compact(
            'evaluationForm', 'editingVersion', 'publishErrors', 'allOutcomes', 'allOutcomesForManagement',
            'allModes', 'letterhead', 'canEdit', 'readOnly'
        ));
    }

    public function archive(EvaluationForm $evaluationForm)
    {
        $this->builder->archiveForm($evaluationForm);

        return redirect()->route('admin.evaluation-library.index')
            ->with('status', "\"{$evaluationForm->name}\" archived.");
    }

    public function destroy(EvaluationForm $evaluationForm)
    {
        $name = $evaluationForm->name;

        try {
            $this->builder->deleteForm($evaluationForm);
        } catch (ValidationException $e) {
            return redirect()->route('admin.evaluation-library.index')
                ->with('error', collect($e->errors())->collapse()->first());
        }

        return redirect()->route('admin.evaluation-library.index')
            ->with('status', "\"{$name}\" deleted.");
    }
}
