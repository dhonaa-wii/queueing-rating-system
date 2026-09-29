@extends('layouts.panelist')

@section('title', 'Schedule — ' . $category->name)
@section('heading', $category->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <p class="text-brand-muted mb-0 small">
                Schedule &amp; Queue &middot; {{ $category->academicYear->name ?? 'N/A' }} &middot; {{ $category->semester->name ?? 'N/A' }} &middot; {{ $category->college->name ?? 'N/A' }}
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($category->categoryAnnouncements->isNotEmpty())
                <a href="#announcements" class="btn btn-outline-brand btn-sm">View Announcements ({{ $category->categoryAnnouncements->count() }})</a>
            @endif

            {{-- Replaces the old back link to the category card picker
                 (user-directed 2026-09-20): View Schedule opens straight on a
                 category now, so there is no picker page to go back to. --}}
            @include('partials.category-picker-dropdown', [
                'categories' => $pickerCategories,
                'current' => $category,
                'routeName' => 'panelist.schedule.show',
            ])
        </div>
    </div>

    @include('partials.schedule.body', ['scheduleRouteName' => 'panelist.schedule.show'])
@endsection
