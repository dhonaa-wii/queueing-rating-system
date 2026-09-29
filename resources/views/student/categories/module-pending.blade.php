@extends('layouts.app')

@section('title', $heading)

@section('content')
    <div class="card-brand p-4 text-center mt-4">
        <h1 class="h4 mb-2">{{ $heading }}</h1>
        <p class="text-brand-muted mb-3">{{ $message }}</p>
        @include('partials.back-link', ['href' => route('student.categories.index'), 'button' => true])
    </div>
@endsection
