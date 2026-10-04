@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card-brand p-4 p-md-5 mt-4">
                <h1 class="h3 mb-1">Change Password</h1>
                <p class="text-brand-muted mb-4">Choose a new password for your account.</p>

                @if ($forced)
                    <div class="alert alert-warning">
                        You must change your password before continuing.
                    </div>
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

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required
                               minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" autocomplete="new-password">
                        <ul class="pw-rules" data-pw-rules>
                            <li data-rule="length">8+ characters</li>
                            <li data-rule="upper">Uppercase letter</li>
                            <li data-rule="lower">Lowercase letter</li>
                            <li data-rule="number">Number</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-brand w-100"><x-icon name="save" /> Update Password</button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                    @csrf
                    <button type="submit" class="btn btn-link text-brand-muted">Log out instead</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .pw-rules { list-style: none; padding: 0; margin: 0.5rem 0 0; display: flex; flex-wrap: wrap; gap: 0.35rem; }
        .pw-rules li {
            font-size: 0.75rem; padding: 0.15rem 0.55rem; border-radius: 999px;
            background: var(--brand-surface-alt); color: var(--brand-muted);
            transition: background-color .15s, color .15s;
        }
        .pw-rules li::before { content: '\2022'; margin-right: 0.3rem; }
        .pw-rules li.is-met { background: var(--brand-success-tint); color: var(--brand-success); }
        .pw-rules li.is-met::before { content: '\2713'; }
    </style>

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
