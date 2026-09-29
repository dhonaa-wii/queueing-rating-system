@extends('layouts.panelist')

@section('title', 'Cannot Join Terminal')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card-brand p-4 p-md-5 text-center mt-4">
                <span class="badge badge-danger-tint mb-3">Cannot Join</span>
                <p class="mb-4">{{ $message }}</p>
                @include('partials.back-link', ['href' => route('panelist.dashboard'), 'button' => true, 'fullWidth' => true])
            </div>
        </div>
    </div>
@endsection
