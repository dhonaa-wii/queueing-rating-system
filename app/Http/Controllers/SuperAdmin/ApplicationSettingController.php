<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\College;
use App\Models\Semester;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationSettingController extends Controller
{
    private const TYPES = [
        'colleges' => College::class,
        'academic-years' => AcademicYear::class,
        'semesters' => Semester::class,
    ];

    private const EXCLUSIVE_ACTIVE_TYPES = ['academic-years', 'semesters'];

    public function index()
    {
        return view('super-admin.settings.application', [
            'campus' => Campus::first(),
            'colleges' => College::orderBy('name')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_year')->get(),
            'semesters' => Semester::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $modelClass = $this->resolveType($type);
        $validated = $this->validated($request, $type);

        $model = $modelClass::create($validated);

        if (in_array($type, self::EXCLUSIVE_ACTIVE_TYPES, true) && ($validated['is_active'] ?? false)) {
            $this->unsetSiblings($modelClass, $model->id);
        }

        return back()->with('status', 'Added.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $modelClass = $this->resolveType($type);
        $model = $modelClass::findOrFail($id);
        $validated = $this->validated($request, $type, $model->id);

        $model->update($validated);

        if (in_array($type, self::EXCLUSIVE_ACTIVE_TYPES, true) && ($validated['is_active'] ?? false)) {
            $this->unsetSiblings($modelClass, $model->id);
        }

        return back()->with('status', 'Updated.');
    }

    public function destroy(string $type, int $id)
    {
        $modelClass = $this->resolveType($type);
        $model = $modelClass::findOrFail($id);

        try {
            $model->delete();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return back()->with('error', $this->inUseMessage($type));
            }

            throw $e;
        }

        return back()->with('status', 'Deleted.');
    }

    private function inUseMessage(string $type): string
    {
        return match ($type) {
            'colleges' => 'Cannot delete this college — it is still assigned to administrators or presentation categories.',
            'academic-years' => 'Cannot delete this academic year — it is still referenced by presentation categories.',
            'semesters' => 'Cannot delete this semester — it is still referenced by presentation categories.',
            default => 'Cannot delete this record — it is still in use elsewhere.',
        };
    }

    public function toggleActive(string $type, int $id)
    {
        $modelClass = $this->resolveType($type);
        $model = $modelClass::findOrFail($id);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('status', 'Status updated.');
    }

    public function setActive(string $type, int $id)
    {
        abort_unless(in_array($type, self::EXCLUSIVE_ACTIVE_TYPES, true), 404);

        $modelClass = $this->resolveType($type);
        $modelClass::whereKey($id)->update(['is_active' => true]);
        $this->unsetSiblings($modelClass, $id);

        return back()->with('status', 'Set as the active '.($type === 'academic-years' ? 'academic year' : 'semester').'.');
    }

    private function unsetSiblings(string $modelClass, int $exceptId): void
    {
        $modelClass::where('id', '!=', $exceptId)->update(['is_active' => false]);
    }

    private function resolveType(string $type): string
    {
        return self::TYPES[$type] ?? abort(404);
    }

    private function validated(Request $request, string $type, ?int $ignoreId = null): array
    {
        return match ($type) {
            'colleges' => [
                ...$request->validate([
                    'code' => ['required', 'string', 'max:30', Rule::unique('colleges', 'code')->ignore($ignoreId)],
                    'name' => ['required', 'string', 'max:150'],
                ]),
                'campus_id' => Campus::firstOrFail()->id,
                'is_active' => $request->boolean('is_active'),
            ],

            'academic-years' => [
                ...$request->validate([
                    'name' => ['required', 'string', 'max:20', Rule::unique('academic_years', 'name')->ignore($ignoreId)],
                    'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
                    'end_year' => ['required', 'integer', 'gte:start_year'],
                ]),
                'is_active' => $request->boolean('is_active'),
            ],

            'semesters' => [
                ...$request->validate([
                    'code' => ['required', 'string', 'max:30', Rule::unique('semesters', 'code')->ignore($ignoreId)],
                    'name' => ['required', 'string', 'max:100'],
                    'sort_order' => ['required', 'integer', 'min:0', 'max:255'],
                ]),
                'is_active' => $request->boolean('is_active'),
            ],

            default => abort(404),
        };
    }
}
