@php
    $presentationStatusBadgeClass = match ($status?->code) {
        'ONGOING' => 'badge-success-tint',
        'PAUSED' => 'badge-danger-tint',
        'CALLED', 'READY_NEXT' => 'badge-brand-tint',
        'SCHEDULED', 'QUEUED', 'DEFERRED', 'RESCHEDULED' => 'badge-info-tint',
        'COMPLETED' => 'badge-success-tint',
        'ABSENT', 'CANCELLED' => 'badge-danger-tint',
        default => 'badge-muted-tint',
    };
@endphp
<span class="badge {{ $presentationStatusBadgeClass }}">{{ $status->name ?? 'Unknown' }}</span>
