@extends('layouts.app')

@section('title', 'Register — ' . $category->name)

@php
    $regStatus = $category->registrationWindowStatus();
    $isOpen = $regStatus === 'Open';
    $personFields = ['last_name', 'first_name', 'middle_name', 'sex', 'section', 'research_track_name'];
    $trackOptions = $category->activeTrackNames();
@endphp

@section('content')
    <div class="reg-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            @include('partials.back-link', ['href' => route('student.categories.index')])
            <h1 class="h4 mt-1 mb-1">Register — {{ $category->name }}</h1>
            <p class="text-brand-muted mb-0">
                {{ $category->academicYear->name ?? '' }} &middot; {{ $category->semester->name ?? '' }} &middot; {{ $category->college->name ?? '' }}
            </p>
        </div>
        <a href="{{ route('student.categories.schedule', $category) }}" class="btn btn-outline-brand"><x-icon name="calendar" /> View Schedule</a>
    </div>

    @if (! $isOpen)
        <div class="card-brand p-4 mb-4">
            <p class="mb-0 text-brand-accent">
                Registration for this category is not currently open
                @if ($regStatus === 'Scheduled')
                    &mdash; it opens {{ $category->registration_opens_at->format('M j, Y g:i A') }}.
                @elseif ($regStatus === 'Closed')
                    &mdash; it closed {{ $category->registration_closes_at->format('M j, Y g:i A') }}.
                @else
                    .
                @endif
            </p>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2 px-3 small mb-4" style="background-color: var(--brand-danger-tint); border-color: var(--brand-danger); color: var(--brand-danger);">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('student.categories.registration.store', $category) }}" id="registration-form" novalidate>
        @csrf

        <div class="reg-panel">
        <div class="reg-panel-head">
            <h2 class="reg-panel-title">Group Registration</h2>
        </div>

        <section class="reg-section">
            <h3 class="reg-section-title">Group Leader</h3>
            @include('partials.person-fields', [
                'nameFor' => fn ($field) => 'leader_'.$field,
                'idBase' => 'leader',
                'values' => collect($personFields)->mapWithKeys(fn ($f) => [$f => old('leader_'.$f)])->all(),
                'trackRequired' => $category->research_track_required,
                'trackOptions' => $trackOptions,
                'required' => true,
                'disabled' => ! $isOpen,
                'showErrors' => true,
            ])
        </section>

        @if ($memberSlots > 0)
            <section class="reg-section">
                <h3 class="reg-section-title">Group Members</h3>
                <p class="text-brand-muted small mb-2">
                    Up to {{ $memberSlots }} additional member{{ $memberSlots > 1 ? 's' : '' }}. A leader-only group is also valid
                    &mdash; leave a row blank to skip it. If you fill in a member, every field in that row is required.
                </p>

                @include('partials.person-fields-head', ['trackRequired' => $category->research_track_required])

                @for ($i = 0; $i < $memberSlots; $i++)
                    <div class="member-row">
                        @include('partials.person-fields', [
                            'rowLabel' => $i + 1,
                            'nameFor' => fn ($field) => "members[$i][$field]",
                            'idBase' => "member_$i",
                            'values' => collect($personFields)->mapWithKeys(fn ($f) => [$f => old("members.$i.$f")])->all(),
                            'trackRequired' => $category->research_track_required,
                            'trackOptions' => $trackOptions,
                            'required' => false,
                            'disabled' => ! $isOpen,
                            'showErrors' => true,
                        ])
                    </div>
                @endfor
            </section>
        @endif

        <section class="reg-section">
            <h3 class="reg-section-title">Project Information</h3>

            @if ($isTitleProposal)
                @php $requiredTitleCount = $category->required_proposed_title_count ?? 1; @endphp
                <p class="text-brand-muted small mb-3">
                    Submit exactly {{ $requiredTitleCount }} proposed research title{{ $requiredTitleCount > 1 ? 's' : '' }}.
                </p>
                @for ($i = 0; $i < $requiredTitleCount; $i++)
                    <div class="mb-2">
                        <label for="proposed_title_{{ $i }}" class="form-label">Proposed Title {{ $i + 1 }} <span class="text-brand-accent">*</span></label>
                        <input type="text" name="proposed_titles[{{ $i }}]" id="proposed_title_{{ $i }}" class="form-control" maxlength="300" required
                               value="{{ old("proposed_titles.$i") }}" @disabled(! $isOpen)>
                        <div class="invalid-feedback" data-error-for="proposed_titles.{{ $i }}"></div>
                    </div>
                @endfor
            @else
                <div class="mb-1">
                    <label for="project_title" class="form-label">Project Title <span class="text-brand-accent">*</span></label>
                    <input type="text" name="project_title" id="project_title" class="form-control" maxlength="255" required
                           value="{{ old('project_title') }}" @disabled(! $isOpen)>
                    <div class="invalid-feedback" data-error-for="project_title"></div>
                </div>
            @endif

            @if ($category->technical_adviser_required)
                <div class="mb-1 mt-3">
                    <label for="technical_adviser_name" class="form-label">Technical Adviser <span class="text-brand-accent">*</span></label>
                    <input type="text" name="technical_adviser_name" id="technical_adviser_name" class="form-control" maxlength="200" required
                           value="{{ old('technical_adviser_name') }}" @disabled(! $isOpen)>
                    <div class="invalid-feedback" data-error-for="technical_adviser_name"></div>
                </div>
            @endif
        </section>

        <div class="reg-panel-foot">
            <div class="text-brand-muted small" id="form-error-summary"></div>
            <button type="button" class="btn btn-brand" id="review-btn" @disabled(! $isOpen)><x-icon name="send" /> Review & Submit</button>
        </div>
        </div>
    </form>

    <div class="modal fade" id="review-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review Your Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-brand-muted small mb-3">
                        Registrations cannot be edited after submission &mdash; any correction must be requested from
                        an administrator. Please check everything below before confirming.
                    </p>
                    <dl class="detail-list small" id="review-content"></dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal"><x-icon name="arrow-left" /> Back to Edit</button>
                    <button type="button" class="btn btn-brand" id="confirm-submit-btn"><x-icon name="check" /> Confirm & Submit</button>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection

