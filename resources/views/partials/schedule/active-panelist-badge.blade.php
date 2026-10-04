{{--
    Badge for one panelist currently logged into a terminal in this room
    (student-schedule-only, kept separate from room-session's own
    panelist-badge.blade.php per user direction 2026-08-22). Expects
    $connection (a TerminalConnection, with panelist.profile and
    roomTerminal.terminalType eager-loaded).
--}}
@php
    $panelistName = trim(($connection->panelist->profile->first_name ?? '') . ' ' . ($connection->panelist->profile->last_name ?? ''));
    $isLead = ($connection->roomTerminal->terminalType->code ?? null) === 'LEAD';
@endphp
<span class="badge {{ $isLead ? 'badge-brand-tint' : 'badge-success-tint' }} mb-1 d-inline-block" style="font-size: 0.72rem;">
    {{ $panelistName }}{{ $isLead ? ' · Lead' : '' }}
</span>
