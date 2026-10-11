@extends('layouts.panelist')

@section('title', 'Join Room')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card-brand p-4 p-md-5 text-center mt-4">
                <h1 class="h4 mb-3">Join This Room?</h1>

                <dl class="detail-list small mb-4 text-start">
                    <dt>Room</dt>
                    <dd>{{ $terminal->roomSession->presentationDateRoom->room_name }}</dd>

                    <dt>Category</dt>
                    <dd>{{ $terminal->roomSession->presentationDateRoom->presentationDate->category->name ?? '—' }}</dd>
                </dl>

                <p class="text-brand-muted small mb-4">
                    You'll join as <strong>{{ trim((auth()->user()->profile->first_name ?? '') . ' ' . (auth()->user()->profile->last_name ?? '')) ?: auth()->user()->username }}</strong>.
                </p>

                <form method="POST" action="{{ route('panelist.terminal-scan.claim', $token) }}">
                    @csrf
                    <button type="submit" class="btn btn-brand w-100 mb-2"><x-icon name="check" /> Confirm &amp; Join</button>
                </form>
                <a href="{{ route('panelist.dashboard') }}" class="btn btn-outline-brand w-100">Cancel</a>
            </div>
        </div>
    </div>
@endsection