@push('styles')
    <style>
        /* Capped so the one-line person rows keep name-sized fields instead
           of stretching across a wide screen. */
        .reg-shell {
            max-width: 62rem;
            margin-inline: auto;
        }

        /* Same surface, radius, type scale and compact controls as the
           system's modals (theme-head), laid out inline as a page panel. */
        .reg-shell {
            --reg-fs: clamp(0.84rem, 0.79rem + 0.18vw, 0.9rem);
            --reg-fs-sm: clamp(0.78rem, 0.74rem + 0.14vw, 0.83rem);
            --reg-fs-xs: clamp(0.7rem, 0.68rem + 0.1vw, 0.75rem);
            --reg-fs-title: clamp(0.95rem, 0.89rem + 0.25vw, 1.06rem);
            --reg-pad-x: clamp(0.95rem, 0.75rem + 0.6vw, 1.35rem);
            font-size: var(--reg-fs);
            line-height: 1.5;
        }

        .reg-shell h1.h4 {
            font-size: clamp(1.1rem, 1rem + 0.5vw, 1.35rem);
        }

        .reg-shell .card-brand {
            background-color: var(--brand-surface-alt);
            border-radius: 1rem;
            padding: 0.85rem var(--reg-pad-x) !important;
            margin-bottom: 1rem !important;
            font-size: var(--reg-fs-sm);
        }

        .reg-panel {
            background-color: var(--brand-surface-alt);
            border: 1px solid var(--brand-border);
            border-radius: 1rem;
            box-shadow: 0 1rem 2.5rem -1.25rem rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .reg-panel-head,
        .reg-panel-foot {
            padding: 0.8rem var(--reg-pad-x);
        }

        .reg-panel-head {
            border-bottom: 1px solid var(--brand-border);
        }

        .reg-panel-title {
            margin: 0;
            font-size: var(--reg-fs-title);
            font-weight: 600;
            letter-spacing: -0.005em;
        }

        .reg-panel-foot {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--brand-border);
        }

        .reg-section {
            padding: 0.9rem var(--reg-pad-x);
        }

        .reg-section + .reg-section {
            border-top: 1px solid var(--brand-border);
        }

        .reg-section-title {
            margin: 0 0 0.55rem;
            font-size: var(--reg-fs);
            font-weight: 600;
        }

        /* Member rows are compact one-liners under a single label header
           (partials.person-fields-head); they only need a small gap. */
        .person-fields-head {
            margin-bottom: 0.3rem;
        }

        .member-row + .member-row {
            margin-top: 0.45rem;
        }

        .reg-shell .form-label {
            font-size: var(--reg-fs-sm);
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--brand-text);
        }

        .reg-shell .form-control,
        .reg-shell .form-select {
            font-size: var(--reg-fs-sm);
            padding: 0.3rem 0.6rem;
            line-height: 1.45;
            border-radius: 0.5rem;
        }

        .reg-shell .form-select {
            padding-right: 1.9rem;
            background-position: right 0.55rem center;
            background-size: 12px 9px;
        }

        .reg-shell .form-control:focus,
        .reg-shell .form-select:focus {
            box-shadow: 0 0 0 0.18rem var(--brand-accent-tint);
        }

        .reg-shell .form-control:disabled,
        .reg-shell .form-select:disabled {
            background-color: transparent;
        }

        .reg-shell .invalid-feedback {
            font-size: var(--reg-fs-xs);
            margin-top: 0.2rem;
        }

        .reg-shell .btn {
            --bs-btn-font-size: var(--reg-fs-sm);
            --bs-btn-padding-y: 0.32rem;
            --bs-btn-padding-x: 0.8rem;
            --bs-btn-border-radius: 0.5rem;
        }

        @media (max-width: 575.98px) {
            .reg-panel-foot > .btn {
                flex: 1 1 auto;
            }
        }

        .invalid-feedback[data-error-for] {
            display: none;
        }

        .is-invalid ~ .invalid-feedback[data-error-for],
        .invalid-feedback[data-error-for].is-shown {
            display: block;
        }
    </style>
