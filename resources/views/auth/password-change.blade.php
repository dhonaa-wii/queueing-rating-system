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
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
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
@endsection
