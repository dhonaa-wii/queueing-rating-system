@extends('layouts.app')

@section('title', 'Registration Confirmed — ' . $category->name)

@php
    $leader = $researchGroup->leader();
    $members = $researchGroup->students->where('is_leader', false);
    $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';
    $details = fn ($student) => $student
        ? array_values(array_filter([$student->sex, $student->section_name, $student->research_track_name]))
        : [];
@endphp

@push('styles')
    <style>
        .rc-page {
            --rc-fs: clamp(0.84rem, 0.8rem + 0.2vw, 0.95rem);
            --rc-fs-sm: clamp(0.76rem, 0.73rem + 0.15vw, 0.84rem);
            --rc-fs-xs: clamp(0.66rem, 0.64rem + 0.1vw, 0.72rem);
            --rc-fs-title: clamp(1.15rem, 1rem + 0.8vw, 1.6rem);
            --rc-fs-ref: clamp(1.45rem, 1.1rem + 2.2vw, 2.4rem);
            max-width: 46rem;
            margin-inline: auto;
            font-size: var(--rc-fs);
        }

        .rc-hero {
            text-align: center;
            padding: clamp(1.25rem, 1rem + 2vw, 2.25rem) clamp(1rem, 0.75rem + 2vw, 2.25rem);
        }

        .rc-check {
            width: clamp(2.6rem, 2.2rem + 1.5vw, 3.25rem);
            height: clamp(2.6rem, 2.2rem + 1.5vw, 3.25rem);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--brand-success-tint, color-mix(in srgb, var(--brand-success) 15%, transparent));
            color: var(--brand-success);
            margin-bottom: 0.85rem;
        }

        .rc-check svg { width: 55%; height: 55%; }

        .rc-title {
            font-size: var(--rc-fs-title);
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 0.35rem;
            overflow-wrap: anywhere;
        }

        .rc-sub {
            color: var(--brand-muted);
            font-size: var(--rc-fs-sm);
            margin-bottom: 0;
        }

        .rc-ref {
            margin: clamp(1rem, 0.8rem + 1vw, 1.5rem) auto 0;
            max-width: 26rem;
            border: 1px dashed color-mix(in srgb, var(--brand-accent) 55%, var(--brand-border));
            border-radius: 0.9rem;
            background-color: var(--brand-accent-tint);
            padding: 0.85rem 1rem;
        }

        .rc-ref-label {
            font-size: var(--rc-fs-xs);
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand-accent);
        }

        .rc-ref-row {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem 0.75rem;
            margin-top: 0.3rem;
        }

        .rc-ref-code {
            font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;
            font-size: var(--rc-fs-ref);
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--brand-text);
            overflow-wrap: anywhere;
            line-height: 1.15;
        }

        .rc-ref-note {
            font-size: var(--rc-fs-xs);
            color: var(--brand-muted);
            margin: 0.45rem 0 0;
        }

        .rc-card { padding: clamp(1rem, 0.8rem + 1.2vw, 1.6rem); }

        .rc-section-label {
            font-size: var(--rc-fs-xs);
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand-muted);
            margin-bottom: 0.6rem;
        }

        .rc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr));
            gap: 0.75rem 1.25rem;
        }

        .rc-field-label {
            font-size: var(--rc-fs-xs);
            color: var(--brand-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }

        .rc-field-value {
            font-size: var(--rc-fs);
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .rc-people {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .rc-person {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.25rem 0.75rem;
            padding: 0.55rem 0.75rem;
            border-radius: 0.6rem;
            background-color: var(--brand-surface-alt);
        }

        .rc-person-name {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .rc-person-meta {
            font-size: var(--rc-fs-sm);
            color: var(--brand-muted);
        }

        .rc-titles {
            margin: 0;
            padding-left: 1.2rem;
        }

        .rc-titles li + li { margin-top: 0.3rem; }

        .rc-divider { margin: 1.1rem 0; }

        .rc-notice {
            font-size: var(--rc-fs-sm);
            background-color: var(--brand-info-tint);
            border: 1px solid color-mix(in srgb, var(--brand-info) 35%, transparent);
            color: var(--brand-info);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
        }

        .rc-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        @media (max-width: 575.98px) {
            .rc-actions > * { flex: 1 1 100%; justify-content: center; }
        }
    </style>
@endpush

@section('content')
    <div class="rc-page">
        <div class="card-brand rc-hero mb-3">
            <span class="rc-check"><x-icon name="check" /></span>
            <h1 class="rc-title">You're registered</h1>
            <p class="rc-sub">{{ $category->name }} &middot; {{ $category->presentationMode->name }}</p>

            <div class="rc-ref">
                <div class="rc-ref-label">Group Reference</div>
                <div class="rc-ref-row">
                    <span class="rc-ref-code" id="rc-ref-code">{{ $researchGroup->group_reference }}</span>
                    <button type="button" class="btn btn-outline-brand btn-sm" data-copy-ref><x-icon name="calendar-copy" /> <span data-copy-label>Copy</span></button>
                </div>
                <p class="rc-ref-note">Keep this reference &mdash; an administrator may ask for it to locate your registration.</p>
            </div>
        </div>

        <div class="card-brand rc-card mb-3">
            <div class="rc-section-label">Details</div>
            <div class="rc-grid">
                <div>
                    <div class="rc-field-label">Presentation</div>
                    <div class="rc-field-value">{{ $category->name }}</div>
                </div>
                <div>
                    <div class="rc-field-label">Academic Year / Semester</div>
                    <div class="rc-field-value">{{ $category->academicYear->name ?? 'N/A' }} &middot; {{ $category->semester->name ?? 'N/A' }}</div>
                </div>
                <div>
                    <div class="rc-field-label">Registered</div>
                    <div class="rc-field-value">{{ $researchGroup->registered_at->format('M j, Y · g:i A') }}</div>
                </div>
                @if ($researchGroup->technical_adviser_name)
                    <div>
                        <div class="rc-field-label">Technical Adviser</div>
                        <div class="rc-field-value">{{ $researchGroup->technical_adviser_name }}</div>
                    </div>
                @endif
            </div>

            <hr class="brand-divider rc-divider">

            <div class="rc-section-label">{{ $isTitleProposal ? 'Proposed Titles' : 'Project Title' }}</div>
            @if ($isTitleProposal)
                <ol class="rc-titles">
                    @foreach ($researchGroup->proposedTitles as $title)
                        <li class="rc-field-value">{{ $title->title_text }}</li>
                    @endforeach
                </ol>
            @else
                <div class="rc-field-value">{{ $researchGroup->current_project_title }}</div>
            @endif

            <hr class="brand-divider rc-divider">

            <div class="rc-section-label">Group</div>
            <ul class="rc-people">
                @if ($leader)
                    <li class="rc-person">
                        <span class="rc-person-name">{{ $leader->full_name }} <span class="badge badge-brand-tint ms-1">Leader</span></span>
                        <span class="rc-person-meta">{{ implode(' · ', $details($leader)) }}</span>
                    </li>
                @endif
                @foreach ($members as $member)
                    <li class="rc-person">
                        <span class="rc-person-name">{{ $member->full_name }}</span>
                        <span class="rc-person-meta">{{ implode(' · ', $details($member)) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rc-notice mb-3">
            This confirmation does not guarantee an immediate presentation schedule. Schedule and queue information
            becomes available once the administrator completes setup and generates the official queue. Registrations
            cannot be edited after submission &mdash; any correction must be requested from an administrator.
        </div>

        <div class="rc-actions">
            <a href="{{ route('student.categories.schedule', $category) }}" class="btn btn-brand"><x-icon name="calendar" /> View Schedule</a>
            @include('partials.back-link', ['href' => route('student.categories.index'), 'button' => true])
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var button = document.querySelector('[data-copy-ref]');
            var code = document.getElementById('rc-ref-code');
            if (!button || !code) {
                return;
            }

            button.addEventListener('click', function () {
                var text = code.textContent.trim();
                var done = function () {
                    var label = button.querySelector('[data-copy-label]');
                    label.textContent = 'Copied';
                    setTimeout(function () { label.textContent = 'Copy'; }, 1800);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                    return;
                }

                // Plain-HTTP fallback (clipboard API needs a secure context).
                var area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(area);
            });
        })();
    </script>
@endpush
