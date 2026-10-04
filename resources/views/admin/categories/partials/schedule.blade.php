@include('admin.partials.capacity-analysis-card', ['capacityAnalysis' => $capacityAnalysis])

@php
    // Room-registry interaction, user-directed 2026-09-21/25: rooms and days
    // are both checkboxes. "Assign to Selected Days" needs at least one of
    // each. The same controls exist
    // twice — on the page and in the Created modal — and each one only reads
    // the checkboxes inside its own [data-room-assigner] root, so ticking
    // something on the page can't leak into the modal's selection.
    $newlyCreatedDateIds = session('newly_created_date_ids', []);
    $newlyCreatedDates = ! empty($newlyCreatedDateIds)
        ? $category->presentationDates->whereIn('id', $newlyCreatedDateIds)->sortBy('presentation_date')
        : collect();

    // Registry room ids already placed on each day (matched by name, the same
    // way CategoryRoomController::placeRooms() matches; a REMOVED row counts as
    // unassigned because placing the room again reactivates it). The script
    // uses this to keep an already-placed room/day pair from being picked.
    $assignedRoomIdsByDate = $category->presentationDates->mapWithKeys(function ($d) use ($category) {
        $names = $d->presentationDateRooms
            ->filter(fn ($r) => $r->roomUseStatus->code !== 'REMOVED')
            ->map(fn ($r) => mb_strtolower($r->room_name))
            ->all();

        return [$d->id => $category->categoryRooms
            ->filter(fn ($cr) => in_array(mb_strtolower($cr->room_name), $names, true))
            ->pluck('id')->implode(',')];
    });
    // The category's active research tracks: rooms can be limited to them (TrackRouting).
    $activeTracks = $category->researchTracks->where('is_active', true)->values();
@endphp

