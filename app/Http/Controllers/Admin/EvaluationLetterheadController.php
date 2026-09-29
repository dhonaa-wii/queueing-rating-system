<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvaluationLetterhead;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Letterhead configuration for the signed-in Admin's own college. There is
 * one letterhead per college (2026-09-21), so an Admin only ever sees and
 * edits their own — the college is never a field on the form.
 */
class EvaluationLetterheadController extends Controller
{
    private const LOGO_DIR = 'evaluation-letterhead';

    public function edit(Request $request)
    {
        $college = $request->user()->administratorProfile?->college;

        $letterhead = EvaluationLetterhead::forCollege($college?->id)
            ?? new EvaluationLetterhead(['college_id' => $college?->id]);

        return view('admin.evaluation-library.letterhead', compact('letterhead', 'college'));
    }

    public function update(Request $request)
    {
        $college = $request->user()->administratorProfile?->college;

        if (! $college) {
            return back()->with('error', 'Your account has no college assigned, so there is no letterhead to configure. Ask a Super Admin to set one.');
        }

        $validated = $request->validate([
            'line_1' => ['nullable', 'string', 'max:200'],
            'line_2' => ['nullable', 'string', 'max:200'],
            'line_3' => ['nullable', 'string', 'max:200'],
            'line_4' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg,webp', 'max:2048'],
            'secondary_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_secondary_logo' => ['nullable', 'boolean'],
        ]);

        $letterhead = EvaluationLetterhead::forCollege($college->id)
            ?? new EvaluationLetterhead(['college_id' => $college->id]);

        $letterhead->fill([
            'college_id' => $college->id,
            'line_1' => $validated['line_1'] ?? null,
            'line_2' => $validated['line_2'] ?? null,
            'line_3' => $validated['line_3'] ?? null,
            'line_4' => $validated['line_4'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $letterhead->logo_path = $this->resolveLogo(
            $letterhead->logo_path,
            $request->file('logo'),
            (bool) ($validated['remove_logo'] ?? false)
        );

        $letterhead->secondary_logo_path = $this->resolveLogo(
            $letterhead->secondary_logo_path,
            $request->file('secondary_logo'),
            (bool) ($validated['remove_secondary_logo'] ?? false)
        );

        $letterhead->save();

        return redirect()->route('admin.evaluation-library.letterhead.edit')
            ->with('status', 'Letterhead updated. It will now appear on every evaluation form for '.$college->name.'.');
    }

    /**
     * A new upload replaces (and deletes) whatever was there; an explicit
     * remove clears it; otherwise the stored path is kept as-is.
     */
    private function resolveLogo(?string $current, ?UploadedFile $upload, bool $remove): ?string
    {
        if ($upload) {
            $this->deleteLogo($current);

            return $upload->store(self::LOGO_DIR, 'public');
        }

        if ($remove) {
            $this->deleteLogo($current);

            return null;
        }

        return $current;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
