<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationForm;
use App\Models\EvaluationFormVersion;
use App\Services\EvaluationFormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvaluationFormVersionController extends Controller
{
    public function __construct(private EvaluationFormBuilderService $builder)
    {
    }

    public function publish(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version)
    {
        try {
            $this->builder->publish($version);
        } catch (ValidationException $e) {
            $message = implode(' ', $e->errors()['publish'] ?? ['Unable to publish this form.']);

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('error', $message);
        }

        $message = "Version {$version->version_number} published and is now active.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('status', $message);
    }

    public function syncModes(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version)
    {
        $validated = $request->validate([
            'mode_id' => ['required', 'integer', 'exists:presentation_modes,id'],
        ]);

        try {
            $this->builder->syncApplicableModes($version, [$validated['mode_id']]);
        } catch (ValidationException $e) {
            $message = implode(' ', $e->errors()['version'] ?? ['Unable to update the presentation mode.']);

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('error', $message);
        }

        $message = 'Presentation mode updated.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('status', $message);
    }

    public function updateShowLetterhead(Request $request, EvaluationForm $evaluationForm, EvaluationFormVersion $version)
    {
        $validated = $request->validate([
            'show_letterhead' => ['required', 'boolean'],
        ]);

        try {
            $this->builder->updateShowLetterhead($version, $validated['show_letterhead']);
        } catch (ValidationException $e) {
            $message = implode(' ', $e->errors()['version'] ?? ['Unable to update the letterhead.']);

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('error', $message);
        }

        $message = 'Letterhead visibility updated.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.evaluation-library.show', $evaluationForm)->with('status', $message);
    }
}
