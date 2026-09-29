<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $restoring ? 'Restoring' : 'Under Maintenance' }} · {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    @include('partials.theme-head')
    @if ($restoring)
        <meta http-equiv="refresh" content="15">
    @endif
    <style>
        .mt-wrap { min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: var(--brand-bg); color: var(--brand-text); }
        .mt-card { max-width: 30rem; width: 100%; text-align: center; padding: 2rem 1.5rem; }
        .mt-ico { width: 3rem; height: 3rem; margin: 0 auto 1rem; border-radius: 50%; display: grid; place-items: center; background: var(--brand-accent-tint); color: var(--brand-accent); }
        .mt-ico svg { width: 1.4rem; height: 1.4rem; }
        .mt-progress { display: none; margin-top: 1.25rem; text-align: left; }
        .mt-progress.is-active { display: block; }
        .mt-progress .progress { height: .5rem; background: var(--brand-surface-alt); border-radius: 999px; }
        .mt-progress .progress-bar { background: var(--brand-accent); }
        .mt-progress-label { display: flex; justify-content: space-between; font-size: .85rem; margin-bottom: .35rem; }
    </style>
</head>
<body>
    <div class="mt-wrap">
        <div class="card-brand mt-card">
            <div class="mt-ico"><x-icon name="settings" /></div>
            <h1 class="h4 mb-2">{{ $restoring ? 'Restoring from a backup' : 'Under maintenance' }}</h1>
            <p class="mb-0 text-brand-muted">{{ $message }}</p>

            @if ($restoring)
                <div class="mt-progress" id="mt-progress">
                    <div class="mt-progress-label"><span id="mt-phase">Restoring…</span><span id="mt-percent">0%</span></div>
                    <div class="progress"><div class="progress-bar" id="mt-bar" style="width: 0%;"></div></div>
                </div>
            @endif
        </div>
    </div>

    @if ($restoring)
        {{-- The tab that started the restore stored where to carry on. If it was
             closed, reopening the site lands here and picks the work back up
             (cron does the same when it is set up). --}}
        <script>
            (function () {
                var saved;
                try { saved = JSON.parse(localStorage.getItem('qrs_restore') || 'null'); } catch (e) { saved = null; }
                if (! saved || ! saved.url || ! saved.token) return;

                var panel = document.getElementById('mt-progress');
                var bar = document.getElementById('mt-bar');
                var percent = document.getElementById('mt-percent');
                var phase = document.getElementById('mt-phase');
                var stopped = false;

                document.querySelector('meta[http-equiv="refresh"]').remove();
                panel.classList.add('is-active');

                function next() {
                    if (stopped) return;
                    fetch(saved.url, { method: 'POST', headers: { 'X-Restore-Token': saved.token, 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (state) {
                            bar.style.width = state.progress + '%';
                            percent.textContent = state.progress + '%';
                            if (state.phase_label) phase.textContent = state.phase_label + '…';

                            if (state.status === 'RUNNING') { setTimeout(next, 300); return; }

                            stopped = true;
                            localStorage.removeItem('qrs_restore');
                            phase.textContent = state.status === 'COMPLETED' ? 'Restore complete' : (state.error || 'Restore failed');
                            setTimeout(function () { window.location.reload(); }, 1500);
                        })
                        .catch(function () { setTimeout(next, 3000); });
                }

                next();
            })();
        </script>
    @endif
</body>
</html>
