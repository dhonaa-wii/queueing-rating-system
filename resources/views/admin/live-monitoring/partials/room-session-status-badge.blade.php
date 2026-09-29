@php
    $roomSessionBadgeClass = match ($status?->code) {
        'ACTIVE' => 'badge-success-tint',
        'WAITING', 'AVAILABLE' => 'badge-info-tint',
        'PAUSED' => 'badge-danger-tint',
        'BREAK' => 'badge-brand-tint',
        'PENDING_CLOSURE' => 'badge-brand-tint',
        'FINISHED', 'CLOSED' => 'badge-muted-tint',
        default => 'badge-muted-tint',
    };
@endphp
<span class="badge {{ $roomSessionBadgeClass }}">{{ $status->name ?? 'Not started' }}</span>
