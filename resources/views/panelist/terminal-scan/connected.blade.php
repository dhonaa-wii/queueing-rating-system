@extends('layouts.panelist')

@section('title', 'Connected')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card-brand p-4 p-md-5 text-center mt-4">
                <span class="badge badge-success-tint mb-3">Connected</span>
                <h1 class="h4 mb-2">Terminal {{ $terminal->terminal_number }}</h1>
                <p class="text-brand-muted mb-4">
                    {{ $terminal->roomSession->presentationDateRoom->room_name }} &mdash;
                    {{ $terminal->terminalType->name ?? 'Panelist' }}
                </p>
                <p class="mb-4">You can return to the tablet now — it will pick this up automatically.</p>
                @include('partials.back-link', ['href' => route('panelist.dashboard'), 'button' => true, 'fullWidth' => true])
            </div>
        </div>
    </div>
@endsection
