{{--
    The Summary of Recommendations & Comments modal, shared by Admin Reports
    (one category) and Panelist My Advisees (that panelist's advisees). Opened
    by any [data-recommendations-open] button; its body is fetched fresh from
    $fetchUrl on every open, so groups completed since the last look are on it.

    Each row's Print loads that group's Recommendation Form into a hidden frame
    and prints the frame, so only the form prints; Export downloads its PDF.

    Inputs: $fetchUrl, $pdfUrl.
--}}
<div class="modal fade" id="recommendations-modal" tabindex="-1" aria-labelledby="recommendations-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recommendations-modal-label">Summary of Recommendations &amp; Comments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-recommendations-body></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                <a href="{{ $pdfUrl }}" class="btn btn-brand"><x-icon name="file-text" /> Export to PDF</a>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .rp-rec-count {
            margin-bottom: 0.6rem;
            font-size: 0.8rem;
            color: var(--brand-muted);
        }

        .rp-rec-grid {
            min-width: 44rem;
            border: 1px solid var(--brand-border);
            border-radius: 0.6rem;
            overflow: hidden;
        }

        .rp-rec-row {
            display: grid;
            grid-template-columns: 2.5rem 7.5rem minmax(0, 1.1fr) minmax(0, 2fr);
            gap: 0.75rem;
            padding: 0.7rem 0.85rem;
            font-size: 0.85rem;
        }

        .rp-rec-row + .rp-rec-row {
            border-top: 1px solid var(--brand-border);
        }

        .rp-rec-head {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--brand-muted);
            background-color: var(--brand-surface);
        }

        .rp-rec-seq {
            font-weight: 600;
            text-align: center;
        }

        .rp-rec-ref {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--brand-accent);
        }

        .rp-rec-category {
            font-size: 0.72rem;
            color: var(--brand-muted);
        }

        .rp-rec-title {
            font-weight: 600;
            margin: 0.1rem 0 0.25rem;
        }

        /* Print / Export sit in the row's upper right, the comments flowing
           beneath and around them. */
        .rp-rec-actions {
            float: right;
            display: flex;
            gap: 0.35rem;
            margin: 0 0 0.35rem 0.75rem;
        }

        .rp-rec-actions .btn {
            --bs-btn-padding-y: 0.15rem;
            --bs-btn-padding-x: 0.5rem;
            --bs-btn-font-size: 0.72rem;
            gap: 0.25rem;
        }

        .rp-rec-comment + .rp-rec-comment {
            margin-top: 0.4rem;
        }

        .rp-rec-panelist {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--brand-accent);
        }

        .rp-rec-text {
            white-space: pre-line;
        }

        .rp-rec-empty {
            padding: 1rem;
            color: var(--brand-muted);
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var modalEl = document.getElementById('recommendations-modal');
            if (! modalEl) return;

            var body = modalEl.querySelector('[data-recommendations-body]');

            document.addEventListener('click', function (event) {
                if (event.target.closest('[data-recommendations-open]')) {
                    body.innerHTML = '<div class="text-brand-muted">Loading&hellip;</div>';
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();

                    fetch(@json($fetchUrl), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function (response) { return response.ok ? response.text() : Promise.reject(); })
                        .then(function (html) { body.innerHTML = '<div class="table-responsive">' + html + '</div>'; })
                        .catch(function () { body.innerHTML = '<div class="text-danger">Could not load the summary. Try again.</div>'; });
                    return;
                }

                var printer = event.target.closest('[data-rec-form-print]');
                if (! printer) return;

                var oldFrame = document.getElementById('rec-form-print-frame');
                if (oldFrame) oldFrame.remove();

                var frame = document.createElement('iframe');
                frame.id = 'rec-form-print-frame';
                frame.setAttribute('aria-hidden', 'true');
                frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:794px;height:1123px;border:0;';
                printer.disabled = true;
                frame.onload = function () {
                    printer.disabled = false;
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                };
                frame.src = printer.dataset.recFormPrint;
                document.body.appendChild(frame);
            });
        })();
    </script>
@endpush