@endpush

@include('partials.section-input-script')

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('registration-form');
            if (! form) return;

            var isOpen = {{ $isOpen ? 'true' : 'false' }};
            var isTitleProposal = {{ $isTitleProposal ? 'true' : 'false' }};
            var memberSlots = {{ $memberSlots }};
            var sectionPattern = /^[1-9][A-Z]$/;
            var fields = ['last_name', 'first_name', 'middle_name', 'sex', 'section'@if ($category->research_track_required), 'research_track_name'@endif];
            var labels = { last_name: 'Last name', first_name: 'First name', middle_name: 'Middle name', sex: 'Sex', section: 'Section', research_track_name: 'Track' };
            var sectionFormatMessage = @json(\App\Services\ResearchGroupRegistrationService::SECTION_FORMAT_MESSAGE);
            var reviewBtn = document.getElementById('review-btn');
            var errorSummary = document.getElementById('form-error-summary');
            var reviewModalEl = document.getElementById('review-modal');
            var reviewModal = reviewModalEl && window.bootstrap ? new bootstrap.Modal(reviewModalEl) : null;
            var confirmSubmitBtn = document.getElementById('confirm-submit-btn');

            function clearErrors() {
                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form.querySelectorAll('.invalid-feedback').forEach(function (el) {
                    el.textContent = '';
                    el.classList.remove('is-shown');
                });
                errorSummary.textContent = '';
            }

            function showError(fieldName, message) {
                var field = form.querySelector('[name="' + fieldName + '"]');
                var feedback = form.querySelector('[data-error-for="' + fieldName.replace(/\[(\d+)\]/g, '.$1').replace(/\[([a-z_]+)\]/g, '.$1') + '"]');

                if (field) field.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = message;
                    feedback.classList.add('is-shown');
                }
            }

            function val(name) {
                var el = form.querySelector('[name="' + name + '"]');
                return el ? el.value.trim() : '';
            }

            function validate() {
                clearErrors();
                var valid = true;
                var errors = [];

                if (! isOpen) {
                    errors.push('Registration is not currently open for this presentation.');
                    valid = false;
                }

                function personAt(nameFor) {
                    var person = {};
                    fields.forEach(function (f) { person[f] = val(nameFor(f)); });
                    return person;
                }

                function identity(p) {
                    return [p.last_name, p.first_name, p.middle_name].join('|').toLowerCase();
                }

                var leader = personAt(function (f) { return 'leader_' + f; });

                fields.forEach(function (f) {
                    if (! leader[f]) {
                        showError('leader_' + f, labels[f] + ' is required.');
                        valid = false;
                    }
                });

                if (leader.section && ! sectionPattern.test(leader.section)) {
                    showError('leader_section', sectionFormatMessage);
                    valid = false;
                }

                var seen = [identity(leader)];
                var members = [];

                for (var i = 0; i < memberSlots; i++) {
                    var member = personAt(function (f) { return 'members[' + i + '][' + f + ']'; });

                    if (fields.every(function (f) { return ! member[f]; })) continue;

                    var missing = fields.filter(function (f) { return ! member[f]; });
                    if (missing.length) {
                        missing.forEach(function (f) { showError('members[' + i + '][' + f + ']', labels[f] + ' is required.'); });
                        valid = false;
                        continue;
                    }

                    if (! sectionPattern.test(member.section)) {
                        showError('members[' + i + '][section]', sectionFormatMessage);
                        valid = false;
                        continue;
                    }

                    var key = identity(member);
                    if (seen.indexOf(key) !== -1) {
                        showError('members[' + i + '][last_name]', key === seen[0]
                            ? 'This cannot match the group leader\'s name.'
                            : 'This name is entered more than once.');
                        valid = false;
                        continue;
                    }

                    seen.push(key);
                    members.push(member);
                }

                var projectTitle = '';
                var proposedTitles = [];

                if (isTitleProposal) {
                    for (var t = 0; t < {{ $isTitleProposal ? ($category->required_proposed_title_count ?? 1) : 0 }}; t++) {
                        var title = val('proposed_titles[' + t + ']');
                        if (! title) {
                            showError('proposed_titles[' + t + ']', 'This proposed title is required.');
                            valid = false;
                        }
                        proposedTitles.push(title);
                    }
                } else {
                    projectTitle = val('project_title');
                    if (! projectTitle) {
                        showError('project_title', 'Project title is required.');
                        valid = false;
                    }
                }

                @if ($category->technical_adviser_required)
                    if (! val('technical_adviser_name')) {
                        showError('technical_adviser_name', 'Technical adviser is required.');
                        valid = false;
                    }
                @endif

                if (! valid) {
                    errorSummary.textContent = 'Please fix the highlighted fields above.';
                }

                return valid ? {
                    leader: leader,
                    members: members,
                    projectTitle: projectTitle,
                    proposedTitles: proposedTitles,
                } : null;
            }

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            function buildReview(data) {
                var rows = [];

                function describe(p) {
                    var parts = [p.last_name + ', ' + p.first_name + ' ' + p.middle_name.charAt(0).toUpperCase() + '.', p.sex, p.section];
                    if (p.research_track_name) parts.push(p.research_track_name);
                    return parts.map(escapeHtml).join(' &middot; ');
                }

                rows.push(['Leader', describe(data.leader)]);

                if (data.members.length) {
                    rows.push(['Members', data.members.map(describe).join('<br>')]);
                } else {
                    rows.push(['Members', 'None &mdash; leader-only group']);
                }

                if (isTitleProposal) {
                    rows.push(['Proposed Titles', data.proposedTitles.map(function (t, i) {
                        return (i + 1) + '. ' + escapeHtml(t);
                    }).join('<br>')]);
                } else {
                    rows.push(['Project Title', escapeHtml(data.projectTitle)]);
                }

                @if ($category->technical_adviser_required)
                    rows.push(['Technical Adviser', escapeHtml(val('technical_adviser_name'))]);
                @endif

                var html = '';
                rows.forEach(function (row) {
                    html += '<dt>' + row[0] + '</dt><dd>' + row[1] + '</dd>';
                });

                document.getElementById('review-content').innerHTML = html;
            }

            reviewBtn.addEventListener('click', function () {
                var data = validate();
                if (! data) return;

                buildReview(data);

                if (reviewModal) {
                    reviewModal.show();
                } else if (confirm('Submit this registration? It cannot be edited afterward.')) {
                    form.submit();
                }
            });

            confirmSubmitBtn.addEventListener('click', function () {
                confirmSubmitBtn.disabled = true;
                form.submit();
            });
        })();
    </script>
@endpush
