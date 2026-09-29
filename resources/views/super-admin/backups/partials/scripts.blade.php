<script>
    (function () {
        var csrf = @json(csrf_token());

        function toast(message, isError) {
            if (window.showAppToast) window.showAppToast(message, !!isError);
        }

        // ---------------------------------------------------------------
        // Tabs (remembered in the URL hash)
        // ---------------------------------------------------------------
        var tabs = document.querySelectorAll('[data-bk-tab]');

        function showTab(name) {
            var found = false;
            tabs.forEach(function (tab) {
                var on = tab.dataset.bkTab === name;
                found = found || on;
                tab.classList.toggle('is-active', on);
            });
            if (! found) name = 'backups';
            document.querySelectorAll('.bk-pane').forEach(function (pane) {
                pane.classList.toggle('is-active', pane.id === 'tab-' + name);
            });
            if (found) tabs.forEach(function (tab) { tab.classList.toggle('is-active', tab.dataset.bkTab === name); });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                showTab(tab.dataset.bkTab);
                history.replaceState(null, '', '#tab-' + tab.dataset.bkTab);
            });
        });
        showTab(window.location.hash.replace('#tab-', ''));

        // ---------------------------------------------------------------
        // Backup run
        // ---------------------------------------------------------------
        var startButton = document.getElementById('bk-start');
        var panel = document.getElementById('bk-progress');
        var bar = document.getElementById('bk-bar');
        var percent = document.getElementById('bk-percent');
        var startUrl = @json(route('super-admin.backups.store'));

        function post(url, options) {
            options = options || {};
            var headers = Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, options.headers || {});
            return fetch(url, { method: 'POST', headers: headers, body: options.body, credentials: 'same-origin' })
                .then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        if (! response.ok) throw new Error(data.message || ('Request failed (' + response.status + ').'));
                        return data;
                    });
                });
        }

        function show(progress) {
            panel.classList.add('is-active');
            bar.style.width = progress + '%';
            percent.textContent = progress + '%';
        }

        // Drive a run to the end, one time-boxed step per request.
        function drive(stepUrl) {
            startButton.disabled = true;

            function next(url) {
                post(url).then(function (state) {
                    show(state.progress);

                    if (state.status === 'RUNNING') {
                        setTimeout(function () { next(state.step_url); }, 400);
                        return;
                    }

                    toast(state.status === 'COMPLETED' ? 'Backup completed.' : (state.error || 'Backup failed.'), state.status !== 'COMPLETED');
                    setTimeout(function () { window.location.reload(); }, 1200);
                }).catch(function (error) {
                    toast(error.message, true);
                    startButton.disabled = false;
                });
            }

            next(stepUrl);
        }

        startButton.addEventListener('click', function () {
            startButton.disabled = true;
            post(startUrl).then(function (state) {
                show(state.progress);
                drive(state.step_url);
            }).catch(function (error) {
                toast(error.message, true);
                startButton.disabled = false;
            });
        });

        // A run already in progress (started earlier or by the scheduler) is picked up.
        if (panel.dataset.stepUrl) drive(panel.dataset.stepUrl);

        // ---------------------------------------------------------------
        // Restore
        // ---------------------------------------------------------------
        var modalEl = document.getElementById('restore-modal');
        var form = document.getElementById('restore-form');
        var confirmInput = document.getElementById('restore-confirm');
        var submit = document.getElementById('restore-submit');
        var errorBox = document.getElementById('restore-error');
        var progress = document.getElementById('restore-progress');
        var restoreUrl = null;
        var running = false;

        modalEl.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (! trigger || running) return;

            restoreUrl = trigger.dataset.restoreUrl;
            document.getElementById('restore-name').textContent = trigger.dataset.restoreName;
            document.getElementById('restore-when').textContent = 'Taken ' + trigger.dataset.restoreWhen;
            document.getElementById('restore-password-group').hidden = trigger.dataset.restoreEncrypted !== '1';
            document.getElementById('restore-password').value = '';
            confirmInput.value = '';
            submit.disabled = true;
            errorBox.hidden = true;
        });

        confirmInput.addEventListener('input', function () {
            submit.disabled = confirmInput.value.trim() !== 'RESTORE';
        });

        function setRestoreProgress(state) {
            document.getElementById('restore-bar').style.width = state.progress + '%';
            document.getElementById('restore-percent').textContent = state.progress + '%';
            if (state.phase_label) document.getElementById('restore-phase').textContent = state.phase_label + '…';
        }

        function driveRestore(url, token) {
            function next() {
                fetch(url, { method: 'POST', headers: { 'X-Restore-Token': token, 'Accept': 'application/json' } })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (! response.ok) throw new Error(data.message || 'The restore could not continue.');
                            return data;
                        });
                    })
                    .then(function (state) {
                        setRestoreProgress(state);

                        if (state.status === 'RUNNING') { setTimeout(next, 300); return; }

                        localStorage.removeItem('qrs_restore');
                        document.getElementById('restore-phase').textContent = state.status === 'COMPLETED'
                            ? 'Restore complete'
                            : (state.error || 'Restore failed');
                        setTimeout(function () { window.location.reload(); }, 1800);
                    })
                    .catch(function () {
                        // A dropped request is not a failed restore: the run is resumable, so try again.
                        setTimeout(next, 3000);
                    });
            }

            next();
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            errorBox.hidden = true;
            submit.disabled = true;

            post(restoreUrl, { body: new FormData(form) }).then(function (state) {
                running = true;
                localStorage.setItem('qrs_restore', JSON.stringify({ url: state.step_url, token: state.token }));

                document.getElementById('restore-close').hidden = true;
                form.hidden = true;
                progress.hidden = false;
                setRestoreProgress(state);
                driveRestore(state.step_url, state.token);
            }).catch(function (error) {
                errorBox.textContent = error.message;
                errorBox.hidden = false;
                submit.disabled = confirmInput.value.trim() !== 'RESTORE';
            });
        });

        @if ($errors->has('archive'))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('upload-modal')).show();
        @endif
    })();
</script>
