@extends('layouts.app')

@section('title', 'Schedule — ' . $category->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <div class="mb-1">@include('partials.back-link', ['href' => route('student.categories.index')])</div>
            <h1 class="h4 mb-1">{{ $category->name }}</h1>
            <p class="text-brand-muted mb-0 small">
                Schedule &amp; Queue &middot; {{ $category->academicYear->name ?? 'N/A' }} &middot; {{ $category->semester->name ?? 'N/A' }} &middot; {{ $category->college->name ?? 'N/A' }}
            </p>
        </div>
        @if ($category->categoryAnnouncements->isNotEmpty())
            <a href="#announcements" class="btn btn-outline-brand btn-sm">View Announcements ({{ $category->categoryAnnouncements->count() }})</a>
        @endif
    </div>

    @include('partials.schedule.body', ['scheduleRouteName' => 'student.categories.schedule'])
@endsection
