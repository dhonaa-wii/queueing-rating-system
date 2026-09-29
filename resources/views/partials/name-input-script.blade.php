{{--
    Tidies name inputs (data-name-input) when the user leaves the field: trims,
    collapses spaces and capitalises each word ("dela  cruz" -> "Dela Cruz").
    The server normalizes the same way (Student::normalizeName()); this only
    keeps what's on screen matching what gets saved. Safe to include more than once.
--}}
@once
    @push('scripts')
        <script>
            document.addEventListener('focusout', function (event) {
                var input = event.target;
                if (! input.matches || ! input.matches('[data-name-input]')) return;

                var tidy = input.value.replace(/\s+/g, ' ').trim().toLowerCase()
                    .replace(/(^|[\s\-'’])(\p{L})/gu, function (m, lead, letter) { return lead + letter.toUpperCase(); });

                if (tidy !== input.value) input.value = tidy;
            });
        </script>
    @endpush
@endonce
