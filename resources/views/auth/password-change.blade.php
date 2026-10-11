@extends('layouts.app')

@section('title', 'Change Password')

@include('auth.partials.styles')

@push('styles')
    <style>
        .pw-rules { list-style: none; padding: 0; margin: 0.4rem 0 0; display: flex; flex-wrap: wrap; gap: 0.3rem; }
        .pw-rules li {
            font-size: 0.7rem; padding: 0.1rem 0.5rem; border-radius: 999px;
            background: var(--brand-surface); color: var(--brand-muted);
            transition: background-color .15s, color .15s;
        }
        .pw-rules li::before { content: '\2022'; margin-right: 0.3rem; }
        .pw-rules li.is-met { background: var(--brand-success-tint); color: var(--brand-success); }
        .pw-rules li.is-met::before { content: '\2713'; }
    </style>
@endpush

@section('content')
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-head">
                <span class="auth-head-icon"><x-icon name="key" /></span>
                <div>
                    <h1 class="auth-title">Change Password</h1>
                    <p class="auth-sub">Choose a new password for your account.</p>
                </div>
            </div>

            @if ($forced)
                <div class="alert alert-warning">You must change your password before continuing.</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <div class="auth-field">
                    <label for="current_password">Current Password</label>
                    <div class="auth-input">
                        <x-icon name="lock" />
                        <input type="password" name="current_password" id="current_password" class="form-control" required autofocus autocomplete="current-password">
                        <button type="button" class="auth-eye" data-auth-eye="current_password" aria-label="Show password" aria-pressed="false"><x-icon name="eye" /></button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">New Password</label>
                    <div class="auth-input">
                        <x-icon name="key" />
                        <input type="password" name="password" id="password" class="form-control" required
                               minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" autocomplete="new-password">
                        <button type="button" class="auth-eye" data-auth-eye="password" aria-label="Show password" aria-pressed="false"><x-icon name="eye" /></button>
                    </div>
                    <ul class="pw-rules" data-pw-rules>
                        <li data-rule="length">8+ characters</li>
                        <li data-rule="upper">Uppercase letter</li>
                        <li data-rule="lower">Lowercase letter</li>
                        <li data-rule="number">Number</li>
                    </ul>
                </div>

                <div class="auth-field mb-3">
                    <label for="password_confirmation">Confirm New Password</label>
                    <div class="auth-input">
                        <x-icon name="check" />
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                        <button type="button" class="auth-eye" data-auth-eye="password_confirmation" aria-label="Show password" aria-pressed="false"><x-icon name="eye" /></button>
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100"><x-icon name="save" /> Update Password</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                @csrf
                <button type="submit" class="auth-link-btn">Log out instead</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('password');
            var list = document.querySelector('[data-pw-rules]');
            if (!input || !list) return;
            var tests = {
                length: function (v) { return v.length >= 8; },
                upper: function (v) { return /[A-Z]/.test(v); },
                lower: function (v) { return /[a-z]/.test(v); },
                number: function (v) { return /\d/.test(v); }
            };
            function update() {
                var v = input.value;
                list.querySelectorAll('[data-rule]').forEach(function (li) {
                    li.classList.toggle('is-met', tests[li.dataset.rule](v));
                });
            }
            input.addEventListener('input', update);
            update();
        })();
    </script>
@endsection
