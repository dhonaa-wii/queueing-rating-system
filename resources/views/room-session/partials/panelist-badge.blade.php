@php
    $isBackup = $isBackup ?? false;
    $panelistName = trim(($assignment->panelist->profile->first_name ?? '') . ' ' . ($assignment->panelist->profile->last_name ?? ''));
    $isActiveConnection = $connectedPanelistIds->contains($assignment->panelist_user_id);
    $badgeClass = $isBackup ? 'badge-brand-tint' : ($isActiveConnection ? 'badge-success-tint' : 'badge-muted-tint');
    $prefix = $isBackup
        ? 'Alternate Panel: '
        : ($assignment->is_lead ? 'Chair: ' : ($assignment->memberNumber() ? 'Member ' . $assignment->memberNumber() . ': ' : ''));
@endphp
<span class="badge {{ $badgeClass }} mb-1 d-inline-block" style="font-size: 0.62rem;">
    {{ $prefix . $panelistName }}{{ $isActiveConnection ? ' · Active' : '' }}
</span>
