{{--
    Device-claim step: which terminal number is this physical tablet? Shares
    the two-column entry shell with the sign-in and waiting screens
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
    @include('partials.toast-stack')

    <div class="rs-auth">
        <div class="rs-grid">
            @include('room-session.partials.entry-intro')

            <section class="rs-form-col">
                <div class="card-brand rs-card">
                    <div class="rs-card-head">
                        <h2 class="rs-card-title">{{ $account->room_name }}</h2>
                        <p class="rs-card-sub">Which terminal is this tablet?</p>
                    </div>

                    <form method="POST" action="{{ route('room-session.terminal.claim') }}">
                        @csrf

                        <div class="rs-choices">
                            @foreach ($terminals as $terminal)
                                <label class="rs-choice {{ $terminal->device_identifier ? 'rs-choice-taken' : '' }}"
                                       for="term-{{ $terminal->id }}"
                                       title="{{ $terminal->device_identifier ? 'Already set up on another device — ask an Administrator to release it first.' : '' }}">
                                    <input class="form-check-input" type="radio" name="room_terminal_id" id="term-{{ $terminal->id }}"
                                           value="{{ $terminal->id }}" required @disabled($terminal->device_identifier)>
                                    <span class="rs-choice-label">
                                        <span>Terminal {{ $terminal->terminal_number }}</span>
                                        @if ($terminal->device_identifier)
                                            <span class="badge badge-muted-tint">Already set up on another device</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-brand w-100"><x-icon name="monitor" /> Set Up This Tablet</button>
                    </form>

                    <div class="rs-actions">
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
