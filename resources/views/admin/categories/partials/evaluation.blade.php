@php
    $activeAssignment = $category->categoryEvaluationForms->whereNull('effective_until')->sortByDesc('effective_from')->first();
@endphp

<div class="card-brand p-4" style="max-width: 720px;">
    <h3 class="h6 mb-3">
        Evaluation Configuration
        @unless ($category->isEvaluationConfigured())<span class="tab-incomplete-dot"></span>@endunless
    </h3>

    @if ($activeAssignment)
        <div class="alert alert-success">
            Currently assigned: <strong>{{ $activeAssignment->evaluationFormVersion->evaluationForm->name }}</strong>
            (v{{ $activeAssignment->evaluationFormVersion->version_number }})
            since {{ $activeAssignment->effective_from->format('M j, Y') }}.
        </div>
    @else
        <div class="alert alert-warning">No evaluation form is currently assigned to this category.</div>
    @endif

    @if ($availableFormVersions->isEmpty())
        <p class="text-brand-muted">No evaluation forms are available yet. Build a form in the Evaluation Library module first.</p>
    @else
        <form method="POST" action="{{ route('admin.categories.evaluation-config.update', $category) }}" class="d-flex gap-2" data-ajax="update">
            @csrf
            @method('PUT')

            <select name="evaluation_form_version_id" class="form-select" required>
                <option value="">Select a form&hellip;</option>
                @foreach ($availableFormVersions as $version)
                    <option value="{{ $version->id }}">{{ $version->evaluationForm->name }} (v{{ $version->version_number }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-brand text-nowrap"><x-icon name="user-check" /> Assign Form</button>
        </form>
    @endif
</div>
