{{--
    Expects: $evaluationForm, $editingVersion (applicablePresentationModes loaded), $allModes, $readOnly
--}}
@php
    $selectedModeId = $editingVersion->applicablePresentationModes->first()?->id;
@endphp

<div class="eval-tools-section">
    <h4 class="eval-tools-heading">Presentation Mode</h4>

    @if ($readOnly)
        <p class="mb-0 small">
            {{ $editingVersion->applicablePresentationModes->first()?->name ?? 'Not set' }}
        </p>
    @else
        <form method="POST" action="{{ route('admin.evaluation-library.versions.modes.sync', [$evaluationForm, $editingVersion]) }}" data-ajax="refresh">
            @csrf
            @method('PUT')
            @foreach ($allModes as $mode)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="mode_id" value="{{ $mode->id }}"
                           id="mode-{{ $mode->id }}" data-autosave-change @checked($selectedModeId == $mode->id) required>
                    <label class="form-check-label small" for="mode-{{ $mode->id }}">{{ $mode->name }}</label>
                </div>
            @endforeach
        </form>
    @endif
</div>
