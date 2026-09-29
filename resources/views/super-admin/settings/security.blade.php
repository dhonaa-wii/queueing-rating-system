@extends('layouts.super-admin')

@section('title', 'Security Settings')

@section('content')
    <div class="page-shell">
        <h2 class="h4 mb-1">Security Settings</h2>
        <p class="text-brand-muted mb-3" style="font-size: var(--page-fs-sm);">
            General system configuration values (institution info, password/session policy, appearance, etc.).
            No password/session rule is currently specified in the functional spec, so this stores plain
            key/value configuration rather than enforcing invented rules.
        </p>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card-brand p-3">
                    <h3 class="h6 mb-2">Add Setting</h3>

                    <form method="POST" action="{{ route('super-admin.settings.security.store') }}">
                        @csrf

                        <div class="mb-2">
                            <label for="setting_key" class="form-label">Key</label>
                            <input type="text" name="setting_key" id="setting_key" class="form-control form-control-sm @error('setting_key') is-invalid @enderror" value="{{ old('setting_key') }}" required>
                            @error('setting_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label for="value_type" class="form-label">Type</label>
                            <select name="value_type" id="value_type" class="form-select form-select-sm">
                                <option value="string">Text</option>
                                <option value="integer">Number</option>
                                <option value="boolean">True/False</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="setting_value" class="form-label">Value</label>
                            <input type="text" name="setting_value" id="setting_value" class="form-control form-control-sm @error('setting_value') is-invalid @enderror" value="{{ old('setting_value') }}" required>
                            @error('setting_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-brand btn-sm"><x-icon name="plus" /> Add Setting</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <h3 class="h6 mb-2">Current Settings</h3>

                @forelse ($settings as $setting)
                    <div class="card-brand p-2 mb-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong style="font-size: var(--page-fs-sm);">{{ $setting->setting_key }}</strong>
                            <span class="badge badge-muted-tint">{{ $setting->value_type }}</span>
                        </div>
                        <pre class="mb-2 text-brand-muted" style="white-space: pre-wrap; font-size: var(--page-fs-xs);">{{ is_array($setting->setting_value) ? json_encode($setting->setting_value) : $setting->setting_value }}</pre>
                        <form method="POST" action="{{ route('super-admin.settings.security.destroy', $setting) }}" onsubmit="return confirm('Remove this setting?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-brand"><x-icon name="trash" /> Remove</button>
                        </form>
                    </div>
                @empty
                    <div class="card-brand p-3 text-center text-brand-muted">No settings configured yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
