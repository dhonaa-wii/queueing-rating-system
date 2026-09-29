<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationForm;
use App\Models\EvaluationFormVersion;
use App\Services\EvaluationFormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EvaluationCriterionController extends Controller
{
    public function __construct(private EvaluationFormBuilderService $builder)
    {
    }

    private function back(EvaluationForm $evaluationForm)
    {
        return redirect(route('admin.evaluation-library.show', $evaluationForm));
    }

    private function handle(Request $request, EvaluationForm $evaluationForm, \Closure $action, string $successMessage)
    {
        try {
            $result = $action();
        } catch (ValidationException $e) {
            $message = implode(' ', Arr::flatten($e->errors()));

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return $this->back($evaluationForm)->with('error', $message);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $successMessage,
                'id' => $result instanceof EvaluationCriterion ? $result->id : null,
            ]);
        }

        return $this->back($evaluationForm)->with('status', $successMessage);
    }

    public function storeSection(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'position' => ['nullable', 'in:before,after'],
            'reference_id' => ['nullable', 'integer', 'exists:evaluation_criteria,id'],
        ]);

        return $this->handle($request, $evaluationForm, fn () => $this->builder->addSection(
            $version,
            $validated,
            $validated['position'] ?? null,
            $validated['reference_id'] ?? null
        ), 'Section added.');
    }

    public function updateSection(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $section)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        return $this->handle($request, $evaluationForm, fn () => $this->builder->updateSection($section, $validated), 'Section updated.');
    }

    public function destroySection(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $section)
    {
        return $this->handle($request, $evaluationForm, fn () => $this->builder->deleteSection($section), 'Section removed.');
    }

    public function moveSection(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $section)
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        return $this->handle($request, $evaluationForm, fn () => $this->builder->moveSection($section, $direction), 'Section reordered.');
    }

    public function storeItem(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $section)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'position' => ['nullable', 'in:before,after'],
            'reference_id' => ['nullable', 'integer', 'exists:evaluation_criteria,id'],
        ]);

        return $this->handle($request, $evaluationForm, fn () => $this->builder->addItem(
            $section,
            $validated,
            $validated['position'] ?? null,
            $validated['reference_id'] ?? null
        ), 'Item added.');
    }

    public function updateItem(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $item)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        return $this->handle($request, $evaluationForm, fn () => $this->builder->updateItem($item, $validated), 'Item updated.');
    }

    public function destroyItem(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $item)
    {
        return $this->handle($request, $evaluationForm, fn () => $this->builder->deleteItem($item), 'Item removed.');
    }

    public function moveItem(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version, EvaluationCriterion $item)
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        return $this->handle($request, $evaluationForm, fn () => $this->builder->moveItem($item, $direction), 'Item reordered.');
    }
}