<div class="row g-4 mt-1" data-room-assigner>
    <div class="col-lg-5">
        <div class="card-brand p-4 mb-4">
            <h3 class="h6 mb-3">Registration &amp; Event Timing</h3>

            <form method="POST" action="{{ route('admin.categories.schedule-config.update', $category) }}" data-ajax="update">
                @csrf
                @method('PUT')

                <div class="registration-window-grid mb-3">
                    <div>
                        <label for="registration_opens_at" class="form-label">
                            Registration Opens
                            @unless ($category->isRegistrationConfigured())<span class="tab-incomplete-dot"></span>@endunless
                        </label>
                        <input type="datetime-local" name="registration_opens_at" id="registration_opens_at" class="form-control"
                               value="{{ old('registration_opens_at', optional($category->registration_opens_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div>
                        <label for="registration_closes_at" class="form-label">
                            Registration Closes
                            @unless ($category->isRegistrationConfigured())<span class="tab-incomplete-dot"></span>@endunless
                        </label>
                        <input type="datetime-local" name="registration_closes_at" id="registration_closes_at" class="form-control"
                               value="{{ old('registration_closes_at', optional($category->registration_closes_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>

                <hr class="brand-divider">

                <div class="mb-3">
                    <label for="duration_minutes" class="form-label">Duration per Group (minutes)</label>
                    <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="1" max="32000" required
                           value="{{ old('duration_minutes', optional($category->categoryScheduleSetting)->duration_minutes) }}">
                </div>

                <button type="submit" class="btn btn-brand mt-2"><x-icon name="save" /> Save Schedule</button>
            </form>

            <hr class="brand-divider my-4">

            <h3 class="h6 mb-3">Registered Rooms</h3>

            {{-- With tracks configured, Register Room opens a modal to pick the
                 room's tracks; its checkboxes and submit belong to this form
                 through the form attribute. --}}
            <form method="POST" action="{{ route('admin.categories.rooms.store', $category) }}" id="register-room-form" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                @csrf
                <input type="text" name="room_name" placeholder="Room name" class="form-control form-control-sm" style="width:auto;" required maxlength="100" data-register-room-name>
                @if ($activeTracks->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-outline-brand" data-register-room-open><x-icon name="plus" /> Register Room</button>
                @else
                    <button type="submit" class="btn btn-sm btn-outline-brand"><x-icon name="plus" /> Register Room</button>
                @endif
            </form>

            @if ($category->categoryRooms->isEmpty())
                <p class="text-brand-muted small mb-0">No rooms registered yet.</p>
            @else
                <div class="room-registry-list" data-room-registry>
                    @foreach ($category->categoryRooms as $categoryRoom)
                        @php
                            $roomOpenUsage = collect();
                            foreach ($category->presentationDates as $usageDate) {
                                if (! $usageDate->isOpenForScheduling()) {
                                    continue;
                                }
                                $usageMatch = $usageDate->presentationDateRooms->first(fn ($dateRoom) => $dateRoom->roomUseStatus->code !== 'REMOVED'
                                    && mb_strtolower($dateRoom->room_name) === mb_strtolower($categoryRoom->room_name));
                                if ($usageMatch) {
                                    $roomOpenUsage->push($usageDate);
                                }
                            }
                        @endphp
                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}" style="border-color: var(--brand-border) !important;">
                            <div class="form-check mb-0">
                                <input class="form-check-input room-select-checkbox" type="checkbox" value="{{ $categoryRoom->id }}" id="room-check-{{ $categoryRoom->id }}">
                                <label class="form-check-label" for="room-check-{{ $categoryRoom->id }}">{{ $categoryRoom->room_name }}</label>
                                @foreach ($categoryRoom->researchTracks->where('is_active', true) as $track)
                                    <span class="badge badge-info-tint ms-1">{{ $track->name }}</span>
                                @endforeach
                            </div>
                            <div class="dropdown">
                                <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Room actions">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100" data-bs-toggle="modal" data-bs-target="#edit-room-modal-{{ $categoryRoom->id }}">
                                            <x-icon name="edit" class="dropdown-item-icon" /> Edit
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        @if ($roomOpenUsage->isNotEmpty())
                                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger-brand w-100"
                                                    data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                    data-confirm-action="{{ route('admin.categories.rooms.destroy', [$category, $categoryRoom]) }}"
                                                    data-confirm-method="DELETE"
                                                    data-confirm-title="Delete {{ $categoryRoom->room_name }}"
                                                    data-confirm-message="This room is on {{ $roomOpenUsage->count() }} ongoing/upcoming day(s) and will be removed from all of them:"
                                                    data-confirm-list="{{ $roomOpenUsage->sortBy('presentation_date')->map(fn ($d) => $d->presentation_date->format('M j, Y'))->values()->toJson() }}"
                                                    data-confirm-submit-label="Delete Room">
                                                <x-icon name="trash" class="dropdown-item-icon" /> Delete
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.categories.rooms.destroy', [$category, $categoryRoom]) }}" class="w-100">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger-brand w-100">
                                                    <x-icon name="trash" class="dropdown-item-icon" /> Delete
                                                </button>
                                            </form>
                                        @endif
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($category->presentationDates->isNotEmpty())
                    @include('admin.categories.partials.room-assign-form', ['category' => $category, 'formClass' => 'mt-3 mb-0'])
                @endif
            @endif
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-brand p-4 mb-4">
            {{-- Before the first date exists the span form is the card's whole
                 content; once one does, the card lists the dates instead and
                 the same form opens from Add Date. --}}
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <h3 class="h6 mb-0">
                    {!! $category->presentationDates->isEmpty() ? 'Presentation Date Span' : 'Presentation Dates &amp; Rooms' !!}
                    @unless ($category->isEventConfigured())<span class="tab-incomplete-dot"></span>@endunless
                </h3>
                @if ($category->presentationDates->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-brand text-nowrap" data-bs-toggle="modal" data-bs-target="#add-date-span-modal"
                            data-focus="date-span" data-focus-reveal="[data-bs-target='#tab-schedules']">
                        <x-icon name="plus" /> Add Date
                    </button>
                @endif
            </div>

            @if ($category->presentationDates->isEmpty())
                @include('admin.categories.partials.date-span-form', ['category' => $category, 'focusable' => true])
            @else
                <div class="p-0" style="overflow-x: auto;">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="text-brand-muted small">
                                <th style="width: 1%;">
                                    <input class="form-check-input date-select-all" type="checkbox" aria-label="Select all days">
                                </th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Rooms</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Nearest first: ongoing/upcoming days earliest first, then
                                 finished or passed days, most recent first. --}}
                            @foreach ($category->presentationDates
                                ->sortBy(fn ($d) => $d->isOpenForScheduling()
                                    ? '0|' . $d->presentation_date->format('Y-m-d') . ' ' . $d->event_start_time . '|' . str_pad($d->id, 10, '0', STR_PAD_LEFT)
                                    : '1|' . (99999999 - (int) $d->presentation_date->format('Ymd')) . '|' . str_pad(PHP_INT_MAX - $d->id, 20, '0', STR_PAD_LEFT)) as $date)
                                @php
                                    $dateActiveRooms = $date->presentationDateRooms->filter(fn ($r) => $r->roomUseStatus->code !== 'REMOVED');
                                    $locked = in_array($date->eventDateStatus->code, ['COMPLETED', 'CANCELLED'], true);
                                    // Editing/removing a past date stays available — editing is how
                                    // an overdue day gets fixed — but its contents (rooms, breaks)
                                    // are only ever changed on an ongoing or upcoming day.
                                    $acceptsRooms = $date->isOpenForScheduling();
                                @endphp
                                <tr data-focus="date-{{ $date->id }}" data-focus-reveal="[data-bs-target='#tab-schedules']">
                                    <td>
                                        @if ($acceptsRooms)
                                            <input class="form-check-input date-select-checkbox" type="checkbox" value="{{ $date->id }}"
                                                   data-assigned-rooms="{{ $assignedRoomIdsByDate[$date->id] ?? '' }}"
                                                   aria-label="Select {{ $date->presentation_date->format('M j, Y') }}">
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $date->presentation_date->format('M j, Y') }}<br>
                                        <span class="badge badge-info-tint">{{ $date->eventDateStatus->name }}</span>
                                    </td>
                                    <td class="text-nowrap">{{ substr($date->event_start_time, 0, 5) }}&ndash;{{ substr($date->event_end_time, 0, 5) }}</td>
                                    <td>
                                        @forelse ($dateActiveRooms as $dateRoom)
                                            @php
                                                // Groups still queued in this room that haven't presented yet —
                                                // named in the confirmation so removing a room mid-event is never
                                                // a blind action. A group actually presenting right now blocks
                                                // the removal outright (re-checked in
                                                // PresentationDateController::destroyRoom()).
                                                $roomUnfinished = $dateRoom->attemptSchedules
                                                    ->filter(fn ($s) => $s->queueEntry && $s->queueEntry->removed_at === null)
                                                    ->filter(fn ($s) => ! $s->presentationAttempt?->presentationStatus?->is_terminal);
                                                $roomPresenting = $roomUnfinished
                                                    ->first(fn ($s) => in_array($s->presentationAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true));
                                                $roomUnfinishedLabels = $roomUnfinished
                                                    ->sortBy(fn ($s) => $s->queueEntry->queue_number)
                                                    ->map(fn ($s) => $s->queueEntry->queue_number . '. ' . $s->presentationAttempt->researchGroup->group_reference
                                                        . ' — ' . $s->presentationAttempt->presentationStatus?->name)
                                                    ->values();
                                            @endphp
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <span>{{ $dateRoom->room_name }}</span>
                                                @if ($acceptsRooms)
                                                    <button type="button" class="btn btn-sm btn-link text-brand-muted p-0"
                                                            data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                            data-confirm-action="{{ route('admin.categories.dates.rooms.destroy', [$category, $date, $dateRoom]) }}"
                                                            data-confirm-method="DELETE"
                                                            data-confirm-title="Remove {{ $dateRoom->room_name }}"
                                                            data-confirm-message="{{ $roomPresenting
                                                                ? $roomPresenting->presentationAttempt->researchGroup->group_reference . ' is presenting in this room right now. ' . $roomUnfinished->count() . ' unfinished group(s) are queued in it and will stay queued until they are transferred elsewhere. Remove it anyway?'
                                                                : ($roomUnfinished->isEmpty()
                                                                    ? 'Remove this room from the date?'
                                                                    : $roomUnfinished->count() . ' unfinished group(s) are queued in this room and will stay queued in it until they are transferred elsewhere:') }}"
                                                            data-confirm-list="{{ $roomUnfinishedLabels->toJson() }}"
                                                            data-confirm-submit-label="Remove">&times;</button>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-brand-muted small">None</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        @unless ($locked)
                                            <button type="button" class="btn btn-sm btn-outline-brand text-nowrap" data-bs-toggle="modal" data-bs-target="#edit-date-modal-{{ $date->id }}">
                                                <x-icon name="edit" /> Edit
                                            </button>
                                        @endunless
                                    </td>
                                    <td>
                                        @unless ($locked)
                                            <button type="button" class="btn btn-sm btn-outline-danger-brand text-nowrap" data-bs-toggle="modal" data-bs-target="#remove-date-modal-{{ $date->id }}">
                                                <x-icon name="trash" /> Delete
                                            </button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($category->presentationDates->isNotEmpty())
    <div class="modal fade" id="add-date-span-modal" tabindex="-1" aria-hidden="true"
         @if ($errors->hasAny(['start_date', 'end_date', 'event_start_time', 'event_end_time', 'break_start_time', 'break_end_time'])) data-span-errors="1" @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Presentation Dates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('admin.categories.partials.date-span-form', ['category' => $category, 'focusable' => false])
                </div>
            </div>
        </div>
    </div>
