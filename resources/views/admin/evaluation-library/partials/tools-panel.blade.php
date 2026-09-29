{{--
    Expects: $evaluationForm, $editingVersion, $publishErrors, $canEdit, $allModes, $allOutcomes, $letterhead, $readOnly
--}}
<div class="eval-tools-panel" data-tools-panel>
    <button type="button" class="eval-tools-toggle" data-tools-toggle title="Collapse tools panel">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"></path></svg>
    </button>

    <div class="eval-tools-panel-inner">
        @include('admin.evaluation-library.partials.tools-status', ['evaluationForm' => $evaluationForm, 'editingVersion' => $editingVersion, 'publishErrors' => $publishErrors, 'canEdit' => $canEdit, 'readOnly' => $readOnly])
        @include('admin.evaluation-library.partials.tools-mode', ['evaluationForm' => $evaluationForm, 'editingVersion' => $editingVersion, 'allModes' => $allModes, 'readOnly' => $readOnly])
        @include('admin.evaluation-library.partials.tools-letterhead', ['evaluationForm' => $evaluationForm, 'editingVersion' => $editingVersion, 'letterhead' => $letterhead, 'readOnly' => $readOnly])
        @include('admin.evaluation-library.partials.tools-outcomes', ['evaluationForm' => $evaluationForm, 'editingVersion' => $editingVersion, 'allOutcomes' => $allOutcomes, 'readOnly' => $readOnly])
    </div>
</div>
