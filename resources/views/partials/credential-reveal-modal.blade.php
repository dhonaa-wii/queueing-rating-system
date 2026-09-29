{{--
    One-time credential reveals (a freshly generated/reset password) get a
    modal with an explicit Close button instead of the toast notification —
    a toast can disappear before the admin finishes copying the password
    down, and it can never be shown again after this. Controllers flash a
    `credential_reveal` array via session()->with() alongside their normal
    (password-free) `status` toast message.

    Expected shape: ['title' => string, 'rows' => [['label'=>..,'value'=>..], ...], 'note' => string|null]
--}}
@if (session('credential_reveal'))
    @php $reveal = session('credential_reveal'); @endphp
    <div class="modal fade" id="credential-reveal-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0">{{ $reveal['title'] ?? 'Credentials' }}</h5>
                </div>
                <div class="modal-body">
                    <dl class="detail-list mb-2">
                        @foreach ($reveal['rows'] ?? [] as $row)
                            <dt>{{ $row['label'] }}</dt>
                            <dd><code>{{ $row['value'] }}</code></dd>
                        @endforeach
                    </dl>
                    @if (! empty($reveal['note']))
                        <p class="text-brand-muted small mb-0">{{ $reveal['note'] }}</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('credential-reveal-modal');
                if (el) new bootstrap.Modal(el).show();
            });
        </script>
    @endpush
@endif
