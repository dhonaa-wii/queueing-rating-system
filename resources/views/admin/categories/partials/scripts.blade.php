@push('styles')
    <style>
        [data-ajax-submit] {
            position: relative;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            function showToast(message, isError) {
                window.showAppToast(message, isError);
            }

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form.querySelectorAll('[data-ajax-error]').forEach(function (el) { el.remove(); });
            }

            // Returns whether at least one error key matched a real input —
            // callers use this to decide between the generic "fix the errors
            // below" toast and showing the message itself (e.g. a guard like
            // "Cannot ... — this category has ended." that isn't about any
            // one field and has nothing to attach an inline message to).
            function showErrors(form, errors) {
                var matched = false;

                Object.keys(errors).forEach(function (field) {
                    // Array fields (e.g. section_order[]) report errors under a
                    // single key that no input is named after — they mark their
                    // container with data-error-for instead.
                    var input = form.querySelector('[name="' + field + '"]')
                        || form.querySelector('[data-error-for="' + field.split('.')[0] + '"]');
                    if (!input) return;
                    matched = true;
                    input.classList.add('is-invalid');
                    var feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.setAttribute('data-ajax-error', '1');
                    feedback.textContent = errors[field][0];
                    input.insertAdjacentElement('afterend', feedback);
                });

                return matched;
            }

            function bindAjaxForm(form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    var submitButton = form.querySelector('button[type="submit"]');
                    // innerHTML, not textContent — restoring textContent dropped
                    // the button's icon after the first save.
                    var originalLabel = submitButton ? submitButton.innerHTML : null;
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Saving…';
                    }

                    clearErrors(form);

                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form),
                    }).then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (data) {
                            return { status: response.status, ok: response.ok, data: data };
                        });
                    }).then(function (result) {
                        if (result.ok) {
                            showToast(result.data.message || 'Saved.', false);

                            if (form.dataset.ajax === 'create' && result.data.redirect) {
                                window.location.href = result.data.redirect;
                                return;
                            }
                        } else if (result.status === 422) {
                            var errors = result.data.errors || {};
                            var matched = showErrors(form, errors);
                            var firstField = Object.keys(errors)[0];
                            var firstMessage = firstField ? errors[firstField][0] : null;
                            // A matched field already shows its own inline message,
                            // so the toast stays generic; an unmatched one (e.g. a
                            // category-level guard with no field to attach to) shows
                            // the real message instead of a vague fallback.
                            showToast(matched ? 'Please fix the errors below.' : (firstMessage || 'Please fix the errors below.'), true);
                        } else {
                            showToast('Something went wrong. Please try again.', true);
                        }
                    }).catch(function () {
                        showToast('Network error. Please try again.', true);
                    }).finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalLabel;
                        }
                    });
                });
            }

            document.querySelectorAll('form[data-ajax]').forEach(bindAjaxForm);

            // ---- In-place refresh for plain-POST forms ------------------------
            // The date / room / break / announcement forms are ordinary POSTs
            // that redirect back. Instead of letting that reload the page, the
            // POST goes over fetch, the redirect target is read, and only the
            // pane the form lives in (plus the queue result card and the tab
            // red dots) is swapped. The active tab, scroll position and every
            // other pane's unsaved input are left exactly as they were.
            var scrollContainer = document.querySelector('.admin-content');
            var toastPattern = /window\.showAppToast\(("(?:[^"\\]|\\.)*"),\s*(true|false)\)/g;

            function announceToasts(html) {
                var match;
                toastPattern.lastIndex = 0;
                while ((match = toastPattern.exec(html)) !== null) {
                    try {
                        showToast(JSON.parse(match[1]), match[2] === 'true');
                    } catch (e) {}
                }
            }

            function swapFrom(html, paneIds) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var scrollTop = scrollContainer ? scrollContainer.scrollTop : 0;

                paneIds.forEach(function (id) {
                    var current = document.getElementById(id);
                    var fresh = doc.getElementById(id);
                    if (current && fresh) {
                        current.innerHTML = fresh.innerHTML;
                    }
                });

                var dotsChanged = false;
                document.querySelectorAll('#setup-tabs button[data-bs-target]').forEach(function (button) {
                    var freshButton = doc.querySelector('#setup-tabs button[data-bs-target="' + button.dataset.bsTarget + '"]');
                    if (! freshButton) return;
                    var dot = button.querySelector('.tab-incomplete-dot');
                    var freshDot = freshButton.querySelector('.tab-incomplete-dot');
                    if (freshDot && ! dot) {
                        button.appendChild(freshDot.cloneNode(true));
                        dotsChanged = true;
                    } else if (! freshDot && dot) {
                        dot.remove();
                        dotsChanged = true;
                    }
                });
                if (dotsChanged) {
                    window.dispatchEvent(new Event('resize'));
                }

                if (scrollContainer) {
                    scrollContainer.scrollTop = scrollTop;
                }

                document.dispatchEvent(new CustomEvent('category:soft-refreshed'));
            }

            function whenModalClosed(modalEl, callback) {
                var instance = modalEl && window.bootstrap ? bootstrap.Modal.getInstance(modalEl) : null;
                if (! instance || ! modalEl.classList.contains('show')) {
                    callback();
                    return;
                }
                modalEl.addEventListener('hidden.bs.modal', callback, { once: true });
                instance.hide();
            }

            // Re-read the current page and swap the given panes (default: all
            // panes that hold plain forms). Used by scripts that change data
            // without a form submit.
            window.categorySoftRefresh = function (paneIds) {
                return fetch(window.location.pathname + window.location.search, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    credentials: 'same-origin',
                }).then(function (response) {
                    return response.text();
                }).then(function (html) {
                    swapFrom(html, paneIds || ['tab-schedules', 'tab-announcements']);
                });
            };

            document.addEventListener('submit', function (event) {
                var form = event.target;

                if (event.defaultPrevented || ! form.matches || ! form.matches('form')) return;
                if (form.hasAttribute('data-ajax') || form.hasAttribute('data-room-register-form')) return;
                if ((form.getAttribute('method') || '').toUpperCase() !== 'POST') return;

                var pane = form.closest('.tab-pane');
                var modalEl = form.closest('.modal');
                // Only forms that belong to this page's own tab panes or to its
                // shared confirmation modal are handled here.
                if (! pane && (! modalEl || modalEl.id !== 'confirm-action-modal')) return;

                var paneId = pane ? pane.id : 'tab-schedules';
                var submitter = event.submitter || null;
                var formData = submitter && submitter.name ? new FormData(form, submitter) : new FormData(form);

                event.preventDefault();

                if (submitter) submitter.disabled = true;

                var restore = function () {
                    if (submitter) submitter.disabled = false;
                };

                var request = fetch(form.getAttribute('action'), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    credentials: 'same-origin',
                    body: formData,
                }).then(function (response) {
                    if (response.redirected && new URL(response.url).pathname !== window.location.pathname) {
                        // The action sent the admin to a different page — follow it.
                        window.location.href = response.url;
                        return null;
                    }
                    if (! response.ok) {
                        throw new Error('bad-status');
                    }
                    return response.text();
                });

                request.then(function (html) {
                    if (html === null) return;
                    // The confirmation modal's submit button is one shared element
                    // reused for every action, so it has to be re-enabled here too.
                    restore();
                    whenModalClosed(modalEl, function () {
                        swapFrom(html, [paneId]);
                        announceToasts(html);
                    });
                }).catch(function () {
                    restore();
                    showToast('Something went wrong. Please try again.', true);
                });
            });
        })();
    </script>
@endpush
