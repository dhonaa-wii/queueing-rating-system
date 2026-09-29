{{--
    Shared by Presentation Setup's Schedules tab and Live Monitoring — same
    CapacityAnalysisService::analyze() output, same layout, so "is there
    enough capacity" reads identically everywhere an admin sees it. Expects
    $capacityAnalysis (the array analyze() returns) and optional $title
    (defaults to "Capacity Analysis"). The stat grid reflows via CSS
    auto-fit, so this reads well both full-width and squeezed into a
    half-width column.
--}}
<div class="card-brand capacity-card">
    <div class="capacity-card-header">
        <div class="d-flex align-items-center gap-2">
            <span class="capacity-header-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </span>
            <div>
                <h3 class="h6 mb-0">{{ $title ?? 'Capacity Analysis' }}</h3>
                <p class="small text-brand-muted mb-0">Room &amp; schedule capacity vs. registered groups</p>
            </div>
        </div>

        @if ($capacityAnalysis['configured'])
            <span class="capacity-status-pill {{ $capacityAnalysis['status'] === 'enough' ? 'text-brand-success' : 'text-brand-danger' }}"
                  style="background-color: {{ $capacityAnalysis['status'] === 'enough' ? 'var(--brand-success-tint)' : 'var(--brand-danger-tint)' }};">
                @if ($capacityAnalysis['status'] === 'enough')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                @endif
                {{ $capacityAnalysis['status'] === 'enough' ? 'Enough Capacity' : 'Not Enough Capacity' }}
            </span>
        @endif
    </div>

    @if ($capacityAnalysis['configured'])
        <div class="capacity-stat-grid">
            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Total Capacity</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['total_capacity'] }}</div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Days Remaining</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['days'] }} <small>({{ $capacityAnalysis['completed_days'] }}/{{ $capacityAnalysis['total_days'] }} done)</small></div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Rooms</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['rooms'] }}</div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Min/Remaining Day</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['minutes_per_day'] }}</div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="7"></circle><polyline points="12 9 12 12 13.5 13.5"></polyline><path d="M16.51 17.35l-.35 3.83a2 2 0 0 1-2 1.82H9.83a2 2 0 0 1-2-1.82l-.35-3.83"></path><path d="M7.49 6.65l.35-3.83A2 2 0 0 1 9.83 1h4.35a2 2 0 0 1 2 1.82l.35 3.83"></path></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Duration/Group</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['duration_per_group'] }} <small>min</small></div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Slots/Room/Day</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['slots_per_room_per_day'] }}</div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Registered</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['registered_groups'] }}</div>
                </div>
            </div>

            <div class="capacity-stat-item">
                <span class="capacity-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                </span>
                <div>
                    <div class="capacity-stat-label">Awaiting a Slot</div>
                    <div class="capacity-stat-value">{{ $capacityAnalysis['groups_awaiting_slot'] }}</div>
                </div>
            </div>
        </div>

        <div class="capacity-message" style="{{ $capacityAnalysis['status'] === 'enough'
            ? 'background-color: var(--brand-success-tint); border-color: var(--brand-success); color: var(--brand-success);'
            : 'background-color: var(--brand-danger-tint); border-color: var(--brand-danger); color: var(--brand-danger);' }}">
            @if ($capacityAnalysis['status'] === 'enough')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            @endif
            <span>{{ $capacityAnalysis['message'] }}</span>
        </div>
    @else
        <div class="capacity-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            <p class="small mb-0">Not yet configured.</p>
        </div>
    @endif
</div>
