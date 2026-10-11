@extends('layouts.panelist')

@section('title', 'Cannot Join Terminal')

@section('back-link')
    @include('partials.back-link', ['href' => route('panelist.dashboard')])
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card-brand p-4 p-md-5 text-center mt-4">
                <span class="badge badge-danger-tint mb-3">Cannot Join</span>
                <p class="mb-0">{{ $message }}</p>
            </div>
        </div>
    </div>
@endsection
