{{--
    assignmentClassification/replaceableCandidates/pendingSubstitutionRequest
    are computed fresh in RoomSessionController::buildHomeData() — see
    room-session.partials.substitution-modal for the backup/unassigned
    picker this feeds, and home.blade.php's scripts stack for the loading
    overlay + reveal logic that reads the data-* attributes below.
--}}
<div id="assignment-check-overlay" class="assignment-check-overlay d-none">
    <div class="spinner-border text-brand-accent" role="status" aria-hidden="true"></div>
    <p class="small text-brand-muted mt-2 mb-0">Verifying panel assignment&hellip;</p>
</div>

<div id="connection-status-content"
     data-connection-id="{{ $connection->id }}"
     data-classification-kind="{{ $assignmentClassification['kind'] ?? '' }}">
    <div class="text-center">
        <span class="badge badge-success-tint mb-2">Connected</span>
        <h2 class="rs-panelist-name mb-1">{{ trim(($connection->panelist->profile->first_name ?? '') . ' ' . ($connection->panelist->profile->last_name ?? '')) ?: $connection->panelist->username }}</h2>
        <p class="rs-panelist-since text-brand-muted mb-2">Connected since {{ $connection->connected_at->format('g:i A') }}</p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            @if ($canEvaluate)
                <span class="badge badge-brand-tint">Can Evaluate</span>
            @endif
            @if ($canControlFlow)
                <span class="badge badge-brand-tint">Flow Control (Chair)</span>
            @endif
            @if (($assignmentClassification['kind'] ?? null) === 'backup')
                <span class="badge badge-brand-tint">Alternate Panel</span>
            @elseif (($assignmentClassification['kind'] ?? null) === 'unassigned')
                <span class="badge badge-danger-tint">Not Assigned</span>
            @endif
        </div>
    </div>
</div>
