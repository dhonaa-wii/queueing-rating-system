@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card-brand p-4 p-md-5 mt-4">
                <h1 class="h3 mb-1">Welcome back</h1>
                <p class="text-brand-muted mb-4">Sign in to your ARPQRS account.</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-brand w-100"><x-icon name="log-in" /> Log in</button>
                </form>
            </div>

            <p class="text-center mt-3">
                @include('partials.back-link', ['href' => route('landing')])
            </p>
        </div>
    </div>
@endsection
