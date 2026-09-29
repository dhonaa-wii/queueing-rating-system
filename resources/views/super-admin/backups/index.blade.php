@extends('layouts.super-admin')

@section('title', 'Backup & Maintenance')

@push('styles')
    <style>
        .bk-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .75rem; }
        .bk-header-actions { display: flex; flex-wrap: wrap; gap: .5rem; }

        .bk-tabs { display: flex; gap: .25rem; border-bottom: 1px solid var(--brand-border); margin-bottom: 1rem; overflow-x: auto; }
        .bk-tab { position: relative; display: inline-flex; align-items: center; gap: .4rem; padding: .5rem .85rem; border: 0; background: none; color: var(--brand-text-muted); font-size: var(--page-fs); font-weight: 500; white-space: nowrap; cursor: pointer; }
        .bk-tab::after { content: ''; position: absolute; left: 50%; right: 50%; bottom: -1px; height: 2px; background: var(--brand-accent); transition: left .2s ease, right .2s ease; }
        .bk-tab:hover { color: var(--brand-text); }
        .bk-tab.is-active { color: var(--brand-text); }
        .bk-tab.is-active::after { left: .5rem; right: .5rem; }
        .bk-dot { width: .5rem; height: .5rem; border-radius: 50%; background: var(--brand-success); }
        .bk-dot.is-warning { background: var(--brand-accent); }
        .bk-dot.is-problem { background: var(--brand-danger); }
        .bk-pane { display: none; }
        .bk-pane.is-active { display: block; }

        .bk-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .bk-stat { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; }
        .bk-stat-ico { flex: none; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .6rem; background: var(--brand-accent-tint); color: var(--brand-accent); }
        .bk-stat-ico svg { width: 1.1rem; height: 1.1rem; }
        .bk-stat-label { font-size: var(--page-fs-xs); color: var(--brand-text-muted); text-transform: uppercase; letter-spacing: .04em; }
        .bk-stat-value { font-size: var(--page-fs-heading); font-weight: 600; line-height: 1.2; }
        .bk-stat-sub { font-size: var(--page-fs-xs); color: var(--brand-text-muted); }
        .bk-progress { display: none; margin-bottom: 1rem; padding: .85rem 1rem; }
        .bk-progress.is-active { display: block; }
        .bk-progress .progress, .bk-modal-progress .progress { height: .5rem; background: var(--brand-surface); border-radius: 999px; }
        .bk-progress .progress-bar, .bk-modal-progress .progress-bar { background: var(--brand-accent); transition: width .4s ease; }
        .bk-table th { font-size: var(--page-fs-xs); text-transform: uppercase; letter-spacing: .04em; color: var(--brand-text-muted); font-weight: 600; white-space: nowrap; }
        .bk-table td { vertical-align: middle; }
        .bk-name { min-width: 14rem; }
        .bk-file { font-weight: 500; overflow-wrap: anywhere; }
        .bk-lock { width: .9rem; height: .9rem; color: var(--brand-text-muted); vertical-align: -.1em; }

        .bk-health-summary { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; margin-bottom: 1rem; }
        .bk-health-summary .bk-dot { width: .75rem; height: .75rem; }
        .bk-check { display: flex; align-items: flex-start; gap: .75rem; padding: .7rem 1rem; border-top: 1px solid var(--brand-border); }
        .bk-check:first-child { border-top: 0; }
        .bk-check .bk-dot { margin-top: .4rem; flex: none; }
        .bk-check-label { font-weight: 600; font-size: var(--page-fs); }
        .bk-check-detail { color: var(--brand-text-muted); font-size: var(--page-fs-sm); }

        .bk-count { font-variant-numeric: tabular-nums; text-align: right; }
        .bk-mode-on { border-left: 3px solid var(--brand-accent); }
        .bk-callout { padding: .6rem .8rem; border-radius: .5rem; background: var(--brand-danger-tint); color: var(--brand-danger); font-size: var(--page-fs-sm); }

        @media (max-width: 767.98px) { .bk-stats { grid-template-columns: 1fr; } .bk-table { min-width: 34rem; } }
    </style>
@endpush

@section('content')
    @php
        use App\Models\Backup;
    @endphp
    <div class="page-shell">
        <div class="bk-header">
            <h2 class="h4 mb-0">Backup &amp; Maintenance</h2>
            <div class="bk-header-actions">
                <button type="button" class="btn btn-outline-brand" data-bs-toggle="modal" data-bs-target="#upload-modal"><x-icon name="file-plus" /> Upload Backup</button>
                <button type="button" class="btn btn-brand" id="bk-start" @disabled($running)><x-icon name="database" /> Back Up Now</button>
            </div>
        </div>

        <div class="bk-tabs" role="tablist">
            <button type="button" class="bk-tab is-active" data-bk-tab="backups" role="tab">Backups</button>
            <button type="button" class="bk-tab" data-bk-tab="health" role="tab">
                Health <span class="bk-dot is-{{ $health['overall'] }}" title="{{ ucfirst($health['overall']) }}"></span>
            </button>
            <button type="button" class="bk-tab" data-bk-tab="maintenance" role="tab">Maintenance</button>
        </div>

        <div class="bk-pane is-active" id="tab-backups">
            @include('super-admin.backups.partials.backups-tab')
        </div>
        <div class="bk-pane" id="tab-health">
            @include('super-admin.backups.partials.health-tab')
        </div>
        <div class="bk-pane" id="tab-maintenance">
            @include('super-admin.backups.partials.maintenance-tab')
        </div>
    </div>

    @include('super-admin.backups.partials.restore-modal')
    @include('super-admin.backups.partials.upload-modal')
    @include('admin.partials.confirm-action-modal')
@endsection

@push('scripts')
    @include('super-admin.backups.partials.scripts')
@endpush
