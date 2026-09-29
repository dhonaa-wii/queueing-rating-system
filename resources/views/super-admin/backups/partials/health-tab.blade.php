@php
    $summary = match ($health['overall']) {
        'problem' => ['Needs attention', 'Something below will stop backups or the site from working properly.'],
        'warning' => ['Mostly fine', 'Nothing is broken, but a few things deserve a look.'],
        default => ['Healthy', 'Everything checked is in order.'],
    };
@endphp

<div class="card-brand bk-health-summary">
    <span class="bk-dot is-{{ $health['overall'] }}"></span>
    <div>
        <div class="fw-semibold">{{ $summary[0] }}</div>
        <div class="text-brand-muted" style="font-size: var(--page-fs-sm);">{{ $summary[1] }}</div>
    </div>
</div>

<div class="card-brand p-0 overflow-hidden">
    @foreach ($health['checks'] as $check)
        <div class="bk-check">
            <span class="bk-dot is-{{ $check['status'] }}"></span>
            <div>
                <div class="bk-check-label">{{ $check['label'] }}</div>
                <div class="bk-check-detail">{{ $check['detail'] }}</div>
            </div>
        </div>
    @endforeach
</div>
