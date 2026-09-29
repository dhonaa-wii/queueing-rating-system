{{-- Event Control redirects straight to a category's workspace (see
     LiveMonitoringController::index()), so this view is only ever reached
     when there is no category left that hasn't ended or been archived. --}}
@extends('layouts.admin')

@section('title', 'Event Control')
@section('heading', 'Event Control')

@section('content')
    <div class="picker-empty">
        <h2>No open categories</h2>
        <p>Every category has either ended or been archived — check Presentation Setup or Reports instead.</p>
    </div>
@endsection
