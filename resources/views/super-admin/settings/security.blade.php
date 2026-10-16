@extends('layouts.super-admin')

@section('title', 'Security Settings')

@push('styles')
<style>
    .sec-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); align-items: start; }
    .sec-card-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
    .sec-card-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2rem; height: 2rem; border-radius: 0.6rem; flex-shrink: 0;
        background: var(--brand-accent-tint); color: var(--brand-accent);
    }
    .sec-card-icon svg { width: 1rem; height: 1rem; }
    .sec-card-head h3 { margin: 0; font-size: var(--page-fs-heading); font-weight: 600; }
    .sec-card-head .badge { margin-left: auto; }
    .sec-checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.35rem 0.75rem; }
</style>
@endpush

@section('content')
    <div class="page-shell">
        <h2 class="h4 mb-3">Security Settings</h2>

        <div class="sec-grid">
            {{-- Login lockout --}}
            <div class="card-brand p-3">
                <div class="sec-card-head">
                    <span class="sec-card-icon"><x-icon name="lock" /></span>
                    <h3>Login Lockout</h3>
                    <span class="badge {{ $lockout ? 'badge-success-tint' : 'badge-muted-tint' }}">{{ $lockout ? 'On' : 'Off' }}</span>
                </div>

                <form method="POST" action="{{ route('super-admin.settings.security.update', 'lockout') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-2">
                        <label for="max_attempts" class="form-label">Failed attempts before lock</label>
                        <input type="number" min="1" max="100" name="max_attempts" id="max_attempts" placeholder="Off"
                               class="form-control form-control-sm @error('max_attempts') is-invalid @enderror"
                               value="{{ old('max_attempts', $lockout['max_attempts'] ?? '') }}">
                        @error('max_attempts') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="lockout_minutes" class="form-label">Lock duration (minutes)</label>
                        <input type="number" min="1" max="1440" name="lockout_minutes" id="lockout_minutes" placeholder="Off"
                               class="form-control form-control-sm @error('lockout_minutes') is-invalid @enderror"
                               value="{{ old('lockout_minutes', $lockout['minutes'] ?? '') }}">
                        @error('lockout_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-brand btn-sm"><x-icon name="save" /> Save</button>
                </form>
            </div>

            {{-- Session timeout --}}
            <div class="card-brand p-3">
                <div class="sec-card-head">
                    <span class="sec-card-icon"><x-icon name="clock" /></span>
                    <h3>Session Timeout</h3>
                    <span class="badge {{ $sessionTimeout ? 'badge-success-tint' : 'badge-muted-tint' }}">{{ $sessionTimeout ? 'Custom' : 'Default' }}</span>
                </div>

                <form method="POST" action="{{ route('super-admin.settings.security.update', 'session') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="session_timeout" class="form-label">Sign out after inactivity (minutes)</label>
                        <input type="number" min="1" max="1440" name="session_timeout" id="session_timeout"
                               placeholder="Default ({{ $defaultSessionLifetime }})"
                               class="form-control form-control-sm @error('session_timeout') is-invalid @enderror"
                               value="{{ old('session_timeout', $sessionTimeout ?? '') }}">
                        @error('session_timeout') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-brand btn-sm"><x-icon name="save" /> Save</button>
                </form>
            </div>

            {{-- Password rules + age --}}
            <div class="card-brand p-3">
                <div class="sec-card-head">
                    <span class="sec-card-icon"><x-icon name="key" /></span>
                    <h3>Password Rules</h3>
                    <span class="badge {{ $customPasswordRules || $passwordMaxAge ? 'badge-success-tint' : 'badge-muted-tint' }}">{{ $customPasswordRules || $passwordMaxAge ? 'Custom' : 'Default' }}</span>
                </div>

                <form method="POST" action="{{ route('super-admin.settings.security.update', 'password') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-2">
                        <label for="min_length" class="form-label">Minimum length</label>
                        <input type="number" min="4" max="64" name="min_length" id="min_length" required
                               class="form-control form-control-sm @error('min_length') is-invalid @enderror"
                               value="{{ old('min_length', $passwordRules['min_length']) }}">
                        @error('min_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Must contain</label>
                        <div class="sec-checks">
                            @foreach (['uppercase' => 'Uppercase letter', 'lowercase' => 'Lowercase letter', 'number' => 'Number', 'symbol' => 'Symbol'] as $key => $label)
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="{{ $key }}" value="1" id="rule_{{ $key }}"
                                           @checked(old('_token') ? old($key) : $passwordRules[$key])>
                                    <label class="form-check-label" for="rule_{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="max_age_days" class="form-label">Password expires after (days)</label>
                        <input type="number" min="1" max="3650" name="max_age_days" id="max_age_days" placeholder="Never"
                               class="form-control form-control-sm @error('max_age_days') is-invalid @enderror"
                               value="{{ old('max_age_days', $passwordMaxAge ?? '') }}">
                        @error('max_age_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-brand btn-sm"><x-icon name="save" /> Save</button>
                </form>
            </div>
        </div>
    </div>
@endsection
