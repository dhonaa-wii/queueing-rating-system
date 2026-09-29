{{--
    Extracted from substitution-modal.blade.php so the exact same markup can
    be reused client-side (workspace-scripts-style innerHTML swap) the
    moment an "unassigned" request is submitted, without a second server
    round trip just to render the "waiting" state.
--}}
@if ($assignmentClassification['kind'] === 'unassigned' && $pendingSubstitutionRequest)
    <div class="text-center py-3" data-substitution-waiting>
        <div class="spinner-border spinner-border-sm text-brand-accent mb-2" role="status" aria-hidden="true"></div>
        <p class="mb-0">Waiting for an Administrator to review your request&hellip;</p>
    </div>
@elseif ($replaceableCandidates->isEmpty())
    <p class="text-brand-muted mb-0">No open assigned seats to replace right now.</p>
@else
    <p class="text-brand-muted small">
        {{ $assignmentClassification['kind'] === 'backup'
            ? "Select which assigned panelist you're filling in for."
            : "Select which assigned panelist you're requesting to replace — an Administrator will need to confirm." }}
    </p>
    <form id="substitution-form">
        @csrf
        <select name="original_panelist_id" class="form-select mb-3" required>
            <option value="" disabled selected>Choose a panelist&hellip;</option>
            @foreach ($replaceableCandidates as $candidate)
                <option value="{{ $candidate->panelist_user_id }}">
                    {{ trim(($candidate->panelist->profile->first_name ?? '') . ' ' . ($candidate->panelist->profile->last_name ?? '')) ?: $candidate->panelist->username }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-brand w-100">
            {{ $assignmentClassification['kind'] === 'backup' ? 'Confirm Replacement' : 'Request Replacement' }}
        </button>
    </form>
@endif
