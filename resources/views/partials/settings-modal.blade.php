@php
    $profile = auth()->user()->profile;
@endphp

<div class="modal fade" id="settings-modal" tabindex="-1" aria-labelledby="settings-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settings-modal-label">Profile &amp; Account Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="text-brand-muted small">Appearance</span>
                    @include('partials.theme-toggle-button')
                </div>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="{{ auth()->user()->username }}" disabled>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', $profile->first_name ?? '') }}" required>
                        </div>
                        <div class="col-4">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" class="form-control" value="{{ old('middle_name', $profile->middle_name ?? '') }}">
                        </div>
                        <div class="col-3">
                            <label for="suffix" class="form-label">Suffix</label>
                            <input type="text" name="suffix" id="suffix" class="form-control" value="{{ old('suffix', $profile->suffix ?? '') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', $profile->last_name ?? '') }}" required>
                    </div>

                    <div class="mb-4">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" id="contact_number" class="form-control" value="{{ old('contact_number', $profile->contact_number ?? '') }}">
                    </div>

                    <button type="submit" class="btn btn-brand w-100"><x-icon name="save" /> Save Profile</button>
                </form>

                <hr class="brand-divider my-4">

                <div class="d-grid gap-2">
                    <a href="{{ route('password.change') }}" class="btn btn-outline-brand"><x-icon name="key" /> Change Password</a>

                    @if (auth()->user()->hasRole('SUPER_ADMIN'))
                        <a href="{{ route('super-admin.settings.application.index') }}" class="btn btn-outline-brand"><x-icon name="settings" /> Application Settings</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger-brand w-100"><x-icon name="log-out" /> Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
