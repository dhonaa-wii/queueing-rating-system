@extends('layouts.app')

@section('title', 'Registration Confirmed — ' . $category->name)

@php
    $leader = $researchGroup->leader();
    $members = $researchGroup->students->where('is_leader', false);
    $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';
    $describe = fn ($student) => $student
        ? implode(' · ', array_filter([$student->full_name, $student->sex, $student->section_name, $student->research_track_name]))
        : 'N/A';
@endphp

@section('content')
    <div class="card-brand p-4 mb-4 text-center">
        <span class="badge badge-success-tint mb-2">Registration Recorded</span>
        <h1 class="h4 mb-1">You're registered for {{ $category->name }}</h1>
        <p class="text-brand-muted mb-0">
            Keep your group reference below &mdash; an administrator may ask for it to locate your registration.
        </p>
        <p class="h5 mt-2 mb-0">{{ $researchGroup->group_reference }}</p>
    </div>

    <div class="card-brand p-4 mb-4">
        <dl class="detail-list small">
            <dt>Presentation Category</dt>
            <dd>{{ $category->name }}</dd>

            <dt>Presentation Mode</dt>
            <dd>{{ $category->presentationMode->name }}</dd>

            <dt>Academic Year / Semester</dt>
            <dd>{{ $category->academicYear->name ?? 'N/A' }} &middot; {{ $category->semester->name ?? 'N/A' }}</dd>

            <dt>Group Leader</dt>
            <dd>{{ $describe($leader) }}</dd>

            <dt>Group Members</dt>
            <dd>
                @forelse ($members as $member)
                    {{ $describe($member) }}@if (! $loop->last)<br>@endif
                @empty
                    None &mdash; leader-only group
                @endforelse
            </dd>

            @if ($isTitleProposal)
                <dt>Proposed Titles</dt>
                <dd>
                    @foreach ($researchGroup->proposedTitles as $title)
                        {{ $loop->iteration }}. {{ $title->title_text }}@if (! $loop->last)<br>@endif
                    @endforeach
                </dd>
            @else
                <dt>Project Title</dt>
                <dd>{{ $researchGroup->current_project_title }}</dd>
            @endif

            @if ($researchGroup->technical_adviser_name)
                <dt>Technical Adviser</dt>
                <dd>{{ $researchGroup->technical_adviser_name }}</dd>
            @endif

            <dt>Registered</dt>
            <dd>{{ $researchGroup->registered_at->format('M j, Y g:i A') }}</dd>
        </dl>
    </div>

    <div class="alert alert-info py-2 px-3 small mb-4" style="background-color: var(--brand-info-tint); border-color: var(--brand-info); color: var(--brand-info);">
        This confirmation does not guarantee an immediate presentation schedule. Schedule and queue information
        becomes available once the administrator completes setup and generates the official queue. Registrations
        cannot be edited after submission &mdash; any correction must be requested from an administrator.
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('student.categories.schedule', $category) }}" class="btn btn-outline-brand"><x-icon name="calendar" /> View Schedule</a>
        @include('partials.back-link', ['href' => route('student.categories.index'), 'button' => true])
    </div>
@endsection