@endif

@if ($activeTracks->isNotEmpty())
    <div class="modal fade" id="register-room-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Register <span data-register-room-title></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-label">Tracks</div>
                    <div data-track-group>
                        @if ($activeTracks->count() > 1)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" data-track-all id="register-room-track-all">
                                <label class="form-check-label fw-semibold" for="register-room-track-all">All tracks</label>
                            </div>
                        @endif
                        @foreach ($activeTracks as $track)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="track_ids[]" value="{{ $track->id }}" form="register-room-form" data-track-option id="register-room-track-{{ $track->id }}">
                                <label class="form-check-label" for="register-room-track-{{ $track->id }}">{{ $track->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="register-room-form" class="btn btn-brand"><x-icon name="plus" /> Register Room</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($category->categoryRooms->isNotEmpty())
    @foreach ($category->categoryRooms as $categoryRoom)
        <div class="modal fade" id="edit-room-modal-{{ $categoryRoom->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.categories.rooms.update', [$category, $categoryRoom]) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Room</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="edit-room-name-{{ $categoryRoom->id }}" class="form-label">Room Name</label>
                            <input type="text" name="room_name" id="edit-room-name-{{ $categoryRoom->id }}" class="form-control"
                                   value="{{ $categoryRoom->room_name }}" required maxlength="100">
                            @if ($activeTracks->isNotEmpty())
                                @php $roomTrackIds = $categoryRoom->researchTracks->pluck('id')->all(); @endphp
                                <div class="form-label mt-3">Tracks</div>
                                <div data-track-group>
                                    @if ($activeTracks->count() > 1)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" data-track-all id="edit-room-{{ $categoryRoom->id }}-track-all">
                                            <label class="form-check-label fw-semibold" for="edit-room-{{ $categoryRoom->id }}-track-all">All tracks</label>
                                        </div>
                                    @endif
                                    @foreach ($activeTracks as $track)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="track_ids[]" value="{{ $track->id }}" data-track-option id="edit-room-{{ $categoryRoom->id }}-track-{{ $track->id }}" @checked(in_array($track->id, $roomTrackIds, true))>
                                            <label class="form-check-label" for="edit-room-{{ $categoryRoom->id }}-track-{{ $track->id }}">{{ $track->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

@if ($category->presentationDates->isNotEmpty())
    @foreach ($category->presentationDates as $date)
        @continue(in_array($date->eventDateStatus->code, ['COMPLETED', 'CANCELLED'], true))
        @php
            $dateActiveRooms = $date->presentationDateRooms->filter(fn ($r) => $r->roomUseStatus->code !== 'REMOVED');
            $dateQueuedCount = $dateActiveRooms->sum(fn ($r) => $r->attemptSchedules->count());
            $dateAcceptsChanges = $date->isOpenForScheduling();
        @endphp

        <div class="modal fade" id="edit-date-modal-{{ $date->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit {{ $date->presentation_date->format('l, F j, Y') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('admin.categories.dates.update', [$category, $date]) }}" class="d-flex gap-2 align-items-center flex-wrap mb-3">
                            @csrf
                            @method('PUT')
                            <input type="time" name="event_start_time" value="{{ substr($date->event_start_time, 0, 5) }}" class="form-control form-control-sm" style="width:auto;" required>
                            <span>&ndash;</span>
                            <input type="time" name="event_end_time" value="{{ substr($date->event_end_time, 0, 5) }}" class="form-control form-control-sm" style="width:auto;" required>
                            <button type="submit" class="btn btn-sm btn-brand"><x-icon name="save" /> Save Time</button>
                        </form>

                        @if ($dateAcceptsChanges && $dateActiveRooms->isNotEmpty())
                            <hr class="brand-divider">
                            <div class="small text-brand-muted mb-2">Break Periods</div>

                            @foreach ($dateActiveRooms as $dateRoom)
                                @foreach ($dateRoom->scheduleBreaks->sortBy('planned_start_at') as $break)
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">
                                            <span class="fw-semibold">{{ $dateRoom->room_name }}</span> &middot;
                                            {{ $break->name }} ({{ $break->planned_start_at->format('H:i') }}&ndash;{{ $break->planned_end_at->format('H:i') }})
                                        </span>
                                        <button type="button" class="btn btn-sm btn-link text-brand-muted p-0"
                                                data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                data-confirm-action="{{ route('admin.categories.dates.rooms.breaks.destroy', [$category, $date, $dateRoom, $break]) }}"
                                                data-confirm-method="DELETE"
                                                data-confirm-title="Remove Break"
                                                data-confirm-message="Remove this break?"
                                                data-confirm-submit-label="Remove">
                                            Remove
                                        </button>
                                    </div>
                                @endforeach
                            @endforeach

                            <form method="POST" action="{{ route('admin.categories.dates.breaks.store', [$category, $date]) }}" class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                @csrf
                                <select name="room_id" class="form-select form-select-sm" style="width:auto;" required>
                                    <option value="all">All rooms</option>
                                    @foreach ($dateActiveRooms as $dateRoom)
                                        <option value="{{ $dateRoom->id }}">{{ $dateRoom->room_name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="name" placeholder="Break name" class="form-control form-control-sm" style="width:auto;" required maxlength="100">
                                <input type="time" name="planned_start_at" class="form-control form-control-sm" style="width:auto;" required>
                                <span>&ndash;</span>
                                <input type="time" name="planned_end_at" class="form-control form-control-sm" style="width:auto;" required>
                                <button type="submit" class="btn btn-sm btn-outline-brand"><x-icon name="plus" /> Add Break</button>
                            </form>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="remove-date-modal-{{ $date->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Remove Presentation Date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            Remove <strong>{{ $date->presentation_date->format('l, F j, Y') }}</strong>?
                            This date has {{ $dateActiveRooms->count() }} room{{ $dateActiveRooms->count() === 1 ? '' : 's' }}
                            @if ($dateQueuedCount > 0)
                                with <strong>{{ $dateQueuedCount }}</strong> queued group{{ $dateQueuedCount === 1 ? '' : 's' }} scheduled here.
                                Removing it will discard the category's current queue and regenerate it fresh for the remaining schedule.
                            @else
                                and no groups currently queued here.
                            @endif
                            This cannot be undone.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="{{ route('admin.categories.dates.destroy', [$category, $date]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Remove Date</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif

@if ($newlyCreatedDates->isNotEmpty())
    <div class="modal fade" id="dates-created-modal" tabindex="-1" aria-hidden="true" data-auto-open="1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Created</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                @php
                    $newWeekendDates = $newlyCreatedDates->filter(fn ($d) => $d->presentation_date->isWeekend() && $d->activated_at === null);
                @endphp
                @if ($newWeekendDates->isNotEmpty())
                    <div class="modal-body" data-weekend-prompt>
                        <p class="mb-2">{{ $newlyCreatedDates->count() }} presentation date{{ $newlyCreatedDates->count() === 1 ? '' : 's' }} created:</p>
                        <ul class="mb-3 ps-3">
                            @foreach ($newlyCreatedDates as $createdDate)
                                <li class="{{ $createdDate->presentation_date->isWeekend() ? 'fw-semibold' : '' }}">{{ $createdDate->presentation_date->format('l, F j, Y') }}</li>
                            @endforeach
                        </ul>
                        <p class="fw-semibold mb-3">Include Saturday and Sunday?</p>
                        <div class="d-flex gap-2 justify-content-end">
                            <form method="POST" action="{{ route('admin.categories.dates.weekends.exclude', $category) }}">
                                @csrf
                                @foreach ($newlyCreatedDates as $createdDate)
                                    <input type="hidden" name="date_ids[]" value="{{ $createdDate->id }}">
                                @endforeach
                                <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="x" /> No</button>
                            </form>
                            <button type="button" class="btn btn-brand" data-weekend-include><x-icon name="check" /> Yes</button>
                        </div>
                    </div>
                @endif
                <div class="modal-body @if ($newWeekendDates->isNotEmpty()) d-none @endif" data-room-assigner data-created-body>
                    <p class="mb-2">{{ $newlyCreatedDates->count() }} presentation date{{ $newlyCreatedDates->count() === 1 ? '' : 's' }} created:</p>
                    <div class="mb-3">
                        @if ($newlyCreatedDates->count() > 1)
                            <div class="form-check">
                                <input class="form-check-input date-select-all" type="checkbox" id="modal-date-check-all">
                                <label class="form-check-label fw-semibold" for="modal-date-check-all">Select all</label>
                            </div>
                        @endif
                        @foreach ($newlyCreatedDates as $createdDate)
                            <div class="form-check">
                                <input class="form-check-input date-select-checkbox" type="checkbox" value="{{ $createdDate->id }}"
                                       data-assigned-rooms="{{ $assignedRoomIdsByDate[$createdDate->id] ?? '' }}"
                                       id="modal-date-check-{{ $createdDate->id }}" @disabled(! $createdDate->isOpenForScheduling())>
                                <label class="form-check-label" for="modal-date-check-{{ $createdDate->id }}">{{ $createdDate->presentation_date->format('l, F j, Y') }}</label>
                            </div>
                        @endforeach
                    </div>

                    <hr class="brand-divider">

                    <div class="small text-brand-muted mb-2">Rooms</div>

                    <form method="POST" action="{{ route('admin.categories.rooms.store', $category) }}" class="mb-1" data-room-register-form>
                        @csrf
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="text" name="room_name" placeholder="Room name" class="form-control form-control-sm" style="width:auto;" required maxlength="100">
                            <button type="submit" class="btn btn-sm btn-outline-brand"><x-icon name="plus" /> Register Room</button>
                        </div>
                        {{-- Same track choice as the page's Register Room (TrackRouting). --}}
                        @if ($activeTracks->isNotEmpty())
                            <div class="d-flex flex-wrap align-items-center gap-3 mt-2 ps-2 border-start" style="border-color: var(--brand-border) !important;" data-track-group>
                                <span class="small text-brand-muted">Tracks</span>
                                @if ($activeTracks->count() > 1)
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" data-track-all id="modal-register-track-all">
                                        <label class="form-check-label fw-semibold" for="modal-register-track-all">All tracks</label>
                                    </div>
                                @endif
                                @foreach ($activeTracks as $track)
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="track_ids[]" value="{{ $track->id }}" data-track-option id="modal-register-track-{{ $track->id }}">
                                        <label class="form-check-label" for="modal-register-track-{{ $track->id }}">{{ $track->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </form>
                    <div class="text-danger small mb-2 d-none" data-room-register-error></div>

                    <div class="mb-3 mt-2" data-room-list style="max-height: 11rem; overflow-y: auto;">
                        @foreach ($category->categoryRooms as $categoryRoom)
                            <div class="form-check">
                                <input class="form-check-input room-select-checkbox" type="checkbox" value="{{ $categoryRoom->id }}" id="modal-room-check-{{ $categoryRoom->id }}">
                                <label class="form-check-label" for="modal-room-check-{{ $categoryRoom->id }}">{{ $categoryRoom->room_name }}</label>
                                @foreach ($categoryRoom->researchTracks->where('is_active', true) as $track)
                                    <span class="badge badge-info-tint ms-1">{{ $track->name }}</span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    @include('admin.categories.partials.room-assign-form', ['category' => $category])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    <script>
        (function () {
            // Every listener here is delegated from document: the Schedules
            // pane is re-rendered in place after each save (scripts.blade.php's
            // soft refresh), which used to drop listeners bound to its
            // elements and leave Register Room and "All tracks" dead until a
            // full reload.

            // "All tracks" toggles every track in its group, and follows them back.
            function syncTrackGroup(group) {
                var all = group.querySelector('[data-track-all]');
                if (!all) return;
                var options = Array.from(group.querySelectorAll('[data-track-option]'));
                var checked = options.filter(function (cb) { return cb.checked; }).length;
                all.checked = options.length > 0 && checked === options.length;
                all.indeterminate = checked > 0 && checked < options.length;
            }

            document.addEventListener('change', function (event) {
                var group = event.target.closest && event.target.closest('[data-track-group]');
                if (!group) return;
                if (event.target.matches('[data-track-all]')) {
                    group.querySelectorAll('[data-track-option]').forEach(function (cb) { cb.checked = event.target.checked; });
                }
                syncTrackGroup(group);
            });

            document.addEventListener('show.bs.modal', function (event) {
                event.target.querySelectorAll('[data-track-group]').forEach(syncTrackGroup);
            });

            // Register Room opens the tracks modal once the name is valid.
            function openTracks() {
                var nameInput = document.querySelector('[data-register-room-name]');
                var modalEl = document.getElementById('register-room-modal');
                if (!nameInput || !modalEl || !nameInput.reportValidity()) return;
                modalEl.querySelector('[data-register-room-title]').textContent = nameInput.value.trim();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            document.addEventListener('click', function (event) {
                if (event.target.closest && event.target.closest('[data-register-room-open]')) {
                    openTracks();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' || !event.target.matches || !event.target.matches('[data-register-room-name]')) return;
                // Only intercept when the tracks modal exists; otherwise Enter submits as usual.
                if (!document.querySelector('[data-register-room-open]')) return;
                event.preventDefault();
                openTracks();
            });
        })();
    </script>
    <script>
        (function () {
            function checkedValues(root, selector) {
                return Array.from(root.querySelectorAll(selector + ':checked')).map(function (cb) {
                    return cb.value;
                });
            }

            function refreshAssigner(root) {
                var checkedRooms = checkedValues(root, '.room-select-checkbox');
                var checkedDays = checkedValues(root, '.date-select-checkbox');
                var assigned = {};

                root.querySelectorAll('.date-select-checkbox').forEach(function (cb) {
                    assigned[cb.value] = (cb.dataset.assignedRooms || '').split(',').filter(Boolean);
                });

                // A room already on a day can't be picked together with that
                // day: ticking either side disables the other's conflicts.
                root.querySelectorAll('.date-select-checkbox').forEach(function (cb) {
                    if (cb.dataset.locked === undefined) {
                        cb.dataset.locked = cb.disabled ? '1' : '';
                    }
                    if (cb.dataset.locked) {
                        return;
                    }
                    var taken = checkedRooms.some(function (r) { return assigned[cb.value].indexOf(r) !== -1; });
                    cb.disabled = taken;
                    cb.title = taken ? 'A selected room is already assigned to this day.' : '';
                });
                root.querySelectorAll('.room-select-checkbox').forEach(function (cb) {
                    var taken = checkedDays.some(function (d) { return assigned[d].indexOf(cb.value) !== -1; });
                    cb.disabled = taken;
                    cb.title = taken ? 'This room is already assigned to a selected day.' : '';
                });

                var roomCount = checkedRooms.length;
                var dayBoxes = Array.from(root.querySelectorAll('.date-select-checkbox:not(:disabled)'));
                var dayCount = dayBoxes.filter(function (cb) { return cb.checked; }).length;

                var ready = roomCount > 0 && dayCount > 0;
                root.querySelectorAll('[data-assign-form]').forEach(function (form) {
                    form.classList.toggle('d-none', ! ready);
                    form.classList.toggle('d-flex', ready);
                });
                root.querySelectorAll('.date-select-all').forEach(function (all) {
                    all.checked = dayBoxes.length > 0 && dayCount === dayBoxes.length;
                    all.indeterminate = dayCount > 0 && dayCount < dayBoxes.length;
                });
            }

            function appendHidden(container, name, values) {
                values.forEach(function (value) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    container.appendChild(input);
                });
            }

            document.addEventListener('change', function (event) {
                var target = event.target;
                if (! target.classList) {
                    return;
                }
                var isSelectAll = target.classList.contains('date-select-all');
                if (! isSelectAll && ! target.classList.contains('room-select-checkbox') && ! target.classList.contains('date-select-checkbox')) {
                    return;
                }
                var root = target.closest('[data-room-assigner]');
                if (! root) {
                    return;
                }
                if (isSelectAll) {
                    root.querySelectorAll('.date-select-checkbox:not(:disabled)').forEach(function (cb) {
                        cb.checked = target.checked;
                    });
                }
                refreshAssigner(root);
            });

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (! form.matches || ! form.matches('[data-assign-form]')) {
                    return;
                }
                var root = form.closest('[data-room-assigner]');
                var roomIds = root ? checkedValues(root, '.room-select-checkbox') : [];
                var dayIds = root ? checkedValues(root, '.date-select-checkbox') : [];

                if (roomIds.length === 0 || dayIds.length === 0) {
                    event.preventDefault();
                    return;
                }

                var container = form.querySelector('.assign-hidden-inputs');
                container.innerHTML = '';
                appendHidden(container, 'category_room_ids[]', roomIds);
                appendHidden(container, 'date_ids[]', dayIds);
            });

            // Registering a room from the Created modal stays in the modal:
            // the room is added to its list already ticked, and the page
            // behind it is refreshed once the modal closes so its own room
            // list catches up.
            var roomsRegisteredInModal = false;

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (! form.matches || ! form.matches('[data-room-register-form]')) {
                    return;
                }
                event.preventDefault();

                var root = form.closest('[data-room-assigner]');
                var errorBox = root.querySelector('[data-room-register-error]');
                var input = form.querySelector('input[name="room_name"]');
                var submit = form.querySelector('button[type="submit"]');

                errorBox.classList.add('d-none');
                submit.disabled = true;

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                }).then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                }).then(function (result) {
                    if (! result.ok) {
                        var errors = result.data.errors;
                        var first = errors ? Object.values(errors)[0][0] : null;
                        throw new Error(first || result.data.message || 'Could not register the room.');
                    }

                    var room = result.data.room;
                    var row = document.createElement('div');
                    row.className = 'form-check';
                    var box = document.createElement('input');
                    box.className = 'form-check-input room-select-checkbox';
                    box.type = 'checkbox';
                    box.value = room.id;
                    box.id = 'modal-room-check-' + room.id;
                    box.checked = true;
                    var label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.htmlFor = box.id;
                    label.textContent = room.room_name;
                    row.appendChild(box);
                    row.appendChild(label);
                    (room.tracks || []).forEach(function (name) {
                        var badge = document.createElement('span');
                        badge.className = 'badge badge-info-tint ms-1';
                        badge.textContent = name;
                        row.appendChild(badge);
                    });

                    var list = root.querySelector('[data-room-list]');
                    list.appendChild(row);
                    list.scrollTop = list.scrollHeight;

                    input.value = '';
                    form.querySelectorAll('[data-track-option], [data-track-all]').forEach(function (cb) {
                        cb.checked = false;
                        cb.indeterminate = false;
                    });
                    roomsRegisteredInModal = true;
                    refreshAssigner(root);
                    input.focus();
                }).catch(function (error) {
                    errorBox.textContent = error.message;
                    errorBox.classList.remove('d-none');
                }).finally(function () {
                    submit.disabled = false;
                });
            });

            // Auto-open the "Created" modal right after a date span is
            // saved — it lives inside the #tab-schedules pane, which is
            // hidden (display: none) until the page's own tab-restore
            // script (categories/show.blade.php) switches to it, so the
            // modal has to wait for that to actually happen before it can
            // render visibly.
            // Re-run after every in-place refresh of this pane, since the
            // elements it binds to are replaced by the swap.
            function initSchedulePane() {
            roomsRegisteredInModal = false;
            document.querySelectorAll('[data-room-assigner]').forEach(refreshAssigner);

            // A span save that failed validation comes back with its
            // values and messages; reopen Add Date so they're visible.
            var spanModal = document.querySelector('#add-date-span-modal[data-span-errors]');
            if (spanModal && window.bootstrap) {
                var openSpanModal = function () {
                    bootstrap.Modal.getOrCreateInstance(spanModal).show();
                };
                var spanPane = document.getElementById('tab-schedules');
                var spanTrigger = document.querySelector('[data-bs-target="#tab-schedules"]');
                if (spanPane && spanPane.classList.contains('active')) {
                    openSpanModal();
                } else if (spanTrigger) {
                    spanTrigger.addEventListener('shown.bs.tab', function handler() {
                        spanTrigger.removeEventListener('shown.bs.tab', handler);
                        openSpanModal();
                    });
                }
            }

            var createdModal = document.getElementById('dates-created-modal');
            if (createdModal && window.bootstrap) {
                // "Yes" keeps the Saturday/Sunday dates (they are already
                // saved) and moves on to room assignment.
                var includeWeekends = createdModal.querySelector('[data-weekend-include]');
                if (includeWeekends) {
                    includeWeekends.addEventListener('click', function () {
                        createdModal.querySelector('[data-weekend-prompt]').remove();
                        createdModal.querySelector('[data-created-body]').classList.remove('d-none');
                    });
                }

                createdModal.addEventListener('hidden.bs.modal', function () {
                    if (roomsRegisteredInModal) {
                        roomsRegisteredInModal = false;
                        window.categorySoftRefresh(['tab-schedules']);
                    }
                });

                var openCreatedModal = function () {
                    bootstrap.Modal.getOrCreateInstance(createdModal).show();
                };

                var schedulesPane = document.getElementById('tab-schedules');
                var schedulesTrigger = document.querySelector('[data-bs-target="#tab-schedules"]');

                if (schedulesPane && schedulesPane.classList.contains('active')) {
                    openCreatedModal();
                } else if (schedulesTrigger) {
                    schedulesTrigger.addEventListener('shown.bs.tab', function handler() {
                        schedulesTrigger.removeEventListener('shown.bs.tab', handler);
                        openCreatedModal();
                    });
                } else {
                    openCreatedModal();
                }
            }
            }

            initSchedulePane();
            document.addEventListener('category:soft-refreshed', initSchedulePane);
        })();
    </script>
@endpush
