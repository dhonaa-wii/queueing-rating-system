{{--
    Shared by admin/dashboard.blade.php's shortcut card and Group & Panel
    Assignment's needs-attention list (admin/panel-assignments/partials/
    needs-attention.blade.php) — one row per PENDING PanelSubstitutionRequest,
    same visual language as needs-attention's existing deferred-group rows.
    Expects $substitutionRequest with originalPanelist.profile,
    requestedSubstitute.profile, presentationAttempt.researchGroup.category
    eager-loaded.
--}}
@php
    $attempt = $substitutionRequest->presentationAttempt;
    $group = $attempt->researchGroup;
    $original = $substitutionRequest->originalPanelist;
    $substitute = $substitutionRequest->requestedSubstitute;
    $displayName = fn ($user) => $user ? (trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')) ?: $user->username) : 'Someone';
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3" style="background: var(--brand-surface-alt); border-radius: .5rem;">
    <div>
        <div>
            <strong>{{ $displayName($substitute) }}</strong>
            <span class="text-brand-muted">is requesting to replace</span>
            <strong>{{ $displayName($original) }}</strong>
        </div>
        <div class="small text-brand-muted">
            {{ $group->group_reference }} &middot; {{ $group->category->name ?? 'Unknown category' }}
            &middot; requested {{ $substitutionRequest->created_at?->diffForHumans() }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.panel-substitutions.approve', $substitutionRequest) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-success-brand"><x-icon name="check" /> Confirm</button>
        </form>
        <button type="button" class="btn btn-sm btn-outline-danger-brand" data-bs-toggle="modal" data-bs-target="#reject-substitution-modal-{{ $substitutionRequest->id }}"><x-icon name="x" /> Reject</button>
    </div>
</div>

<div class="modal fade" id="reject-substitution-modal-{{ $substitutionRequest->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.panel-substitutions.reject', $substitutionRequest) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Substitution Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        This will log {{ $displayName($substitute) }} out of the terminal they're
                        currently connected to. They'll need to identify themselves again if they
                        return.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="x" /> Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
