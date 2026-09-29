{{--
    Live formatting for section inputs (data-section-input): uppercases and
    drops spaces/hyphens as the user types, so "4b" becomes "4B". The server
    normalizes the same way (ResearchGroupRegistrationService::
    normalizeSection()), this only keeps what's on screen matching what gets
    saved. Safe to include more than once.
--}}
@once
    @push('scripts')
        <script>
            document.addEventListener('input', function (event) {
                var input = event.target;
                if (! input.matches || ! input.matches('[data-section-input]')) return;

                var formatted = input.value.toUpperCase().replace(/[\s-]+/g, '');
                if (formatted !== input.value) input.value = formatted;
            });
        </script>
    @endpush
@endonce
