@php
    use App\Models\Backup;
    $lastRun = $maintenanceSettings->last_run;
@endphp

<div class="row g-3">
    <div class="col-lg-6">
        {{-- Maintenance mode --}}
        <div class="card-brand p-3 mb-3 {{ $maintenanceState ? 'bk-mode-on' : '' }}">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <h3 class="h6 mb-0">Maintenance Mode</h3>
                <span class="badge {{ $maintenanceState ? 'badge-brand-tint' : 'badge-muted-tint' }}">{{ $maintenanceState ? 'On' : 'Off' }}</span>
            </div>

            <form method="POST" action="{{ route('super-admin.maintenance.mode') }}">
                @csrf
                @if ($maintenanceState)
                    <input type="hidden" name="enabled" value="0">
                    <p class="mb-3" style="font-size: var(--page-fs-sm);">
                        On since {{ now()->parse($maintenanceState['since'])->format('M j, Y · g:i A') }}@if ($maintenanceState['by']) by {{ $maintenanceState['by'] }}@endif.
                        @if ($maintenanceState['message'])<br><span class="text-brand-muted">“{{ $maintenanceState['message'] }}”</span>@endif
                    </p>
                    <button type="submit" class="btn btn-brand btn-sm"><x-icon name="power" /> Turn Off</button>
                @else
                    <input type="hidden" name="enabled" value="1">
                    <div class="mb-2">
                        <label for="mt-message" class="form-label">Message to visitors</label>
                        <input type="text" name="message" id="mt-message" maxlength="300" class="form-control form-control-sm" placeholder="The system is down for maintenance and will be back shortly.">
                    </div>
                    <button type="submit" class="btn btn-outline-danger-brand btn-sm"><x-icon name="power" /> Turn On</button>
                @endif
            </form>
        </div>

        {{-- Cleanup --}}
        <div class="card-brand p-0 overflow-hidden">
            <div class="d-flex justify-content-between align-items-center p-3 pb-2">
                <h3 class="h6 mb-0">Routine Cleanup</h3>
                <form method="POST" action="{{ route('super-admin.maintenance.run') }}">
                    @csrf
                    <button type="submit" class="btn btn-brand btn-sm"><x-icon name="play" /> Run Now</button>
                </form>
            </div>
            <table class="table mb-0 align-middle" style="font-size: var(--page-fs-sm);">
                <tbody>
                    @foreach ($cleanupPreview as $item)
                        <tr>
                            <td class="ps-3">{{ $item['label'] }}</td>
                            <td class="bk-count pe-3 {{ $item['count'] ? 'fw-semibold' : 'text-brand-muted' }}">{{ number_format($item['count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-3 pt-2 text-brand-muted" style="font-size: var(--page-fs-xs);">
                @if ($maintenanceSettings->last_run_at)
                    Last run {{ $maintenanceSettings->last_run_at->diffForHumans() }}:
                    {{ number_format(collect($lastRun['items'] ?? [])->sum('count')) }} removed.
                @else
                    Has not run yet.
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-brand p-3 mb-3">
            <h3 class="h6 mb-3">Logs &amp; Audit Retention</h3>

            <form method="POST" action="{{ route('super-admin.maintenance.settings') }}">
                @csrf
                @method('PUT')

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label for="mt-log-max" class="form-label">Rotate log at (MB)</label>
                        <input type="number" name="log_max_mb" id="mt-log-max" min="1" max="500" required
                               class="form-control form-control-sm @error('log_max_mb') is-invalid @enderror" value="{{ old('log_max_mb', $maintenanceSettings->log_max_mb) }}">
                        @error('log_max_mb') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label for="mt-log-days" class="form-label">Keep old logs (days)</label>
                        <input type="number" name="log_keep_days" id="mt-log-days" min="1" max="365" required
                               class="form-control form-control-sm @error('log_keep_days') is-invalid @enderror" value="{{ old('log_keep_days', $maintenanceSettings->log_keep_days) }}">
                        @error('log_keep_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-2">
                    <label for="mt-audit" class="form-label">Keep audit entries (days)</label>
                    <input type="number" name="audit_retention_days" id="mt-audit" min="30" max="3650" placeholder="Forever"
                           class="form-control form-control-sm @error('audit_retention_days') is-invalid @enderror" value="{{ old('audit_retention_days', $maintenanceSettings->audit_retention_days) }}">
                    @error('audit_retention_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-brand btn-sm mt-1"><x-icon name="save" /> Save Settings</button>
            </form>
        </div>

        <div class="card-brand p-3">
            <div class="row g-3">
                <div class="col-6">
                    <div class="bk-stat-label">Application Log</div>
                    <div class="bk-stat-value">{{ Backup::formatBytes($logStatus['size']) }}</div>
                    <div class="bk-stat-sub">limit {{ $maintenanceSettings->log_max_mb }} MB</div>
                </div>
                <div class="col-6">
                    <div class="bk-stat-label">Rotated Logs</div>
                    <div class="bk-stat-value">{{ $logStatus['rotated_count'] }}</div>
                    <div class="bk-stat-sub">{{ Backup::formatBytes($logStatus['rotated_bytes']) }}</div>
                </div>
            </div>
            <hr class="my-3">
            <a href="{{ route('super-admin.audit-logs.index') }}" class="btn btn-outline-brand btn-sm"><x-icon name="list-check" /> Open Audit Log</a>
        </div>
    </div>
</div>
