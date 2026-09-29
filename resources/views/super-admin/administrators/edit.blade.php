@extends('layouts.super-admin')

@section('title', 'Edit Admin Account')

@section('content')
    @include('partials.back-link', ['href' => route('super-admin.administrators.index')])
    <h2 class="h4 mt-1 mb-4">Edit {{ $admin->username }}</h2>

    <div class="card-brand p-4 p-md-5" style="max-width: 720px;">
        <form method="POST" action="{{ route('super-admin.administrators.update', $admin) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="{{ $admin->username }}" disabled>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email (optional)</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $admin->email) }}">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="first_name" class="form-label">First Name <span class="text-brand-accent">*</span></label>
                    <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', $admin->profile->first_name ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label for="middle_name" class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" id="middle_name" class="form-control" value="{{ old('middle_name', $admin->profile->middle_name ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label for="last_name" class="form-label">Last Name <span class="text-brand-accent">*</span></label>
                    <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', $admin->profile->last_name ?? '') }}" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="contact_number" class="form-label">Contact Number (optional)</label>
                    <input type="text" name="contact_number" id="contact_number" class="form-control" value="{{ old('contact_number', $admin->profile->contact_number ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label for="employee_reference" class="form-label">Employee Reference (optional)</label>
                    <input type="text" name="employee_reference" id="employee_reference" class="form-control" value="{{ old('employee_reference', $admin->administratorProfile->employee_reference ?? '') }}">
                </div>
            </div>

            <div class="mb-4">
                <label for="college_id" class="form-label">College <span class="text-brand-accent">*</span></label>
                <select name="college_id" id="college_id" class="form-select" required>
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}" @selected(old('college_id', $admin->administratorProfile->college_id ?? null) == $college->id)>{{ $college->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save Changes</button>
        </form>
    </div>
@endsection
