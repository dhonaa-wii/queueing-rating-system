{{-- Group & Panel Assignment redirects straight to a category's workspace
     (see PanelAssignmentController::index()), so this view is only ever
     reached when no category has a generated queue yet. --}}
@extends('layouts.admin')

@section('title', 'Group & Panel Assignment')
@section('heading', 'Group & Panel Assignment')

@section('content')
    <div class="picker-empty">
        <h2>No categories are ready for panel assignment</h2>
        <p>Panel assignment becomes available once a category's queue is generated in Presentation Setup.</p>
    </div>
@endsection
