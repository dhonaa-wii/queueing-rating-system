{{--
    Expects: $evaluationForm, $editingVersion, $sections, $tableReadOnly

    The performance-indicator criteria/rating table. Extracted out of
    paper.blade.php so Title Proposal mode can render it once per proposed
    title (see paper.blade.php) without duplicating the edit forms/modals —
    only the first ($tableReadOnly === false) render ever carries those, so
    section/item ids never collide across repeats.
--}}
<table class="eval-paper-criteria-table">
    <thead>
        <tr>
            <th style="width: 62%;">Performance Indicators <span class="text-brand-muted fw-normal">(Group Rating)</span></th>
            <th>Rating ({{ $editingVersion->scale_min }}&ndash;{{ $editingVersion->scale_max }})</th>
        </tr>
    </thead>
    <tbody>
        @if ($editingVersion->scaleLabels->isNotEmpty())
            <tr class="eval-legend-row">
                <td colspan="2">
                    @foreach ($editingVersion->scaleLabels as $label)
                        <strong>{{ rtrim(rtrim(number_format((float) $label->value, 2), '0'), '.') }}</strong> &ndash; {{ $label->label }}@if (! $loop->last)&nbsp;&nbsp;&nbsp;@endif
                    @endforeach
                </td>
            </tr>
        @endif

        @forelse ($sections as $section)
            @php $items = $section->childCriteria->sortBy('sort_order')->values(); @endphp
            <tr class="eval-section-row" data-eval-row data-row-type="section" data-row-id="{{ $section->id }}">
                <td colspan="2">
                    @if ($tableReadOnly)
                        {{ $loop->iteration }}. {{ $section->name }}
                        ({{ rtrim(rtrim(number_format($section->weight ?? 0, 2), '0'), '.') }}%)
                    @else
                        <form method="POST" action="{{ route('admin.evaluation-library.sections.update', [$evaluationForm, $editingVersion, $section]) }}" class="eval-inline-form" data-ajax="update">
                            @csrf
                            @method('PUT')
                            <span class="eval-row-number">{{ $loop->iteration }}.</span>
                            <input type="text" name="name" value="{{ $section->name }}" maxlength="200" required
                                   class="eval-plain-input eval-section-name-input" data-autosave-input placeholder="Section name">
                            <span>(</span><input type="number" step="0.01" min="0" max="100" name="weight"
                                   value="{{ rtrim(rtrim(number_format((float) ($section->weight ?? 0), 2), '0'), '.') }}"
                                   class="eval-plain-input eval-weight-input" data-autosave-input><span>%)</span>
                        </form>
                        <div class="eval-row-actions">
                            <form method="POST" action="{{ route('admin.evaluation-library.sections.move', [$evaluationForm, $editingVersion, $section]) }}" data-ajax="refresh">
                                @csrf
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="eval-row-action-btn" title="Move up" @disabled($loop->first)>&uarr;</button>
                            </form>
                            <form method="POST" action="{{ route('admin.evaluation-library.sections.move', [$evaluationForm, $editingVersion, $section]) }}" data-ajax="refresh">
                                @csrf
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="eval-row-action-btn" title="Move down" @disabled($loop->last)>&darr;</button>
                            </form>
                            <button type="button" class="eval-row-action-btn eval-row-action-danger" title="Remove section"
                                    data-bs-toggle="modal" data-bs-target="#remove-section-modal-{{ $section->id }}">&times;</button>
                        </div>
                    @endif
                </td>
            </tr>
            @foreach ($items as $item)
                <tr class="eval-item-row" data-eval-row data-row-type="item" data-row-id="{{ $item->id }}" data-section-id="{{ $section->id }}">
                    <td class="ps-4">
                        @if ($tableReadOnly)
                            {{ $loop->parent->iteration }}.{{ $loop->iteration }} {{ $item->name }}
                        @else
                            <form method="POST" action="{{ route('admin.evaluation-library.items.update', [$evaluationForm, $editingVersion, $item]) }}" class="eval-inline-form" data-ajax="update">
                                @csrf
                                @method('PUT')
                                <span class="eval-row-number">{{ $loop->parent->iteration }}.{{ $loop->iteration }}</span>
                                <input type="text" name="name" value="{{ $item->name }}" maxlength="200" required
                                       class="eval-plain-input eval-item-name-input" data-autosave-input placeholder="Item prompt">
                            </form>
                            <div class="eval-row-actions">
                                <form method="POST" action="{{ route('admin.evaluation-library.items.move', [$evaluationForm, $editingVersion, $item]) }}" data-ajax="refresh">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="eval-row-action-btn" title="Move up" @disabled($loop->first)>&uarr;</button>
                                </form>
                                <form method="POST" action="{{ route('admin.evaluation-library.items.move', [$evaluationForm, $editingVersion, $item]) }}" data-ajax="refresh">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="eval-row-action-btn" title="Move down" @disabled($loop->last)>&darr;</button>
                                </form>
                                <button type="button" class="eval-row-action-btn eval-row-action-danger" title="Remove item"
                                        data-bs-toggle="modal" data-bs-target="#remove-item-modal-{{ $item->id }}">&times;</button>
                            </div>
                        @endif
                    </td>
                    <td>
                        @for ($v = $editingVersion->scale_min; $v <= $editingVersion->scale_max; $v++)
                            <span class="eval-rating-bubble">{{ $v }}</span>
                        @endfor
                    </td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="2" class="text-brand-muted text-center py-4">No sections yet &mdash; use "Add Row" below to start.</td></tr>
        @endforelse
    </tbody>

    @unless ($tableReadOnly)
        @php $lastSection = $sections->last(); @endphp
        <tfoot>
            <tr>
                <td colspan="2" class="eval-add-row-cell">
                    <div class="eval-add-row-toolbar">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <span class="text-brand-muted small me-1">Add row:</span>

                        <button type="button" class="btn btn-sm btn-outline-brand" data-add-row
                                data-kind="section" data-position="before"
                                data-create-url="{{ route('admin.evaluation-library.sections.store', [$evaluationForm, $editingVersion]) }}"
                                data-create-value="New Section">
                            + Criteria (Above)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-brand" data-add-row
                                data-kind="section" data-position="after"
                                data-create-url="{{ route('admin.evaluation-library.sections.store', [$evaluationForm, $editingVersion]) }}"
                                data-create-value="New Section">
                            + Criteria (Below)
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-brand" data-add-row
                                data-kind="item" data-position="before"
                                data-create-url-template="{{ route('admin.evaluation-library.items.store', [$evaluationForm, $editingVersion, 'REPLACE_SECTION_ID']) }}"
                                data-create-value="New Criterion"
                                data-fallback-section-id="{{ $lastSection?->id }}" @disabled(! $lastSection)>
                            + Rating (Above)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-brand" data-add-row
                                data-kind="item" data-position="after"
                                data-create-url-template="{{ route('admin.evaluation-library.items.store', [$evaluationForm, $editingVersion, 'REPLACE_SECTION_ID']) }}"
                                data-create-value="New Criterion"
                                data-fallback-section-id="{{ $lastSection?->id }}" @disabled(! $lastSection)>
                            + Rating (Below)
                        </button>
                    </div>
                </td>
            </tr>
        </tfoot>
    @endunless
</table>

@unless ($tableReadOnly)
    @foreach ($sections as $section)
        <div class="modal fade" id="remove-section-modal-{{ $section->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Remove Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Remove section <strong>{{ $section->name }}</strong> and all its items?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="{{ route('admin.evaluation-library.sections.destroy', [$evaluationForm, $editingVersion, $section]) }}" data-ajax="refresh">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Remove</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @foreach ($section->childCriteria->sortBy('sort_order')->values() as $item)
            <div class="modal fade" id="remove-item-modal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Remove Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Remove item <strong>{{ $item->name }}</strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <form method="POST" action="{{ route('admin.evaluation-library.items.destroy', [$evaluationForm, $editingVersion, $item]) }}" data-ajax="refresh">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach
@endunless
