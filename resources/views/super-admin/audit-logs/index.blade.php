@extends('layouts.super-admin')

@section('title', 'Audit Log')

@push('styles')
    <style>
        .al-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
        .al-filters { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .5rem; padding: .85rem 1rem; margin-bottom: 1rem; align-items: end; }
        .al-filters .al-search { grid-column: span 2; }
        .al-filters label { font-size: var(--page-fs-xs); color: var(--brand-text-muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .2rem; }
        .al-table th { font-size: var(--page-fs-xs); text-transform: uppercase; letter-spacing: .04em; color: var(--brand-text-muted); font-weight: 600; white-space: nowrap; }
        .al-table td { vertical-align: top; font-size: var(--page-fs-sm); }
        .al-action { font-weight: 600; }
        .al-details summary { cursor: pointer; color: var(--brand-accent); font-size: var(--page-fs-xs); list-style: none; }
        .al-details summary::-webkit-details-marker { display: none; }
        .al-details[open] summary { margin-bottom: .4rem; }
        .al-values { margin: 0; padding: .5rem .65rem; border-radius: .4rem; background: var(--brand-surface); font-size: var(--page-fs-xs); white-space: pre-wrap; word-break: break-word; }
        .al-values + .al-values { margin-top: .35rem; }
        .al-values-label { display: block; font-weight: 600; color: var(--brand-text-muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .15rem; }
        @media (max-width: 991.98px) { .al-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .al-filters .al-search { grid-column: span 2; } .al-table { min-width: 42rem; } }
    </style>
@endpush

@section('content')
    @php
        $actorName = fn ($log) => $log->user?->profile?->first_name
            ? $log->user->profile->first_name.' '.$log->user->profile->last_name
            : ($log->user?->username ?? 'System');
        $hasFilters = collect($filters)->filter()->isNotEmpty();
    @endphp

    <div class="page-shell">
        <div class="al-header">
            <div>
                <h2 class="h4 mb-0">Audit Log</h2>
                <div class="text-brand-muted" style="font-size: var(--page-fs-sm);">
                    {{ number_format($logs->total()) }} {{ $hasFilters ? 'matching' : '' }} of {{ number_format($total) }} entries
                </div>
            </div>
            <a href="{{ route('super-admin.audit-logs.export', array_filter($filters)) }}" class="btn btn-outline-brand"><x-icon name="download" /> Export CSV</a>
        </div>

        <form method="GET" action="{{ route('super-admin.audit-logs.index') }}" class="card-brand al-filters">
            <div class="al-search">
                <label for="al-q">Search</label>
                <input type="search" name="q" id="al-q" value="{{ $filters['q'] }}" maxlength="100" class="form-control form-control-sm">
            </div>
            <div>
                <label for="al-user">User</label>
                <select name="user" id="al-user" class="form-select form-select-sm">
                    <option value="">Anyone</option>
                    <option value="system" @selected($filters['user'] === 'system')>System</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string) $filters['user'] === (string) $actor->id)>{{ $actor->username }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="al-action">Action</label>
                <select name="action" id="al-action" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ Str::of($action)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="al-entity">Entity</label>
                <select name="entity" id="al-entity" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($entities as $type => $label)
                        <option value="{{ $type }}" @selected($filters['entity'] === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="al-from">From</label>
                <input type="date" name="from" id="al-from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
            </div>
            <div>
                <label for="al-to">To</label>
                <input type="date" name="to" id="al-to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
            </div>
            <div class="d-flex gap-2" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-brand btn-sm"><x-icon name="search" /> Filter</button>
                @if ($hasFilters)
                    <a href="{{ route('super-admin.audit-logs.index') }}" class="btn btn-outline-brand btn-sm"><x-icon name="x" /> Clear</a>
                @endif
            </div>
        </form>

        <div class="card-brand p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table al-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th class="pe-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    {{ $log->created_at->format('M j, Y') }}
                                    <div class="text-brand-muted" style="font-size: var(--page-fs-xs);">{{ $log->created_at->format('g:i:s A') }}</div>
                                </td>
                                <td>{{ $actorName($log) }}</td>
                                <td class="al-action">{{ Str::of($log->action)->replace('_', ' ')->title() }}</td>
                                <td class="text-brand-muted">{{ class_basename($log->entity_type) }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                                <td class="pe-3">
                                    @if ($log->old_values || $log->new_values || $log->ip_address)
                                        <details class="al-details">
                                            <summary>View</summary>
                                            @if ($log->old_values)
                                                <pre class="al-values"><span class="al-values-label">Before</span>{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                            @if ($log->new_values)
                                                <pre class="al-values"><span class="al-values-label">After</span>{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                            @if ($log->ip_address)
                                                <div class="text-brand-muted mt-1" style="font-size: var(--page-fs-xs);">{{ $log->ip_address }}</div>
                                            @endif
                                        </details>
                                    @else
                                        <span class="text-brand-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-brand-muted py-4">{{ $hasFilters ? 'No entries match these filters.' : 'No audit entries yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $logs->links() }}</div>
    </div>
@endsection
