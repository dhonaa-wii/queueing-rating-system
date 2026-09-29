{{--
    Saves without a page reload (user-directed 2026-09-30, Group & Panel
    Assignment first). Every POST form inside <main class="admin-content">
    is sent with fetch instead of navigating. The controllers are untouched:
    they still redirect back, the redirected page comes back as HTML, and its
    <main> is swapped in place. The open roster tab, room filter and scroll
    positions are carried over, and the page's own scripts are re-run so the
    new rows are wired up exactly like on a first load.

    - An error-only result (flash error, no status) is not swapped in: the
      error is toasted and the modal the user was typing in stays open.
    - A redirect to a different page is followed normally.
    - A form opts out with data-hard-submit.

    Include once, from a page that uses the admin layout.
--}}
@push('scripts')
    <script data-soft-submit>
        (function () {
            if (window.__softSubmit) return;
            window.__softSubmit = true;

            var main = document.querySelector('main.admin-content');
            if (! main) return;

            var busy = false;
            var trackedListeners = [];

            function isSoft(form) {
                return form instanceof HTMLFormElement
                    && main.contains(form)
                    && (form.getAttribute('method') || 'get').toLowerCase() === 'post'
                    && ! form.hasAttribute('data-hard-submit')
                    && ! form.target;
            }

            // form.submit() skips the submit event, so it has to be caught too.
            var nativeSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function () {
                if (isSoft(this)) {
                    send(this, null);
                } else {
                    nativeSubmit.call(this);
                }
            };

            // Bubble phase on document: a form's own submit handlers (client
            // validation, confirm steps) run first and can still cancel it.
            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (event.defaultPrevented || ! isSoft(form)) return;
                event.preventDefault();
                send(form, event.submitter || null);
            });

            function submitButtons(form) {
                var inside = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'));
                var outside = form.id ? Array.prototype.slice.call(document.querySelectorAll('[form="' + CSS.escape(form.id) + '"]')) : [];
                return inside.concat(outside);
            }

            function send(form, submitter) {
                if (busy) return;
                busy = true;

                var data = new FormData(form);
                if (submitter && submitter.name) data.append(submitter.name, submitter.value);

                var buttons = submitButtons(form).filter(function (btn) { return ! btn.disabled; });
                buttons.forEach(function (btn) { btn.disabled = true; });
                main.setAttribute('aria-busy', 'true');

                function done() {
                    busy = false;
                    buttons.forEach(function (btn) { btn.disabled = false; });
                    main.removeAttribute('aria-busy');
                }

                fetch(form.action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'text/html' },
                })
                    .then(function (response) {
                        return response.text().then(function (html) { return { response: response, html: html }; });
                    })
                    .then(function (result) {
                        var doc = new DOMParser().parseFromString(result.html, 'text/html');
                        var newMain = doc.querySelector('main.admin-content');
                        var landed = new URL(result.response.url || window.location.href, window.location.href);

                        // Signed out, session expired, a server error page, or
                        // a redirect elsewhere: let the browser handle it.
                        if (! newMain || landed.pathname !== window.location.pathname) {
                            window.location.href = newMain ? landed.href : window.location.href;
                            return;
                        }

                        var stack = doc.getElementById('app-toast-stack');
                        var status = stack ? stack.dataset.flashStatus : '';
                        var error = stack ? stack.dataset.flashError : '';

                        if (error && ! status) {
                            window.showAppToast && window.showAppToast(error, true);
                            done();
                            return;
                        }

                        swap(doc, newMain);
                        done();
                    })
                    .catch(function () {
                        window.showAppToast && window.showAppToast('Could not save — check your connection and try again.', true);
                        done();
                    });
            }

            function captureState() {
                var activeTab = document.querySelector('[data-tab-btn].active');
                var roomFilter = document.getElementById('room-filter');
                var scrolled = [];

                main.querySelectorAll('[id]').forEach(function (el) {
                    if (el.scrollTop > 0) scrolled.push([el.id, el.scrollTop]);
                });

                return {
                    tab: activeTab ? activeTab.dataset.tabBtn : null,
                    room: roomFilter ? roomFilter.value : null,
                    windowY: window.scrollY,
                    mainY: main.scrollTop,
                    scrolled: scrolled,
                };
            }

            function restoreState(state) {
                var roomFilter = document.getElementById('room-filter');
                if (roomFilter && state.room && roomFilter.querySelector('option[value="' + CSS.escape(state.room) + '"]')) {
                    roomFilter.value = state.room;
                    roomFilter.dispatchEvent(new Event('change'));
                }

                if (state.tab) {
                    var tab = document.querySelector('[data-tab-btn="' + CSS.escape(state.tab) + '"]');
                    if (tab && ! tab.classList.contains('active')) tab.click();
                }

                // Twice: some panels size themselves on the next frame.
                var apply = function () {
                    window.scrollTo(0, state.windowY);
                    main.scrollTop = state.mainY;
                    state.scrolled.forEach(function (pair) {
                        var el = document.getElementById(pair[0]);
                        if (el) el.scrollTop = pair[1];
                    });
                };
                apply();
                requestAnimationFrame(function () { apply(); requestAnimationFrame(apply); });
            }

            function closeModals() {
                main.querySelectorAll('.modal').forEach(function (el) {
                    var instance = window.bootstrap && bootstrap.Modal.getInstance(el);
                    if (instance) instance.dispose();
                });
                document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }

            function swap(doc, newMain) {
                var state = captureState();

                closeModals();
                main.innerHTML = newMain.innerHTML;
                rerunScripts(doc, newMain);
                restoreState(state);
            }

            // The page's scripts ran once against the old markup; run the new
            // page's copies against the new markup. Content scripts first,
            // then everything the layout printed after Bootstrap (the pushed
            // stack and the focus script) — the same order as a first load.
            function rerunScripts(doc, newMain) {
                trackedListeners.forEach(function (entry) {
                    entry[0].removeEventListener(entry[1], entry[2], entry[3]);
                });
                trackedListeners = [];

                var all = Array.prototype.slice.call(doc.body.querySelectorAll('script'));
                var bootstrapIndex = all.findIndex(function (s) { return /bootstrap(\.bundle)?(\.min)?\.js/.test(s.getAttribute('src') || ''); });
                var inContent = Array.prototype.slice.call(newMain.querySelectorAll('script'));
                var afterBootstrap = bootstrapIndex === -1 ? [] : all.slice(bootstrapIndex + 1);

                var scripts = inContent.concat(afterBootstrap).filter(function (s) {
                    return ! s.getAttribute('src') && ! s.hasAttribute('data-soft-submit');
                });

                var readyCallbacks = [];
                var loadCallbacks = [];
                var docAdd = document.addEventListener;
                var winAdd = window.addEventListener;

                document.addEventListener = function (type, callback, options) {
                    if (type === 'DOMContentLoaded') { readyCallbacks.push(callback); return; }
                    trackedListeners.push([document, type, callback, options]);
                    return docAdd.call(document, type, callback, options);
                };
                window.addEventListener = function (type, callback, options) {
                    if (type === 'load' || type === 'DOMContentLoaded') { loadCallbacks.push(callback); return; }
                    trackedListeners.push([window, type, callback, options]);
                    return winAdd.call(window, type, callback, options);
                };

                try {
                    scripts.forEach(function (source) {
                        var script = document.createElement('script');
                        // A block keeps top-level const/let from colliding
                        // with the first run's declarations.
                        script.textContent = '{\n' + source.textContent + '\n}';
                        document.body.appendChild(script);
                        script.remove();
                    });
                } finally {
                    document.addEventListener = docAdd;
                    window.addEventListener = winAdd;
                }

                readyCallbacks.forEach(function (callback) {
                    try { callback.call(document, new Event('DOMContentLoaded')); } catch (e) { console.error(e); }
                });
                loadCallbacks.forEach(function (callback) {
                    try { callback.call(window, new Event('load')); } catch (e) { console.error(e); }
                });
            }
        })();
    </script>
@endpush
