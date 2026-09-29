{{--
    Expects: $evaluationForm, $editingVersion, $letterhead, $readOnly

    Visibility toggle only — letterhead *management* (the text lines)
    stays on the separate, untouched Letterhead admin page.
--}}
<div class="eval-tools-section">
    <h4 class="eval-tools-heading">Letterhead</h4>

    @if ($readOnly)
        <p class="mb-0 small">{{ $editingVersion->show_letterhead ? 'Shown' : 'Hidden' }}</p>
    @else
        <form method="POST" action="{{ route('admin.evaluation-library.versions.letterhead.update', [$evaluationForm, $editingVersion]) }}" data-ajax="refresh">
            @csrf
            @method('PUT')
            <select name="show_letterhead" class="form-select form-select-sm" data-autosave-change @disabled(! $letterhead)>
                <option value="0" @selected(! $editingVersion->show_letterhead)>None</option>
                <option value="1" @selected($editingVersion->show_letterhead)>{{ $letterhead ? 'Default Letterhead' : 'Default Letterhead (not configured)' }}</option>
            </select>
        </form>
        @unless ($letterhead)
            <p class="text-brand-muted small mt-1 mb-0">
                No letterhead configured yet.
                <a href="{{ route('admin.evaluation-library.letterhead.edit') }}">Set one up &rarr;</a>
            </p>
        @endunless
    @endif
</div>
