@php
    $activeAssignment = $category->categoryEvaluationForms->whereNull('effective_until')->sortByDesc('effective_from')->first();
    $usesTrackForms = $category->usesTrackEvaluationForms();
    $activeTracks = $category->researchTracks->where('is_active', true)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp

<div class="card-brand p-4" style="max-width: 720px;">
    <h3 class="h6 mb-3">
        Evaluation Configuration
        @unless ($category->isEvaluationConfigured())<span class="tab-incomplete-dot"></span>@endunless
    </h3>

    @if ($usesTrackForms)
        {{-- Track required: every track has its own form (user-directed 2026-10-04). --}}
        @if ($activeTracks->isEmpty())
            <div class="alert alert-warning mb-0">No tracks yet.</div>
        @elseif ($availableFormVersions->isEmpty())
            <p class="text-brand-muted mb-0">No evaluation forms are available yet. Build a form in the Evaluation Library module first.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Track</th>
                            <th>Evaluation Form</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activeTracks as $track)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $track->name }}
                                    @unless ($track->evaluation_form_version_id)<span class="tab-incomplete-dot"></span>@endunless
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.categories.tracks.evaluation-form.update', [$category, $track]) }}"
                                          class="d-flex gap-2" data-ajax="update" data-ajax-refresh="tab-evaluation">
                                        @csrf
                                        @method('PUT')
                                        <select name="evaluation_form_version_id" class="form-select form-select-sm" required aria-label="Evaluation form for {{ $track->name }}">
                                            <option value="">Select a form&hellip;</option>
                                            @foreach ($availableFormVersions as $version)
                                                <option value="{{ $version->id }}" @selected($track->evaluation_form_version_id === $version->id)>{{ $version->evaluationForm->name }}</option>
                                            @endforeach
                                            {{-- A form assigned earlier that is no longer offered (unpublished, other mode) still shows as selected. --}}
                                            @if ($track->evaluationFormVersion && ! $availableFormVersions->contains('id', $track->evaluation_form_version_id))
                                                <option value="{{ $track->evaluation_form_version_id }}" selected disabled>{{ $track->evaluationFormVersion->evaluationForm->name }}</option>
                                            @endif
                                        </select>
                                        <button type="submit" class="btn btn-brand btn-sm text-nowrap"><x-icon name="user-check" /> Assign</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        @if ($activeAssignment)
            <div class="alert alert-success">
                Currently assigned: <strong>{{ $activeAssignment->evaluationFormVersion->evaluationForm->name }}</strong>
                since {{ $activeAssignment->effective_from->format('M j, Y') }}.
            </div>
        @else
            <div class="alert alert-warning">No evaluation form is currently assigned to this category.</div>
        @endif

        @if ($availableFormVersions->isEmpty())
            <p class="text-brand-muted">No evaluation forms are available yet. Build a form in the Evaluation Library module first.</p>
        @else
            <form method="POST" action="{{ route('admin.categories.evaluation-config.update', $category) }}" class="d-flex gap-2" data-ajax="update" data-ajax-refresh="tab-evaluation">
                @csrf
                @method('PUT')

                <select name="evaluation_form_version_id" class="form-select" required>
                    <option value="">Select a form&hellip;</option>
                    @foreach ($availableFormVersions as $version)
                        <option value="{{ $version->id }}">{{ $version->evaluationForm->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-brand text-nowrap"><x-icon name="user-check" /> Assign Form</button>
            </form>
        @endif
    @endif
</div>
