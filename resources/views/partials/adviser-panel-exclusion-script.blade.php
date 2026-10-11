{{--
    A group's technical adviser can't sit on its own panel (user-directed
    2026-10-11): picking an adviser ([data-adviser-select]) disables that
    person in the same form's panel dropdowns ([data-panelist-choice]) and
    clears a seat that already had them. The server refuses it anyway
    (ResearchGroupRegistrationService, PanelAssignmentService). Options are
    matched by their text, which is how the adviser is stored.
--}}
@once
    @push('scripts')
        <script>
            (function () {
                function apply(form) {
                    var adviser = form.querySelector('[data-adviser-select]');
                    var name = adviser ? adviser.value : (form.dataset.adviserName || '');

                    form.querySelectorAll('[data-panelist-choice]').forEach(function (select) {
                        Array.prototype.forEach.call(select.options, function (option) {
                            var isAdviser = name !== '' && option.value !== '' && option.text === name;
                            option.disabled = isAdviser;
                            if (isAdviser && option.selected) select.value = '';
                        });
                    });
                }

                document.addEventListener('change', function (e) {
                    if (e.target.matches('[data-adviser-select]') && e.target.form) apply(e.target.form);
                });

                document.querySelectorAll('form').forEach(function (form) {
                    if (form.querySelector('[data-panelist-choice]')) apply(form);
                });
            })();
        </script>
    @endpush
@endonce
