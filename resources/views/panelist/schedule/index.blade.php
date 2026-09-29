{{-- View Schedule redirects straight to a category's workspace (see
     ScheduleController::index()), so this view is only ever reached when no
     category has a schedule configured at all. --}}
@extends('layouts.panelist')

@section('title', 'View Schedule')

@section('content')
    <div class="picker-empty">
        <h2>No schedules yet</h2>
        <p>No category has a presentation schedule configured yet — check back once one does.</p>
    </div>
@endsection
