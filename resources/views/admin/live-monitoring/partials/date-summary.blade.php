{{-- Compact per-day status card for every date that isn't the featured one —
     "View All Days" reveals a stack of these instead of duplicating the full
     detail card (room tabs, live queue, terminals) for every single date.
     Laid out as a single row (when on the left, status/rooms/action on the
     right) so a long list of days stays scannable; it wraps to stacked lines
     on narrow screens. --}}
@php
    $summaryCode = $date->eventDateStatus?->code;
    $summaryOverdue = $date->isOverdue();
    // Same rule as the full detail card: a day that can no longer run
    // doesn't list its rooms here either (user-directed 2026-09-12).
    $summaryShowRooms = $date->isOpenForScheduling();
@endphp
<div class="card-brand p-3 mb-2 event-summary">
    <div class="event-summary-row">
        <div class="event-summary-when">
            <div class="event-summary-date">{{ $date->presentation_date->format('l, M j, Y') }}</div>
            <div class="event-day-time">{{ $date->event_start_time }}&ndash;{{ $date->event_end_time }}</div>
        </div>

        <div class="event-summary-side">
            @if ($summaryShowRooms && $date->presentationDateRooms->isNotEmpty())
                <div class="event-summary-rooms">
                    @foreach ($date->presentationDateRooms as $room)
                        @php $roomSession = $room->roomSessions->sortByDesc('id')->first(); @endphp
                        <span class="event-summary-room">
                            {{ $room->room_name }}
                            @include('admin.live-monitoring.partials.room-session-status-badge', ['status' => $roomSession?->roomSessionStatus])
                        </span>
                    @endforeach
                </div>
            @elseif ($summaryShowRooms)
                <span class="text-brand-muted event-day-time">No rooms configured</span>
            @endif

            @include('admin.live-monitoring.partials.event-date-status-badge', ['status' => $date->eventDateStatus, 'overdue' => $summaryOverdue])

            <a href="{{ route('admin.live-monitoring.show', $category) }}?date={{ $date->id }}" class="btn btn-sm btn-outline-brand"><x-icon name="eye" /> View</a>
        </div>
    </div>

    @if ($summaryOverdue)
        <p class="text-brand-danger event-day-note mb-0 mt-2">
            This date's window has passed without being started &mdash;
            <a href="{{ route('admin.categories.show', $category) }}#tab-schedules">update the date</a> before starting.
        </p>
    @endif
</div>
