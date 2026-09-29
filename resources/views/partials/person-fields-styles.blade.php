{{--
    Layout for partials.person-fields / person-fields-head. Sized by the width
    the fields are given (a container query), so the public form, the Admin
    modals and a phone each get the layout that fits them.
--}}
@once
    @push('styles')
        <style>
            .person-fields { container-type: inline-size; }

            /* Wide: the whole person on one line, each field sized to what it
               holds — sex is one word, a section two characters. */
            .person-grid {
                display: grid;
                gap: 0.5rem;
                align-items: start;
                grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.1fr) 6rem 4.6rem;
            }
            .person-grid.has-track {
                grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.1fr) 6rem 4.6rem minmax(0, 1.2fr);
            }
            .person-grid.has-row-label {
                grid-template-columns: 1.4rem minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.1fr) 6rem 4.6rem;
            }
            .person-grid.has-track.has-row-label {
                grid-template-columns: 1.4rem minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.1fr) 6rem 4.6rem minmax(0, 1.2fr);
            }

            .person-cell { min-width: 0; }
            .person-cell .form-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

            .person-row-label {
                align-self: center;
                font-size: 0.78rem;
                font-weight: 600;
                color: var(--brand-muted);
                text-align: center;
            }
            .person-row-word { display: none; }

            .person-fields-head .person-grid > div {
                font-size: 0.72rem;
                font-weight: 600;
                letter-spacing: 0.03em;
                text-transform: uppercase;
                color: var(--brand-muted);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* Medium (a modal, a tablet): names on one line, the rest under. */
            @container (max-width: 44rem) {
                .person-grid,
                .person-grid.has-track,
                .person-grid.has-row-label,
                .person-grid.has-track.has-row-label {
                    grid-template-columns: repeat(6, minmax(0, 1fr));
                }
                .person-last-name,
                .person-first-name { grid-column: span 3; }
                .person-middle-name,
                .person-sex,
                .person-section { grid-column: span 2; }
                .has-track .person-middle-name,
                .has-track .person-track { grid-column: span 3; }
                .has-track .person-sex,
                .has-track .person-section { grid-column: span 3; }

                .person-row-label {
                    grid-column: 1 / -1;
                    text-align: left;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    font-size: 0.72rem;
                }
                .person-row-word { display: inline; }
                .person-fields-head { display: none; }
            }

            /* Narrow (a phone): one field per line, sex and section paired. */
            @container (max-width: 26rem) {
                .person-last-name,
                .person-first-name,
                .person-middle-name,
                .has-track .person-middle-name,
                .has-track .person-track { grid-column: 1 / -1; }
                .person-sex,
                .person-section,
                .has-track .person-sex,
                .has-track .person-section { grid-column: span 3; }
            }
        </style>
    @endpush
@endonce
