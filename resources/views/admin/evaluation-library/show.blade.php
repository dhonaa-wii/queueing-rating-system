@extends('layouts.admin')

@section('title', $evaluationForm->name)
@section('back-link')
    @include('partials.back-link', ['href' => route('admin.evaluation-library.index')])
@endsection

@section('content')
    @if (! $editingVersion)
        <div class="card-brand p-4 text-center text-brand-muted">This form has no content yet.</div>
    @else
        <div class="eval-workspace">
            <div class="eval-workspace-paper">
                @include('admin.evaluation-library.partials.paper', [
                    'evaluationForm' => $evaluationForm,
                    'editingVersion' => $editingVersion,
                    'letterhead' => $letterhead,
                    'readOnly' => $readOnly,
                ])
            </div>

            @include('admin.evaluation-library.partials.tools-panel', [
                'evaluationForm' => $evaluationForm,
                'editingVersion' => $editingVersion,
                'publishErrors' => $publishErrors,
                'canEdit' => $canEdit,
                'allModes' => $allModes,
                'allOutcomes' => $allOutcomes,
                'letterhead' => $letterhead,
                'readOnly' => $readOnly,
            ])
        </div>

        @include('admin.evaluation-library.partials.manage-outcomes-modal', [
            'allOutcomesForManagement' => $allOutcomesForManagement,
        ])

        @include('admin.evaluation-library.partials.workspace-scripts')
    @endif
@endsection
