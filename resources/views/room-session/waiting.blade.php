{{--
    The room account is valid but its room hasn't been started for today yet.
    Shares the two-column entry shell with the sign-in and picker screens
    (room-session/partials/entry-styles + entry-intro).
--}}
@extends('layouts.app')

@section('title', 'Room Session — ' . $account->room_name)

@section('navbar-brand', 'Room Session')

{{-- Fluid: Bootstrap's .container caps at 540px between 576px and 767px, which
     squeezes a tablet's two columns into a phone-width strip. --}}
@section('container-class', 'container-fluid px-3 px-sm-4')

@push('styles')
    @include('room-session.partials.entry-styles')
@endpush

@section('content')
    <div class="rs-auth">
        <div class="rs-grid">
            @include('room-session.partials.entry-intro', ['introTitle' => 'This room hasn\'t started yet.'])

            <section class="rs-form-col">
                <div class="card-brand rs-card">
                    <div class="rs-card-head">
                        <h2 class="rs-card-title">{{ $account->room_name }}</h2>
                        <p class="rs-card-sub">Waiting for the Administrator to start this room.</p>
                    </div>

                    <div class="rs-actions">
                        <a href="{{ route('room-session.terminal') }}" class="btn btn-brand"><x-icon name="refresh" /> Check Again</a>
                        <form method="POST" action="{{ route('room-session.cancel') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-brand"><x-icon name="log-out" /> Log Out</button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
