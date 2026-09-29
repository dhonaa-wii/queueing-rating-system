@extends('layouts.app')

@section('title', 'Presentation Categories')

@section('content')
    <div class="page-shell stu-cat-page">
        <div class="stu-cat-heading mb-4">
            <h1 class="h4 mb-1">Presentation Categories</h1>
            <p class="text-brand-muted mb-0">
                Select your presentation category to register your research group or view its schedule.
            </p>
        </div>

        @if ($categories->isEmpty())
            <div class="picker-empty">
                <h2>No categories open right now</h2>
                <p>No presentation categories are open to the public at the moment. Check back later.</p>
            </div>
        @else
            <div class="stu-cat-grid">
                @foreach ($categories as $category)
                    @php
                        $regStatus = $category->registrationWindowStatus();
                        $isOpen = $regStatus === 'Open';

                        $statusBadgeClass = match ($regStatus) {
                            'Open' => 'badge-success-tint',
                            'Scheduled' => 'badge-info-tint',
                            default => 'badge-muted-tint',
                        };

                        $statusDotClass = match ($regStatus) {
                            'Open' => 'bg-brand-success',
                            'Scheduled' => 'bg-brand-info',
                            default => 'bg-brand-muted',
                        };

                        $statusLabel = match ($regStatus) {
                            'Open' => 'Registration Open',
                            'Scheduled' => 'Opens Soon',
                            'Closed' => 'Registration Closed',
                            default => 'Registration Unavailable',
                        };

                        $disabledReason = match ($regStatus) {
                            'Scheduled' => 'Registration opens ' . $category->registration_opens_at->format('M j, Y g:i A') . '.',
                            'Closed' => 'Registration closed ' . $category->registration_closes_at->format('M j, Y g:i A') . '.',
                            default => 'Registration is not currently open.',
                        };

                        $dates = $category->presentationDates;
                        $paymentRequired = (bool) optional($category->categoryPaymentSetting)->payment_required;
                        $announcement = $category->categoryAnnouncements->sortBy(fn ($a) => $a->isPaymentInstructions() ? 0 : 1)->first();
                        $panelId = 'category-details-' . $category->id;
                    @endphp

                    <div class="card-brand stu-cat-card d-flex flex-column overflow-hidden p-0">
                        <div class="category-card-header">
                            <div class="d-flex align-items-center gap-2">
                                <svg class="category-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 10L12 5 2 10l10 5 10-5z"></path>
                                    <path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"></path>
                                    <path d="M22 10v6"></path>
                                </svg>
                                <h2 class="category-card-title mb-0 flex-grow-1">{{ $category->name }}</h2>
                                <span class="badge badge-brand-tint text-nowrap">{{ $category->presentationMode->name }}</span>
                            </div>

                            <p class="category-card-meta mb-0 mt-1">
                                {{ $category->academicYear->name ?? 'No academic year' }} &middot; {{ $category->semester->name ?? 'No semester' }}
                                <br>{{ $category->college->name ?? 'No college' }}
                            </p>
                        </div>

                        <div class="category-card-body d-flex flex-column flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge {{ $statusBadgeClass }} stu-cat-status-badge">
                                    <span class="stu-cat-status-dot {{ $statusDotClass }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </div>

                            @if ($announcement)
                                <div class="stu-cat-announcement mb-2">
                                    <x-icon name="{{ $announcement->isPaymentInstructions() ? 'wallet' : 'megaphone' }}" />
                                    <div>
                                        <strong>{{ $announcement->title }}</strong>
                                        <div class="text-brand-muted">{{ $announcement->message }}</div>
                                    </div>
                                </div>
                            @endif

                            @if ($category->description)
                                <p class="small mb-2 stu-cat-description">{{ $category->description }}</p>
                            @endif

                            <div class="d-flex flex-wrap gap-1 mb-3">
                                @if ($category->subject_or_research_type)
                                    <span class="badge badge-muted-tint">{{ $category->subject_or_research_type }}</span>
                                @endif
                                @if ($category->research_track_required)
                                    <span class="badge badge-info-tint"><x-icon name="list-check" /> Track</span>
                                @endif
                                @if ($category->technical_adviser_required)
                                    <span class="badge badge-info-tint"><x-icon name="shield-check" /> Technical Adviser</span>
                                @endif
                                @if ($paymentRequired)
                                    <span class="badge badge-info-tint"><x-icon name="wallet" /> Payment</span>
                                @endif
                            </div>

                            <div class="stu-cat-facts mb-2">
                                <div class="stu-cat-fact">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <div>
                                        <div class="category-stat-label">Max Members</div>
                                        <div class="category-stat-value">{{ $category->maximum_members }}</div>
                                    </div>
                                </div>
                                <div class="stu-cat-fact">
                                    <x-icon name="calendar" />
                                    <div>
                                        <div class="category-stat-label">Presentation Date{{ $dates->count() > 1 ? 's' : '' }}</div>
                                        <div class="category-stat-value">
                                            @if ($dates->isEmpty())
                                                TBA
                                            @else
                                                {{ $dates->first()->presentation_date->format('M j, Y') }}
                                                @if ($dates->count() > 1)
                                                    +{{ $dates->count() - 1 }}
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="stu-cat-actions mt-auto">
                                @if ($isOpen)
                                    <a href="{{ route('student.categories.registration.create', $category) }}" class="btn btn-brand btn-sm flex-grow-1"><x-icon name="user-plus" /> Register</a>
                                @else
                                    <button type="button" class="btn btn-brand btn-sm flex-grow-1" disabled title="{{ $disabledReason }}"><x-icon name="user-plus" /> Register</button>
                                @endif

                                <a href="{{ route('student.categories.schedule', $category) }}" class="btn btn-outline-brand btn-sm flex-grow-1"><x-icon name="calendar" /> Schedule</a>
                            </div>

                            @unless ($isOpen)
                                <p class="text-brand-muted stu-cat-reason mb-0 mt-2">{{ $disabledReason }}</p>
                            @endunless

                            <button type="button"
                                    class="btn btn-link btn-sm p-0 text-decoration-none details-toggle d-inline-flex align-items-center gap-1 mt-2"
                                    data-category-toggle="{{ $panelId }}" aria-expanded="false" aria-controls="{{ $panelId }}">
                                <span class="toggle-label">More Details</span>
                                <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
                            </button>

                            <div class="category-details" id="{{ $panelId }}">
                                <hr class="brand-divider my-2">

                                <dl class="detail-list small mb-0">
                                    <dt>Registration Opens</dt>
                                    <dd>{{ $category->registration_opens_at->format('M j, Y g:i A') }}</dd>

                                    <dt>Registration Closes</dt>
                                    <dd>{{ $category->registration_closes_at->format('M j, Y g:i A') }}</dd>

                                    <dt>Presentation Date{{ $dates->count() > 1 ? 's' : '' }}</dt>
                                    <dd>
                                        @if ($dates->isEmpty())
                                            To be announced
                                        @else
                                            @foreach ($dates as $date)
                                                <div>
                                                    {{ $date->presentation_date->format('l, M j, Y') }}
                                                    @if ($date->event_start_time && $date->event_end_time)
                                                        &middot; {{ \Illuminate\Support\Carbon::parse($date->event_start_time)->format('g:i A') }}&ndash;{{ \Illuminate\Support\Carbon::parse($date->event_end_time)->format('g:i A') }}
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </dd>

                                    @if ($paymentRequired)
                                        <dt>Payment</dt>
                                        <dd>
                                            @if ($category->categoryPaymentTypes->isNotEmpty())
                                                {{ $category->categoryPaymentTypes->pluck('name')->implode(', ') }}
                                            @endif
                                        </dd>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .stu-cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 21.5rem), 1fr));
            align-items: start;
            gap: 1rem;
        }

        .stu-cat-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .stu-cat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--brand-shadow-lifted);
        }

        .stu-cat-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .stu-cat-status-dot {
            width: 0.4rem;
            height: 0.4rem;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stu-cat-announcement {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.55rem 0.7rem;
            border-radius: 0.6rem;
            background-color: var(--brand-info-tint);
            color: var(--brand-info);
            font-size: 0.78rem;
        }

        .stu-cat-announcement svg {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .stu-cat-announcement strong {
            display: block;
            color: var(--brand-text);
            font-size: 0.82rem;
        }

        .stu-cat-description {
            color: var(--brand-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .badge-info-tint svg {
            width: 0.7rem;
            height: 0.7rem;
            margin-right: 0.15rem;
            vertical-align: -1px;
        }

        .stu-cat-facts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
        }

        .stu-cat-fact {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .stu-cat-fact > svg {
            width: 1.1rem;
            height: 1.1rem;
            color: var(--brand-accent);
            flex-shrink: 0;
        }

        .stu-cat-actions {
            display: flex;
            gap: 0.5rem;
        }

        .stu-cat-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }

        .stu-cat-reason {
            font-size: 0.74rem;
        }

        .category-details {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.25s ease;
        }

        .category-details.is-open {
            opacity: 1;
        }

        .details-toggle {
            color: var(--brand-accent);
            font-size: 0.78rem;
        }

        .details-toggle .chevron {
            transition: transform 0.2s ease;
        }

        .details-toggle .chevron.rotate-180 {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-category-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = document.getElementById(btn.dataset.categoryToggle);
                var expanding = !panel.classList.contains('is-open');

                panel.classList.toggle('is-open', expanding);
                panel.style.maxHeight = expanding ? panel.scrollHeight + 'px' : null;

                btn.setAttribute('aria-expanded', expanding ? 'true' : 'false');
                btn.querySelector('.chevron').classList.toggle('rotate-180', expanding);
                btn.querySelector('.toggle-label').textContent = expanding ? 'Hide Details' : 'More Details';
            });
        });
    </script>
@endpush
