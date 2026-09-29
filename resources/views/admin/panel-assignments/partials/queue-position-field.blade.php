{{--
    Shared queue-position picker for the Move and Reinsert modals: the room's
    current order as it stands, plus a position input bounded so a group can
    never be given (or placed ahead of) the number of one that has already
    presented. QueueAdjustmentService::firstOpenPosition() enforces the same
    floor server-side; this is what makes the rule visible rather than a
    silent clamp.

    Expects:
      $rows             — the room's active attempts, in queue order
      $roomName
      $minPosition      — first position not already spoken for
      $maxPosition      — last selectable position
      $defaultPosition  — what the input starts on
      $lockedThrough    — highest already-presented position (0 when none)
      $currentAttemptId — the attempt being moved, marked in the list (Move
                          only; null for Reinsert, which isn't in the list yet)

    A pending re-defense is held at the end of its room by
    QueueAdjustmentService::applyOrder(), so the ceiling drops below it here
    too — offering a number that the group could not actually be given would
    only look like the save had failed.
--}}
@php
    $pinnedLastCount = $rows->filter(
        fn ($queued) => $queued->attemptType?->code === 'RE_DEFENSE' && ! $queued->presentationStatus?->is_terminal
    )->count();

    $maxPosition = max($minPosition, $maxPosition - $pinnedLastCount);
    $defaultPosition = min(max($defaultPosition, $minPosition), $maxPosition);
@endphp
<div class="mb-3">
    <label class="form-label">Position in {{ $roomName }}'s queue</label>

    <div class="reinsert-order mb-2">
        <table class="table table-sm align-middle small mb-0">
            <tbody>
                @forelse ($rows as $queued)
                    @php
                        $queuedStatus = $queued->presentationStatus;
                        $queuedLocked = $queuedStatus?->is_terminal || in_array($queuedStatus?->code, ['ONGOING', 'PAUSED'], true);
                        $isSelf = $currentAttemptId !== null && $queued->id === $currentAttemptId;
                        $isPinnedLast = $queued->attemptType?->code === 'RE_DEFENSE' && ! $queuedLocked;
                    @endphp
                    <tr class="{{ $queuedLocked ? 'text-brand-muted' : '' }}">
                        <td class="ps-0" style="width: 2.5rem;">{{ $queued->attemptSchedule->queueEntry->queue_number }}</td>
                        <td>
                            {{ $queued->researchGroup->group_reference }}
                            @if ($isSelf)
                                <span class="badge badge-brand-tint ms-1">Moving</span>
                            @endif
                            @if ($isPinnedLast)
                                <span class="badge badge-info-tint ms-1">Re-Defense &middot; always last</span>
                            @endif
                        </td>
                        <td class="text-end pe-0">
                            <span class="badge {{ $queuedLocked ? 'badge-muted-tint' : 'badge-info-tint' }}">{{ $queuedStatus?->name }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td class="ps-0 text-brand-muted">No groups in this room's queue yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <input type="number" name="position" class="form-control"
           min="{{ $minPosition }}" max="{{ $maxPosition }}" value="{{ $defaultPosition }}" required>
    <p class="text-brand-muted small mb-0 mt-1">
        {{ $minPosition }}&ndash;{{ $maxPosition }} available{{ $lockedThrough > 0 ? ' — 1–' . $lockedThrough . ' already presented or presenting' : '' }}.
    </p>
</div>
