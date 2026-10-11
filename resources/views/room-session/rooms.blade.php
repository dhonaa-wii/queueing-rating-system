{{--
    Room Session entry (user-directed 2026-10-11 redesign): every room that is
    started and not yet closed, grouped by category. Any device picks one and
    watches it; panelists sign in on the room screen itself. Replaces the old
    room-account sign-in and terminal picker.

    Shares the two-column entry shell (room-session/partials/entry-styles +
    entry-intro).
--}}
@extends('layouts.app')

@section('title', 'Room Session')

@section('navbar-brand', 'Room Session')

{{-- Fluid: Bootstrap's .container caps at 540px between 576px and 767px, which
     squeezes a tablet's two columns into a phone-width strip. --}}
@section('container-class', 'container-fluid px-3 px-sm-4')

@push('styles')
    @include('room-session.partials.entry-styles')
@endpush

@section('back-link')
    @include('partials.back-link', ['href' => route('landing')])
@endsection

@section('content')
    <div class="rs-auth">
        <div class="rs-grid">
            @include('room-session.partials.entry-intro')

            <section class="rs-form-col">
                <div class="card-brand rs-card">
                    <div class="rs-card-head">
                        <h2 class="rs-card-title">Running Rooms</h2>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if ($roomsByCategory->isEmpty())
                        <p class="rs-note mb-3">No room is running right now.</p>
                    @else
                        <form method="POST" action="{{ route('room-session.join') }}">
                            @csrf

                            @foreach ($roomsByCategory as $sessions)
                                @php $category = $sessions->first()->presentationDateRoom->presentationDate->category; @endphp
                                <p class="rs-group-label">{{ $category->name }}</p>

                                <div class="rs-choices">
                                    @foreach ($sessions as $roomSession)
                                        @php $seat = $seats[$roomSession->id]; @endphp
                                        <label class="rs-choice" for="room-{{ $roomSession->id }}">
                                            <input class="form-check-input" type="radio" name="room_session_id" id="room-{{ $roomSession->id }}"
                                                   value="{{ $roomSession->id }}" required>
                                            <span class="rs-choice-label">
                                                <span>{{ $roomSession->presentationDateRoom->room_name }}</span>
                                                <span class="badge {{ $seat['full'] ? 'badge-success-tint' : 'badge-muted-tint' }}">
                                                    {{ $seat['full'] ? 'Panel complete' : 'Panel' }} &middot; {{ $seat['taken'] }}/{{ $seat['total'] }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach

                            <button type="submit" class="btn btn-brand w-100"><x-icon name="log-in" /> Open Room</button>
                        </form>
                    @endif

                    <div class="rs-actions">
                        <a href="{{ route('room-session.entry') }}" class="btn btn-outline-brand"><x-icon name="refresh" /> Refresh</a>
                    </div>
                </div>
            </section>

        </div>
    </div>
@endsection
