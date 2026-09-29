{{--
    Room session sign-in. Two columns on wide screens: a feature panel on the
    left introducing what a claimed terminal does, and the compact room
    account form on the right. Stacks to one column below lg, where the form
    comes first so the inputs stay above the fold on a phone.

    Styles are page-local (.rs-*) and sized with clamp() so the panel reads
    the same on a tablet as on a desktop.
--}}
@extends('layouts.app')

@section('title', 'Room Session')

@section('navbar-brand', 'Room Session')

{{--
    Fluid, not Bootstrap's fixed .container: that one is capped at 540px for
    every viewport from 576px to 767px, so a tablet at 744px (iPad mini and
    friends) had the whole two-column layout squeezed into 540px and the card
    was left barely wider than its own inputs. .rs-auth caps the column itself.
--}}
@section('container-class', 'container-fluid px-3 px-sm-4')

@push('styles')
    @include('room-session.partials.entry-styles')
@endpush

@section('content')
    <div class="rs-auth">
        <div class="rs-grid">
            @include('room-session.partials.entry-intro')


            <section class="rs-form-col">
                <div class="card-brand rs-card">
                    <div class="rs-card-head">
                        <h2 class="rs-card-title">Room Session Access</h2>
                        <p class="rs-card-sub">Use the room account for today's session.</p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('room-session.login') }}">
                        @csrf

                        <div class="rs-field">
                            <label for="username" class="form-label">Room Account Username</label>
                            <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required autofocus>
                        </div>

                        <div class="rs-field">
                            <label for="password" class="form-label">Room Account Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-brand w-100"><x-icon name="log-in" /> Join Room Session</button>
                    </form>
                </div>
            </section>

            <p class="rs-back">
                @include('partials.back-link', ['href' => route('landing')])
            </p>
        </div>
    </div>
@endsection
