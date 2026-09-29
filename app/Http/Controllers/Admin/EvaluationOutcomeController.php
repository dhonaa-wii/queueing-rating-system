<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationForm;
use App\Models\EvaluationFormVersion;
use App\Models\PresentationOutcome;
use App\Services\EvaluationFormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationOutcomeController extends Controller
{
    public function __construct(private EvaluationFormBuilderService $builder)
    {
    }

    public function store(Request $request)
    {
        $validated = [...$this->validated($request), 'is_active' => true];

        PresentationOutcome::create($validated);

        return back()->with('status', 'Presentation outcome added.');
    }

    public function update(Request $request, PresentationOutcome $outcome)
    {
        $validated = $this->validated($request, $outcome->id);

        $outcome->update($validated);

        return back()->with('status', 'Presentation outcome updated.');
    }

    public function toggleActive(PresentationOutcome $outcome)
    {
        $outcome->update(['is_active' => ! $outcome->is_active]);

        return back()->with('status', 'Status updated.');
    }

    public function syncForVersion(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version)
    {
        $validated = $request->validate([
            'outcome_ids' => ['nullable', 'array'],
            'outcome_ids.*' => ['integer', 'exists:presentation_outcomes,id'],
        ]);

        try {
            $this->builder->syncOutcomes($version, $validated['outcome_ids'] ?? []);
        } catch (ValidationException $e) {
            $message = implode(' ', $e->errors()['version'] ?? ['Unable to update outcomes.']);

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('error', $message);
        }

        $message = 'Remarks/verdict options updated.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('status', $message);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return [
            ...$request->validate([
                'code' => ['required', 'string', 'max:50', Rule::unique('presentation_outcomes', 'code')->ignore($ignoreId)],
                'name' => ['required', 'string', 'max:150'],
                'requires_new_attempt' => ['nullable', 'boolean'],
                'allows_project_title_change' => ['nullable', 'boolean'],
                'is_successful' => ['nullable', 'in:0,1,'],
            ]),
            'requires_new_attempt' => $request->boolean('requires_new_attempt'),
            'allows_project_title_change' => $request->boolean('allows_project_title_change'),
            'is_successful' => $request->input('is_successful') === '' ? null : $request->boolean('is_successful'),
        ];
    }
}
