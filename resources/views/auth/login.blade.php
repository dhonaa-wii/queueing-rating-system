@extends('layouts.app')

@section('title', 'Login')

@section('back-link')
    @include('partials.back-link', ['href' => route('landing')])
@endsection

@include('auth.partials.styles')

@section('content')
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-head">
                <span class="auth-head-icon"><x-icon name="log-in" /></span>
                <div>
                    <h1 class="auth-title">Welcome back</h1>
                    <p class="auth-sub">Sign in to your ARPQRS account.</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="auth-field">
                    <label for="username">Username</label>
                    <div class="auth-input">
                        <x-icon name="user" />
                        <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="auth-field mb-3">
                    <label for="password">Password</label>
                    <div class="auth-input">
                        <x-icon name="lock" />
                        <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
                        <button type="button" class="auth-eye" data-auth-eye="password" aria-label="Show password" aria-pressed="false"><x-icon name="eye" /></button>
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100"><x-icon name="log-in" /> Log in</button>
            </form>

            <div class="auth-divider">or</div>

            <a href="{{ route('landing') }}#presentations" class="btn btn-outline-brand w-100"><x-icon name="graduation-cap" /> Student? Browse Presentations</a>
        </div>
    </div>
@endsection
