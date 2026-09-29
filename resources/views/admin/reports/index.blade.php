{{-- Reports redirects straight to a category's Generated Grades (see
     ReportController::index()), so this view is only ever reached when no
     category has a submitted evaluation to report on yet. --}}
@extends('layouts.admin')

@section('title', 'Reports')
@section('heading', 'Reports')

@section('content')
    <div class="page-narrow">
        <div class="picker-empty">
            <h2>No evaluations have been submitted yet</h2>
            <p>The Generated Grades report becomes available once panelists have submitted evaluations for a Standard-mode category.</p>
        </div>
    </div>
@endsection
