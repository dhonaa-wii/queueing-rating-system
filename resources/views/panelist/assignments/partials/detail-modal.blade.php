{{--
    Group-data view for one assignment, shared by the Scheduled and Deferred
    tabs. Expects: $assignment, $isPending, $canMark, $expectedAt (the
    running day's forecast for this row, or null).

    Completed rows deliberately do not render this — their View button opens
    the evaluation sheet instead (admin.partials.evaluation-sheet-modal).
--}}
@php
    $attempt = $assignment->presentationAttempt;
    $group = $attempt->researchGroup;
    $category = $group->category;
    $schedule = $attempt->attemptSchedule;
    $room = $schedule?->presentationDateRoom;
    $queueEntry = $schedule?->queueEntry;
    $deferAdjustment = $queueEntry?->removed_at ? $queueEntry->latestDeferAdjustment() : null;
    // Parked on a day that can no longer run: the plan, the room and the
    // queue number this row still carries all belong to that day, so none of
    // them is shown (AttemptSchedule::isAwaitingReschedule()).
    $isAwaiting = (bool) $schedule?->isAwaitingReschedule($attempt);
@endphp

<div class="modal fade" id="view-modal-{{ $assignment->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $group->group_reference }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="detail-list small mb-0">
                    <dt>Category</dt>
                    <dd>
                        {{ $category->name }}
                        <div class="text-brand-muted">
                            {{ $category->academicYear->name ?? 'N/A' }} &middot;
                            {{ $category->semester->name ?? 'N/A' }} &middot;
                            {{ $category->college->name ?? 'N/A' }}
                        </div>
                    </dd>

                    <dt>Group Leader</dt>
                    <dd>{{ $group->leader()?->full_name ?? 'N/A' }}</dd>

                    <dt>Members</dt>
                    <dd>
                        @php $members = $group->students->where('is_leader', false); @endphp
                        @forelse ($members as $member)
                            <div>{{ $member->full_name }}{{ $member->section_name ? ' (' . $member->section_name . ')' : '' }}</div>
                        @empty
                            <span class="text-brand-muted">None</span>
                        @endforelse
                    </dd>

                    @if ($group->current_project_title)
                        <dt>Project Title</dt>
                        <dd>{{ $group->current_project_title }}</dd>
                    @elseif ($group->proposedTitles->isNotEmpty())
                        <dt>Proposed Titles</dt>
                        <dd>
                            <ol class="mb-0 ps-3">
                                @foreach ($group->proposedTitles as $title)
                                    <li>{{ $title->title_text }}</li>
                                @endforeach
                            </ol>
                        </dd>
                    @endif

                    @if ($group->technical_adviser_name)
                        <dt>Technical Adviser</dt>
                        <dd>{{ $group->technical_adviser_name }}</dd>
                    @endif

                    <dt>Room</dt>
                    <dd>{{ $isAwaiting ? \App\Models\AttemptSchedule::AWAITING_ROOM : ($room->room_name ?? 'Not scheduled yet') }}</dd>

                    @if ($deferAdjustment || $queueEntry?->removed_at)
                        <dt>Deferred At</dt>
                        <dd>{{ ($deferAdjustment?->adjusted_at ?? $queueEntry->removed_at)->format('l, M j, Y \a\t g:i A') }}</dd>

                        <dt>Reason</dt>
                        <dd>
                            {{ $deferAdjustment?->reason?->name ?? 'N/A' }}
                            @if ($deferAdjustment?->remarks)
                                <div class="text-brand-muted">{{ $deferAdjustment->remarks }}</div>
                            @endif
                        </dd>
                    @elseif ($isAwaiting)
                        <dt>Presentation Date &amp; Time</dt>
                        <dd>{{ \App\Models\AttemptSchedule::AWAITING_DATE }}</dd>
                    @else
                        <dt>Date</dt>
                        <dd>{{ $room?->presentationDate?->presentation_date?->format('l, M j, Y') ?? '—' }}</dd>

                        {{-- Only worth its own row once the running day has actually
                        pushed this group off its plan; otherwise it just repeats
                        the Planned Time below. --}}
                        @if ($expectedAt && ! $expectedAt->equalTo($schedule?->planned_start_at))
                            <dt>Expected Time</dt>
                            <dd>{{ $expectedAt->format('g:i A') }}</dd>
                        @endif

                        <dt>Planned Time</dt>
                        <dd>
                            @if ($schedule)
                                Call: {{ $schedule->planned_call_at?->format('g:i A') ?? '—' }}
                                &middot; Start: {{ $schedule->planned_start_at?->format('g:i A') ?? '—' }}
                                &middot; End: {{ $schedule->planned_end_at?->format('g:i A') ?? '—' }}
                                @if ($schedule->adjusted_expected_at)
                                    <div class="text-brand-muted">Adjusted expected time: {{ $schedule->adjusted_expected_at->format('g:i A') }}</div>
                                @endif
                            @else
                                Not scheduled yet
                            @endif
                        </dd>

                        <dt>Queue Number</dt>
                        <dd>
                            @if ($queueEntry && ! $queueEntry->removed_at)
                                #{{ $queueEntry->queue_number }}
                            @else
                                Not queued yet
                            @endif
                        </dd>
                    @endif

                    <dt>Your Role</dt>
                    <dd>
                        <span class="badge {{ $assignment->roleBadgeClass() }}">{{ $assignment->roleLabel() }}</span>
                        <span class="badge badge-muted-tint">{{ $assignment->assignmentStatus->name }}</span>
                    </dd>

                    <dt>Presentation Status</dt>
                    <dd class="mb-0">{{ $attempt->presentationStatus->name }}</dd>
                </dl>

                @if ($isPending)
                    <hr style="border-color: var(--brand-border);">
                    <p class="small text-brand-muted mb-0">An unavailability request is already pending review for this assignment.</p>
                @endif
            </div>
            <div class="modal-footer">
                @if ($canMark)
                    <span @if ($isAwaiting) title="Unavailable until this group has a schedule and room." @endif>
                        <button type="button" class="btn btn-outline-danger-brand" data-bs-dismiss="modal"
                                data-bs-toggle="modal" data-bs-target="#unavailable-modal-{{ $assignment->id }}"
                                @disabled($isAwaiting)>
                            <x-icon name="user-x" /> Mark Unavailable
                        </button>
                    </span>
                @endif
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if ($canMark)
    <div class="modal fade" id="unavailable-modal-{{ $assignment->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('panelist.assignments.unavailable', $assignment) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Mark Unavailable — {{ $group->group_reference }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-brand-muted small">
                            This sends an unavailability request to the Administrator for review. They'll
                            arrange a substitute — you don't need to name one.
                        </p>
                        <div class="mb-0">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required maxlength="1000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="send" /> Send Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
