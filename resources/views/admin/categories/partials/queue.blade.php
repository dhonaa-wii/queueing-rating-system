<div class="card-brand p-4 h-100">
    <h3 class="h6 mb-3">Queue Configuration</h3>

    <form method="POST" action="{{ route('admin.categories.queue-config.update', $category) }}" data-ajax="update">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="queue_strategy_id" class="form-label">
                Queue Strategy
                @unless ($category->isQueueConfigured())<span class="tab-incomplete-dot"></span>@endunless
            </label>
            <select name="queue_strategy_id" id="queue_strategy_id" class="form-select" required>
                @foreach ($queueStrategies as $strategy)
                    <option value="{{ $strategy->id }}" data-code="{{ $strategy->code }}" @selected(old('queue_strategy_id', optional($category->categoryQueueSetting)->queue_strategy_id) == $strategy->id)>{{ $strategy->name }}</option>
                @endforeach
            </select>
            @if (optional($category->categoryQueueSetting->queueStrategy ?? null)->description)
                <div class="form-text">{{ $category->categoryQueueSetting->queueStrategy->description }}</div>
            @endif
        </div>

        @php
            $isSectionBased = $category->categoryQueueSetting?->queueStrategy?->code === 'SECTION_BASED';
            $sectionOrder = $category->categoryQueueSetting?->sectionOrder() ?? [];
        @endphp

        {{--
            Section Order — one input field holding every section segment
            ("4C – 4A – 4B – __"). A new empty segment appears once the last
            one is filled; clearing a segment removes it. Saved by the form's
            own Save button. Only shown while Section Based is selected.
        --}}
        <div class="mb-3" data-section-order-field @unless ($isSectionBased) hidden @endunless>
            <label class="form-label" for="section-order-last">Section Order</label>
            <div class="form-control section-order-box" data-section-order data-error-for="section_order">
                @foreach ([...$sectionOrder, ''] as $index => $section)
                    @if ($index > 0)<span class="section-order-sep" aria-hidden="true">&ndash;</span>@endif
                    <input type="text" name="section_order[]" class="section-order-segment" value="{{ $section }}"
                           maxlength="2" autocomplete="off" autocapitalize="characters" aria-label="Section {{ $index + 1 }}"
                           data-section-input @if ($loop->last) id="section-order-last" placeholder="e.g. 4A" @endif>
                @endforeach
            </div>
        </div>

        <div class="mb-4">
            <label for="called_waiting_minutes" class="form-label">Called-Group Waiting Period (minutes)</label>
            <input type="number" name="called_waiting_minutes" id="called_waiting_minutes" class="form-control" min="1" max="120" required
                   value="{{ old('called_waiting_minutes', optional($category->categoryQueueSetting)->called_waiting_minutes ?? 5) }}">
        </div>

        <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save Queue Configuration</button>
    </form>
</div>

@include('partials.section-input-script')

@push('styles')
    <style>
        .section-order-box {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem 0.35rem;
            cursor: text;
            min-height: calc(1.5em + 0.75rem + 2px);
        }

        .section-order-segment {
            width: 2.6rem;
            padding: 0.05rem 0;
            border: 0;
            border-radius: 0.3rem;
            background: var(--brand-accent-tint);
            color: var(--brand-text);
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
        }

        .section-order-segment:placeholder-shown {
            width: 3.6rem;
            background: transparent;
            text-align: left;
        }

        .section-order-segment::placeholder {
            color: var(--brand-muted);
            font-weight: 400;
            text-transform: none;
        }

        .section-order-segment:focus {
            outline: 2px solid var(--brand-accent);
            outline-offset: 0;
        }

        .section-order-sep {
            color: var(--brand-muted);
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var box = document.querySelector('[data-section-order]');
            var field = document.querySelector('[data-section-order-field]');
            var select = document.getElementById('queue_strategy_id');
            if (! box || ! field || ! select) return;

            var sectionPattern = /^[1-9][A-Z]$/;

            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                field.hidden = ! option || option.dataset.code !== 'SECTION_BASED';
            });

            function segments() {
                return Array.prototype.slice.call(box.querySelectorAll('.section-order-segment'));
            }

            function syncLastSegment() {
                segments().forEach(function (input, index, all) {
                    var isLast = index === all.length - 1;
                    input.id = isLast ? 'section-order-last' : '';
                    input.placeholder = isLast ? 'e.g. 4A' : '';
                    input.setAttribute('aria-label', 'Section ' + (index + 1));
                });
            }

            function appendSegment() {
                var separator = document.createElement('span');
                separator.className = 'section-order-sep';
                separator.setAttribute('aria-hidden', 'true');
                separator.innerHTML = '&ndash;';

                var input = document.createElement('input');
                input.type = 'text';
                input.name = 'section_order[]';
                input.className = 'section-order-segment';
                input.maxLength = 2;
                input.autocomplete = 'off';
                input.setAttribute('autocapitalize', 'characters');
                input.setAttribute('data-section-input', '');

                box.appendChild(separator);
                box.appendChild(input);
                syncLastSegment();

                return input;
            }

            function removeSegment(input) {
                var separator = input.previousElementSibling && input.previousElementSibling.classList.contains('section-order-sep')
                    ? input.previousElementSibling
                    : input.nextElementSibling;

                if (separator && separator.classList.contains('section-order-sep')) separator.remove();
                input.remove();
                syncLastSegment();
            }

            box.addEventListener('input', function (event) {
                var input = event.target;
                if (! input.classList.contains('section-order-segment')) return;

                // This listener fires before the shared document-level
                // formatter (the event reaches the box first), so format here.
                var formatted = input.value.toUpperCase().replace(/[\s-]+/g, '');
                if (formatted !== input.value) input.value = formatted;
                if (! sectionPattern.test(input.value)) return;

                var all = segments();
                var index = all.indexOf(input);
                var next = index === all.length - 1 ? appendSegment() : all[index + 1];
                next.focus();
            });

            box.addEventListener('keydown', function (event) {
                var input = event.target;
                if (! input.classList.contains('section-order-segment')) return;

                var all = segments();
                var index = all.indexOf(input);

                if (event.key === 'Backspace' && input.value === '' && index > 0) {
                    event.preventDefault();
                    var previous = all[index - 1];
                    if (index < all.length - 1) removeSegment(input);
                    previous.focus();
                    previous.setSelectionRange(previous.value.length, previous.value.length);
                }
            });

            box.addEventListener('focusout', function (event) {
                var input = event.target;
                if (! input.classList.contains('section-order-segment')) return;

                var all = segments();
                if (input.value === '' && all.indexOf(input) < all.length - 1) removeSegment(input);
            });

            box.addEventListener('mousedown', function (event) {
                if (event.target !== box) return;
                event.preventDefault();
                var all = segments();
                all[all.length - 1].focus();
            });
        })();
    </script>
@endpush
