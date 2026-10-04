{{-- Full detail card for exactly one "featured" date — room tabs, live queue
     preview, terminals, room session accounts. Rendered once per page load
     (for whichever date admin.live-monitoring.show resolved as featured),
     never duplicated, so the per-date modal ids below stay unique. --}}
@php
    $code = $date->eventDateStatus?->code;
    $overdue = $date->isOverdue();
    // User-directed 2026-09-12: a day that can no longer run — finished,
    // cancelled, or simply passed unstarted — has no rooms worth showing
    // here; Event Control is for rooms you can still act on.
    $showRooms = $date->isOpenForScheduling();
    $defaultRoomId = $panels->first()?->room->id;
@endphp
<div class="card-brand p-4 mb-3">
    <div class="event-day-head">
        <div>
            <h3 class="event-day-title">{{ $date->presentation_date->format('l, M j, Y') }}</h3>
            <div class="event-day-time">{{ $date->event_start_time }}&ndash;{{ $date->event_end_time }}</div>
        </div>
        @include('admin.live-monitoring.partials.event-date-status-badge', ['status' => $date->eventDateStatus, 'overdue' => $overdue])
    </div>

    @if ($overdue || in_array($code, ['PLANNED', 'STANDBY', 'COMPLETED'], true))
        <p class="text-brand-muted event-day-note">
            @if ($overdue)
                This date's window has already passed without being started &mdash;
                <a href="{{ route('admin.categories.show', $category) }}#tab-schedules">update the date</a> before starting.
            @elseif ($code === 'PLANNED')
                Start becomes available once the scheduled time above arrives.
            @elseif ($code === 'STANDBY')
                Ready to start.
            @elseif ($code === 'COMPLETED')
                Event has ended.
            @endif
        </p>
    @endif

    @if (! $showRooms)
        {{-- Nothing to control on a day that has already finished, been
             cancelled, or passed without ever starting. --}}
    @elseif ($panels->isEmpty())
        <p class="text-brand-muted small mb-0">No rooms configured for this date yet.</p>
    @else
        <ul class="nav nav-tabs mb-3 flex-nowrap overflow-x-auto overflow-y-hidden" role="tablist">
            @foreach ($panels as $panel)
                <li class="nav-item text-nowrap" role="presentation">
                    <button type="button" class="nav-link {{ $panel->room->id === $defaultRoomId ? 'active' : '' }}"
                            data-room-tab="{{ $panel->room->id }}" role="tab">
                        {{ $panel->room->room_name }}
                    </button>
                </li>
            @endforeach
        </ul>

        @foreach ($panels as $panel)
            @php
                $session = $panel->session;
                $sessionCode = $session?->roomSessionStatus?->code;
                $canPause = $session && ! in_array($sessionCode, ['PAUSED', 'BREAK', 'CLOSED', 'FINISHED'], true) && $code === 'ACTIVE';
                $canResume = $session && $sessionCode === 'PAUSED';
                // Each room starts and ends on its own (user-directed
                // 2026-09-12) — the first room started activates the day,
                // the last one closed ends it. Re-checked in
                // EventActivationService::startRoom()/endRoom().
                $roomClosed = $session && ($session->ended_at || $sessionCode === 'CLOSED');
                $canStartRoom = ! $session && in_array($code, ['STANDBY', 'ACTIVE'], true) && ! $overdue && $panel->room->panelist_count;
                $presentingNow = $session?->currentAttempt && in_array($session->currentAttempt->presentationStatus?->code, ['ONGOING', 'PAUSED'], true);
            @endphp
            <div class="{{ $panel->room->id === $defaultRoomId ? '' : 'd-none' }}" data-room-panel="{{ $panel->room->id }}">
                <div class="row g-3">
                    <div class="col-xl-5">
                        {{-- Room Status + Room Session Account merged into one
                             card (user-directed 2026-09-13). Buttons reuse the
                             tablet's Presentation Control .control-btn style. --}}
                        <div class="card-brand p-3">
                            <h4 class="h6 mb-3">Room Status</h4>

                            <div class="room-status-stats mb-3">
                                <div class="capacity-stat-item">
                                    <span class="capacity-stat-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4h3a2 2 0 0 1 2 2v14"></path><path d="M2 20h3"></path><path d="M13 20h9"></path><path d="M10 12v.01"></path><path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561Z"></path></svg>
                                    </span>
                                    <div class="room-status-text">
                                        <div class="capacity-stat-label">Room</div>
                                        <div class="room-status-value text-truncate" title="{{ $panel->room->room_name }}">{{ $panel->room->room_name }}</div>
                                    </div>
                                </div>
                                <div class="capacity-stat-item">
                                    <span class="capacity-stat-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    </span>
                                    <div class="room-status-text">
                                        <div class="capacity-stat-label">Panelists</div>
                                        <div class="room-status-value">{{ $panel->room->panelist_count ?? 'Not set' }}</div>
                                    </div>
                                </div>
                                <div class="capacity-stat-item">
                                    <span class="capacity-stat-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                                    </span>
                                    <div class="room-status-text">
                                        <div class="capacity-stat-label">Session</div>
                                        <div>@include('admin.live-monitoring.partials.room-session-status-badge', ['status' => $session?->roomSessionStatus])</div>
                                    </div>
                                </div>
                            </div>

                            <div class="control-btn-grid">
                                @if ($roomClosed)
                                    <span class="badge badge-muted-tint">Closed for the day</span>
                                @elseif (! $session)
                                    <form method="POST" action="{{ route('admin.live-monitoring.rooms.start', [$category, $panel->room]) }}" class="d-flex flex-fill">
                                        @csrf
                                        <button type="submit" class="btn btn-brand control-btn control-btn-primary" @disabled(! $canStartRoom)
                                                title="{{ $canStartRoom ? '' : (! $panel->room->panelist_count ? 'Set this room\'s panelist count first' : 'Available once the scheduled time arrives') }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                            Start Room
                                        </button>
                                    </form>
                                @else
                                    @if ($canResume)
                                        <form method="POST" action="{{ route('admin.live-monitoring.room-sessions.resume', [$category, $session]) }}" class="d-flex flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-success-brand control-btn control-btn-primary">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                                Resume
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-outline-brand control-btn control-btn-primary"
                                                data-bs-toggle="modal" data-bs-target="#pause-modal-{{ $panel->room->id }}"
                                                @disabled(! $canPause)>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
                                            Pause
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-outline-danger-brand control-btn control-btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#end-room-modal-{{ $panel->room->id }}"
                                            @disabled($presentingNow)
                                            title="{{ $presentingNow ? 'A presentation is in progress in this room' : '' }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="14" height="14" rx="2"></rect></svg>
                                        End Room
                                    </button>
                                @endif
                            </div>

                            @if ($panel->account)
                                <div class="room-status-account mt-3">
                                    <div class="capacity-stat-label mb-2">Room Session Account</div>
                                    <dl class="detail-list small mb-2">
                                        <dt>Username</dt>
                                        <dd>{{ $panel->account->username }}</dd>

                                        <dt>Password</dt>
                                        <dd><code>{{ $panel->account->password }}</code></dd>
                                    </dl>
                                    <form method="POST" action="{{ route('admin.live-monitoring.room-accounts.reset', [$category, $panel->account]) }}"
                                          onsubmit="return confirm('Issue a new password for this room account? The old one will stop working immediately.');"
                                          class="control-btn-grid">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-brand control-btn control-btn-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"></path><path d="M21 3v6h-6"></path></svg>
                                            Reset Password
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        <div class="card-brand p-3 mt-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <h4 class="h6 mb-0">Terminals</h4>
                                <button type="button" class="info-icon-btn" data-bs-toggle="modal" data-bs-target="#terminals-guide-modal"
                                        aria-label="How terminals work" title="How terminals work">
                                    <x-icon name="info" />
                                </button>
                            </div>
                            @if (! $session)
                                <p class="text-brand-muted small mb-0">Not generated yet.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 small">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Type</th>
                                                <th>Device</th>
                                                <th>Status</th>
                                                <th>Panelist</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($session->roomTerminals->sortBy('terminal_number') as $terminal)
                                                @php $activeConnection = $terminal->terminalConnections->firstWhere('disconnected_at', null); @endphp
                                                <tr>
                                                    <td>{{ $terminal->terminal_number }}</td>
                                                    <td>{{ $terminal->terminalType->name ?? '—' }}</td>
                                                    <td>
                                                        @if ($terminal->device_identifier)
                                                            <span class="badge badge-brand-tint">Claimed</span>
                                                        @else
                                                            <span class="badge badge-muted-tint">Unclaimed</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($activeConnection)
                                                            <span class="badge badge-success-tint">Connected</span>
                                                        @else
                                                            <span class="badge badge-muted-tint">Empty</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($activeConnection)
                                                            {{ trim(($activeConnection->panelist->profile->first_name ?? '') . ' ' . ($activeConnection->panelist->profile->last_name ?? '')) ?: $activeConnection->panelist->username }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        @if ($activeConnection)
                                                            <button type="button" class="btn btn-sm btn-outline-danger-brand"
                                                                    data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                                    data-confirm-action="{{ route('admin.live-monitoring.terminals.disconnect', [$category, $terminal]) }}"
                                                                    data-confirm-title="Disconnect Terminal {{ $terminal->terminal_number }}"
                                                                    data-confirm-message="Disconnect this terminal seat?"
                                                                    data-confirm-submit-label="Disconnect"
                                                                    data-confirm-variant="btn-outline-danger-brand">
                                                                <x-icon name="unlink" /> Disconnect
                                                            </button>
                                                        @endif
                                                        @if ($terminal->device_identifier)
                                                            <button type="button" class="btn btn-sm btn-outline-brand"
                                                                    data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                                    data-confirm-action="{{ route('admin.live-monitoring.terminals.release-device', [$category, $terminal]) }}"
                                                                    data-confirm-title="Release Terminal {{ $terminal->terminal_number }}"
                                                                    data-confirm-message="The tablet currently set up as Terminal {{ $terminal->terminal_number }} will need to be set up again, and any connected panelist will be logged out."
                                                                    data-confirm-submit-label="Release Device"
                                                                    data-confirm-variant="btn-outline-danger-brand">
                                                                <x-icon name="log-out" /> Release Device
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-xl-7">
                        @php
                            $roomPanelSource = $panel->queue['current'] ?? $panel->queue['called'] ?? collect($panel->queue['upcoming'] ?? [])->first();
                            $roomPanelLabel = ($panel->queue['current'] || $panel->queue['called']) ? 'Panelist' : 'Panelist (Next to Call)';
                            $roomAssignedPanel = $roomPanelSource?->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'ASSIGNED_PANELIST') ?? collect();
                            $roomBackupPanel = $roomPanelSource?->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'BACKUP_PANELIST') ?? collect();
                            $roomUpcomingThree = collect($panel->queue['upcoming'] ?? [])->take(3);
                            $roomPlannedEnd = $panel->room->room_end_time ?? $date->event_end_time;
                        @endphp
                        {{-- Sizes live in .room-data-* (theme-head) so the card
                             keeps its tablet-sized look and scales up on wider
                             screens (user-directed 2026-09-13). --}}
                        <div class="card-brand p-3 h-100 room-data" data-focus="room-{{ $panel->room->id }}" data-focus-reveal="[data-room-tab='{{ $panel->room->id }}']">
                            <h4 class="room-data-title mb-2">Room Data</h4>

                            <div class="room-data-section room-data-stats mb-2">
                                <div>
                                    <div class="room-data-label">Start</div>
                                    <div class="fw-semibold">{{ $panel->room->startTime()?->format('g:i A') ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="room-data-label">End</div>
                                    <div class="fw-semibold">{{ $roomPlannedEnd ? \Carbon\Carbon::parse($roomPlannedEnd)->format('g:i A') : '—' }}</div>
                                </div>
                                <div>
                                    <div class="room-data-label">Duration / Group</div>
                                    <div class="fw-semibold">{{ $category->categoryScheduleSetting?->duration_minutes ? $category->categoryScheduleSetting->duration_minutes . ' min' : '—' }}</div>
                                </div>
                                <div>
                                    <div class="room-data-label">Registered in Category</div>
                                    <div class="fw-semibold">{{ $panel->dayStats['registeredInCategory'] }}</div>
                                </div>
                                <div>
                                    <div class="room-data-label">Scheduled Today</div>
                                    <div class="fw-semibold">{{ $panel->dayStats['scheduledToday'] }}</div>
                                </div>
                                <div>
                                    <div class="room-data-label">Completed Today</div>
                                    <div class="fw-semibold">{{ $panel->dayStats['completedToday'] }}</div>
                                </div>
                            </div>

                            @if ($roomPanelSource)
                                <div class="room-data-section mb-2">
                                    <div class="room-data-label mb-1">{{ $roomPanelLabel }}</div>
                                    <div class="room-data-body mb-2">
                                        {{ $roomPanelSource->presentationAttempt->researchGroup->group_reference }}
                                        &mdash;
                                        {{ $roomPanelSource->presentationAttempt->researchGroup->current_project_title ?? $roomPanelSource->presentationAttempt->researchGroup->leader()?->full_name }}
                                    </div>
                                    @php $roomMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($roomAssignedPanel); @endphp
                                    @forelse ($roomAssignedPanel as $pa)
                                        <span class="badge badge-muted-tint room-data-badge mb-1 me-1 d-inline-block">{{ $pa->is_lead ? 'Chair: ' : 'Member ' . ($roomMemberNumbers[$pa->id] ?? '') . ': ' }}{{ trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? '')) }}</span>
                                    @empty
                                        <span class="text-brand-muted d-block">No panel assigned</span>
                                    @endforelse
                                    @foreach ($roomBackupPanel as $pb)
                                        <span class="badge badge-brand-tint room-data-badge mb-1 me-1 d-inline-block">Alternate Panel: {{ trim(($pb->panelist->profile->first_name ?? '') . ' ' . ($pb->panelist->profile->last_name ?? '')) }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="room-data-section">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div class="room-data-label">Next</div>
                                    <button type="button" class="btn btn-sm btn-outline-brand room-data-btn" data-room-view-all-toggle="{{ $panel->room->id }}"><x-icon name="eye" /> View All</button>
                                </div>

                                @if ($roomUpcomingThree->isNotEmpty())
                                    <div class="room-data-next">
                                        @foreach ($roomUpcomingThree as $n)
                                            <div class="room-data-next-item">
                                                <div class="fw-semibold">{{ $n->presentationAttempt->researchGroup->group_reference }}</div>
                                                <div class="text-brand-muted room-data-sub">{{ $n->presentationAttempt->researchGroup->leader()?->full_name ?? '—' }}</div>
                                                @if ($n->expected_start_at)
                                                    <div class="text-brand-muted room-data-sub">Expected {{ $n->expected_start_at->format('g:i A') }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="room-data-body text-brand-muted">—</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @include('admin.live-monitoring.partials.room-view-all-overlay', [
                    'room' => $panel->room,
                    'fullQueue' => $panel->queue['schedules'],
                ])
            </div>

            @if ($session)
                <div class="modal fade" id="pause-modal-{{ $panel->room->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.live-monitoring.room-sessions.pause', [$category, $session]) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Pause {{ $panel->room->room_name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Reason (optional)</label>
                                        <select name="reason_id" class="form-select">
                                            <option value="">None</option>
                                            @foreach ($adjustmentReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Remarks (optional)</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="pause" /> Pause</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="end-room-modal-{{ $panel->room->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.live-monitoring.room-sessions.end', [$category, $session]) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">End {{ $panel->room->room_name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    @php
                                        $remainingHere = collect($panel->queue['schedules'] ?? [])
                                            ->filter(fn ($s) => ! ($s->presentationAttempt?->presentationStatus?->is_terminal))
                                            ->values();
                                        // Mirrors EventActivationService::roomsStillToRun() — the day
                                        // ends only once every other room still on it has started and closed.
                                        $otherRoomsToRun = $panels->filter(fn ($p) => $p->room->id !== $panel->room->id
                                            && $p->room->roomUseStatus?->is_accepting_queue
                                            && (! $p->session || ! $p->session->ended_at));
                                        $lastOpenRoom = $otherRoomsToRun->isEmpty();
                                    @endphp
                                    <p class="mb-2">
                                        {{ $lastOpenRoom
                                            ? 'This is the day\'s last open room — closing it ends the day and moves any unfinished groups to the next scheduled day.'
                                            : 'This room closes for the day. The day stays live until ' . $otherRoomsToRun->map(fn ($p) => $p->room->room_name)->implode(', ') . ' ' . ($otherRoomsToRun->count() === 1 ? 'is' : 'are') . ' started and closed.' }}
                                    </p>
                                    @if ($remainingHere->isNotEmpty())
                                        <p class="mb-1 small text-brand-muted">{{ $remainingHere->count() }} group(s) still queued here:</p>
                                        <ul class="confirm-action-list mb-0">
                                            @foreach ($remainingHere as $remaining)
                                                <li>{{ $remaining->queueEntry?->queue_number }}. {{ $remaining->presentationAttempt->researchGroup->group_reference }} &mdash; {{ $remaining->presentationAttempt->presentationStatus?->name }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="stop" /> End Room</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
</div>
