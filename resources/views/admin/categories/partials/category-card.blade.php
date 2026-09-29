{{--
    One category card + its Delete confirm modal. Used by the index page's
    Active and Archived tabs alike — $category is the only expected variable.
--}}
@php
    $setupComplete = $category->setupCompletionStatus() === 'Complete';
    $dates = $category->presentationDates;

    $registrationStatus = $category->registrationWindowStatus();
    $registrationColor = match ($registrationStatus) {
        'Open' => 'success',
        'Closed' => 'accent',
        'Scheduled' => 'info',
        default => 'muted',
    };
    $registrationBadge = match ($registrationStatus) {
        'Open' => 'check',
        'Closed' => 'lock',
        'Scheduled' => 'clock',
        default => 'dash',
    };

    $setupStatus = $category->setupCompletionStatus();
    $setupColor = $setupComplete ? 'success' : 'accent';
    $setupBadge = $setupComplete ? 'check' : 'exclamation';

    $queueStatus = $category->queueGenerationStatus();
    $queueGenerated = $queueStatus === 'Generated';
    $queueColor = $queueGenerated ? 'success' : 'muted';
    $queueBadge = $queueGenerated ? 'check' : 'dash';

    $eventStatusText = $category->eventStatus();
    $eventColor = match ($eventStatusText) {
        'Active' => 'info',
        'Completed' => 'success',
        'Cancelled' => 'danger',
        'Upcoming' => 'accent',
        default => 'muted',
    };
    $eventBadge = match ($eventStatusText) {
        'Active' => 'check',
        'Completed' => 'check',
        'Cancelled' => 'x',
        'Upcoming' => 'clock',
        default => 'dash',
    };
@endphp
@php
    $overlayIcons = [
        'check' => '<polyline points="20 6 9 17 4 12"></polyline>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'exclamation' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>',
        'dash' => '<line x1="5" y1="12" x2="19" y2="12"></line>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
    ];
@endphp
<div>
    <div class="card-brand h-100 d-flex flex-column overflow-hidden p-0">
        <div class="category-card-header">
            <div class="d-flex align-items-center gap-2">
                <svg class="category-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10L12 5 2 10l10 5 10-5z"></path>
                    <path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"></path>
                    <path d="M22 10v6"></path>
                </svg>
                <h3 class="category-card-title mb-0">{{ $category->name }}</h3>
                <span class="badge rounded-pill badge-brand-tint ms-auto">{{ $category->presentationMode->name }}</span>
            </div>

            <p class="category-card-meta mb-0 mt-1">
                {{ $category->academicYear->name ?? 'No academic year' }} &middot; {{ $category->semester->name ?? 'No semester' }}
                <br>{{ $category->college->name ?? 'No college' }}
            </p>
        </div>

        <div class="category-card-body d-flex flex-column flex-grow-1">
            <div class="row row-cols-2 g-2 mb-2">
                <div class="col">
                    <div class="category-stat-item">
                        <span class="category-stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-2"></path><rect x="9" y="1" width="6" height="4" rx="1" ry="1"></rect></svg>
                            <span class="category-stat-badge bg-brand-{{ $registrationColor }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">{!! $overlayIcons[$registrationBadge] !!}</svg>
                            </span>
                        </span>
                        <div>
                            <div class="category-stat-label">Registration:</div>
                            <div class="category-stat-value text-brand-{{ $registrationColor }}">{{ $registrationStatus }}</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="category-stat-item">
                        <span class="category-stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            <span class="category-stat-badge bg-brand-{{ $setupColor }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">{!! $overlayIcons[$setupBadge] !!}</svg>
                            </span>
                        </span>
                        <div>
                            <div class="category-stat-label">Setup:</div>
                            <div class="category-stat-value text-brand-{{ $setupColor }}">{{ $setupStatus }}</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="category-stat-item">
                        <span class="category-stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                            <span class="category-stat-badge bg-brand-{{ $queueColor }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">{!! $overlayIcons[$queueBadge] !!}</svg>
                            </span>
                        </span>
                        <div>
                            <div class="category-stat-label">Queue:</div>
                            <div class="category-stat-value text-brand-{{ $queueColor }}">{{ $queueStatus }}</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="category-stat-item">
                        <span class="category-stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <span class="category-stat-badge bg-brand-{{ $eventColor }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">{!! $overlayIcons[$eventBadge] !!}</svg>
                            </span>
                        </span>
                        <div>
                            <div class="category-stat-label">Event:</div>
                            <div class="category-stat-value text-brand-{{ $eventColor }}">{{ $eventStatusText }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="category-card-dates d-flex align-items-center gap-2 text-brand-muted mb-2">
                <svg width="14" height="14" class="flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                @if ($dates->isEmpty())
                    No presentation date set
                @else
                    {{ $dates->pluck('presentation_date')->map(fn ($d) => $d->format('M j, Y'))->join(', ') }}
                @endif
            </div>

            <div class="category-card-actions mt-auto">
                @if ($category->isEnded())
                    <a href="{{ route('admin.categories.show', $category) }}" class="btn-card-action btn-card-solid">
                        <x-icon name="eye" />
                        View Setup
                    </a>
                @else
                    <a href="{{ route('admin.categories.show', $category) }}" class="btn-card-action btn-card-solid">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        {{ $setupComplete ? 'Edit Setup' : 'Open Setup' }}
                    </a>

                    <a href="{{ route('admin.categories.show', $category) }}#tab-announcements" class="btn-card-action btn-card-outline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        Announce
                    </a>
                @endif

                @if ($category->categoryStatus->code !== 'ARCHIVED')
                    <button type="button" class="btn-card-action btn-card-ghost"
                            data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                            data-confirm-action="{{ route('admin.categories.archive', $category) }}"
                            data-confirm-method="POST"
                            data-confirm-title="Archive Category"
                            data-confirm-message="Archive {{ $category->name }}? It can be unarchived again later."
                            data-confirm-submit-label="Archive"
                            data-confirm-variant="btn-outline-danger-brand">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                        Archive
                    </button>
                @else
                    <button type="button" class="btn-card-action btn-card-outline"
                            data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                            data-confirm-action="{{ route('admin.categories.unarchive', $category) }}"
                            data-confirm-method="POST"
                            data-confirm-title="Unarchive Category"
                            data-confirm-message="Restore {{ $category->name }} to the active list?"
                            data-confirm-submit-label="Unarchive"
                            data-confirm-variant="btn-brand">
                        <x-icon name="refresh" />
                        Unarchive
                    </button>
                @endif

                <button type="button" class="btn-card-action btn-card-danger"
                        data-bs-toggle="modal" data-bs-target="#delete-category-{{ $category->id }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="delete-category-{{ $category->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Are you sure you want to permanently delete <strong>{{ $category->name }}</strong>?
                </p>
                <p class="mb-2">
                    Current status:
                    <span class="badge {{ $category->categoryStatus->code === 'ARCHIVED' ? 'badge-muted-tint' : 'badge-brand-tint' }}">{{ $category->statusDisplayName() }}</span>
                </p>
                @if (in_array($category->categoryStatus->code, ['COMPLETED', 'ARCHIVED'], true))
                    <p class="text-danger-brand small mb-0">
                        This also deletes every registered group, its queue and schedule history,
                        submitted evaluations, and grades. They will no longer appear in Reports.
                        This cannot be undone.
                    </p>
                @else
                    <p class="text-brand-muted small mb-0">
                        This removes its schedules, rooms, queue/payment/evaluation configuration, and
                        announcements, and cannot be undone. If research groups are already registered
                        under this category, deletion will be blocked &mdash; archive it instead.
                    </p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>
