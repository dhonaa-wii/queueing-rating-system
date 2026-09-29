@php
    $eventDateBadgeClass = ($overdue ?? false) ? 'badge-danger-tint' : match ($status?->code) {
        'ACTIVE' => 'badge-success-tint',
        'STANDBY' => 'badge-brand-tint',
        'COMPLETED' => 'badge-info-tint',
        'CANCELLED' => 'badge-danger-tint',
        default => 'badge-muted-tint', // PLANNED, or unknown
    };
    $eventDateBadgeLabel = ($overdue ?? false) ? 'Overdue' : ($status->name ?? 'Unknown');
@endphp
<span class="badge {{ $eventDateBadgeClass }}">{{ $eventDateBadgeLabel }}</span>
