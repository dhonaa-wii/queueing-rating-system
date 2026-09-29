@extends('layouts.super-admin')

@section('title', 'New Admin Account')

@section('content')
    <div class="page-shell aac-page">
        @include('partials.back-link', ['href' => route('super-admin.administrators.index')])
        <h2 class="h4 mt-1 mb-3">New Admin Account</h2>

        <div class="card-brand p-3 p-md-4 aac-card">
            @if ($colleges->isEmpty())
                <div class="alert alert-warning py-2 px-3 small mb-3">No active Colleges exist yet. Create one in Application Settings first.</div>
            @endif

            <form method="POST" action="{{ route('super-admin.administrators.store') }}">
                @csrf

                <div class="aac-section-label">Account</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required autofocus>
                        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email (optional)</label>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="aac-section-label">Personal Information</div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="middle_name" class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" id="middle_name" class="form-control" value="{{ old('middle_name') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name') }}" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" id="contact_number" class="form-control" value="{{ old('contact_number') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="employee_reference" class="form-label">Employee Reference</label>
                        <input type="text" name="employee_reference" id="employee_reference" class="form-control" value="{{ old('employee_reference') }}">
                    </div>
                </div>

                <div class="aac-section-label">College</div>
                <div class="mb-3">
                    <select name="college_id" id="college_id" class="form-select @error('college_id') is-invalid @enderror" required @if($colleges->isEmpty()) disabled @endif>
                        <option value="">Select&hellip;</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}" @selected(old('college_id') == $college->id)>{{ $college->name }}</option>
                        @endforeach
                    </select>
                    @error('college_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Determines which College this Administrator's categories belong to.</div>
                </div>

                <div class="alert alert-info py-2 px-3 small mb-3">A temporary password will be generated and shown once after creation. The account must change it on first login.</div>

                <button type="submit" class="btn btn-brand" @if($colleges->isEmpty()) disabled @endif><x-icon name="user-plus" /> Create Admin Account</button>
            </form>
        </div>
    </div>

    @push('styles')
        <style>
            .aac-page {
                max-width: 40rem;
            }

            .aac-card {
                border-radius: 0.85rem;
            }

            .aac-section-label {
                font-size: 0.7rem;
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: var(--brand-muted);
                margin-bottom: 0.5rem;
            }

            .aac-section-label:not(:first-child) {
                margin-top: 0.25rem;
            }
        </style>
    @endpush
@endsection
