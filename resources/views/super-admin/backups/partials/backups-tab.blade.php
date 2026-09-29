@php
    use App\Models\Backup;
@endphp

<div class="bk-stats">
    <div class="card-brand bk-stat">
        <span class="bk-stat-ico"><x-icon name="clock" /></span>
        <div>
            <div class="bk-stat-label">Last Backup</div>
            <div class="bk-stat-value">{{ $lastCompleted?->completed_at?->diffForHumans() ?? 'Never' }}</div>
            @if ($lastCompleted)
                <div class="bk-stat-sub">{{ $lastCompleted->completed_at->format('M j, Y · g:i A') }}</div>
            @endif
        </div>
    </div>
    <div class="card-brand bk-stat">
        <span class="bk-stat-ico"><x-icon name="database" /></span>
        <div>
            <div class="bk-stat-label">Stored Backups</div>
            <div class="bk-stat-value">{{ $storedCount }}</div>
            <div class="bk-stat-sub">{{ Backup::formatBytes((int) $storedBytes) }} used</div>
        </div>
    </div>
    <div class="card-brand bk-stat">
        <span class="bk-stat-ico"><x-icon name="save" /></span>
        <div>
            <div class="bk-stat-label">Free Disk Space</div>
            <div class="bk-stat-value">{{ Backup::formatBytes($freeBytes ? (int) $freeBytes : null) }}</div>
        </div>
    </div>
</div>

<div class="card-brand bk-progress {{ $running ? 'is-active' : '' }}" id="bk-progress"
     @if ($running) data-step-url="{{ route('super-admin.backups.step', $running) }}" @endif>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong style="font-size: var(--page-fs-sm);">Backing up…</strong>
        <span id="bk-percent" style="font-size: var(--page-fs-sm);">{{ $running?->progressPercent() ?? 0 }}%</span>
    </div>
    <div class="progress"><div class="progress-bar" id="bk-bar" style="width: {{ $running?->progressPercent() ?? 0 }}%;"></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-brand p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table bk-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-3">Backup</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($backups as $backup)
                            @php
                                $badge = match ($backup->status) {
                                    'COMPLETED' => 'badge-success-tint',
                                    'RUNNING' => 'badge-info-tint',
                                    default => 'badge-danger-tint',
                                };
                                $when = ($backup->completed_at ?? $backup->started_at ?? $backup->created_at);
                            @endphp
                            <tr>
                                <td class="ps-3 bk-name">
                                    <div class="bk-file">
                                        @if ($backup->is_encrypted)<x-icon name="lock" class="bk-lock" title="Encrypted" />@endif
                                        {{ $backup->file_name ?? 'Backup #'.$backup->id }}
                                    </div>
                                    <div class="text-brand-muted" style="font-size: var(--page-fs-xs);">
                                        {{ $when->format('M j, Y · g:i A') }}@if ($backup->row_count) · {{ number_format($backup->row_count) }} rows @endif · {{ $backup->originLabel() }}
                                    </div>
                                </td>
                                <td class="text-nowrap">{{ Backup::formatBytes($backup->size_bytes) }}</td>
                                <td>
                                    <span class="badge {{ $badge }}" @if ($backup->error_message) title="{{ $backup->error_message }}" @endif>{{ ucfirst(strtolower($backup->status)) }}</span>
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if ($backup->isCompleted())
                                        <a href="{{ route('super-admin.backups.download', $backup) }}" class="btn btn-outline-brand btn-sm"><x-icon name="download" /> Download</a>
                                        <button type="button" class="btn btn-outline-brand btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#restore-modal"
                                                data-restore-url="{{ route('super-admin.backups.restore', $backup) }}"
                                                data-restore-name="{{ $backup->file_name }}"
                                                data-restore-when="{{ $when->format('M j, Y · g:i A') }}"
                                                data-restore-encrypted="{{ $backup->is_encrypted ? '1' : '0' }}"><x-icon name="refresh" /> Restore</button>
                                    @endif
                                    @unless ($backup->isRunning())
                                        <button type="button" class="btn btn-outline-danger-brand btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                data-confirm-action="{{ route('super-admin.backups.destroy', $backup) }}"
                                                data-confirm-method="DELETE"
                                                data-confirm-title="Delete Backup"
                                                title="Delete" aria-label="Delete backup"
                                                data-confirm-message="Delete {{ $backup->file_name ?? 'this backup' }}? The file cannot be recovered."><x-icon name="trash" /></button>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-brand-muted py-4">No backups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-brand p-3">
            <h3 class="h6 mb-3">Schedule</h3>

            <form method="POST" action="{{ route('super-admin.backups.settings') }}">
                @csrf
                @method('PUT')

                <div class="mb-2">
                    <label for="bk-frequency" class="form-label">Frequency</label>
                    <select name="frequency" id="bk-frequency" class="form-select form-select-sm @error('frequency') is-invalid @enderror">
                        <option value="OFF" @selected(old('frequency', $settings->frequency) === 'OFF')>Off</option>
                        <option value="DAILY" @selected(old('frequency', $settings->frequency) === 'DAILY')>Daily</option>
                        <option value="WEEKLY" @selected(old('frequency', $settings->frequency) === 'WEEKLY')>Weekly (Sunday)</option>
                    </select>
                    @error('frequency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label for="bk-time" class="form-label">Time</label>
                        <input type="time" name="run_time" id="bk-time" class="form-control form-control-sm @error('run_time') is-invalid @enderror" value="{{ old('run_time', $settings->run_time) }}" required>
                        @error('run_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label for="bk-keep" class="form-label">Keep Latest</label>
                        <input type="number" name="keep_count" id="bk-keep" min="1" max="30" class="form-control form-control-sm @error('keep_count') is-invalid @enderror" value="{{ old('keep_count', $settings->keep_count) }}" required>
                        @error('keep_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-2">
                    <label for="bk-password" class="form-label">Archive Password</label>
                    <input type="password" name="archive_password" id="bk-password" autocomplete="new-password" minlength="8" maxlength="100"
                           class="form-control form-control-sm @error('archive_password') is-invalid @enderror"
                           placeholder="{{ $settings->archive_password ? '••••••••' : '' }}">
                    @error('archive_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if ($settings->archive_password)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="clear_password" value="1" id="bk-clear">
                        <label class="form-check-label" for="bk-clear" style="font-size: var(--page-fs-sm);">Remove password</label>
                    </div>
                @endif

                <button type="submit" class="btn btn-brand btn-sm mt-1"><x-icon name="save" /> Save Settings</button>
            </form>
        </div>
    </div>
</div>
