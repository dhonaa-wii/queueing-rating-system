{{--
    Expects: $version, $sections, $scoreLookup, $proposedTitleId (nullable —
    only set in Title Proposal mode, one call per registered title),
    $readOnly

    Live-fill counterpart to admin/evaluation-library/partials/criteria-table.blade.php's
    read-only ($tableReadOnly = true) rendering — same table shape, same CSS
    classes, but the rating cell is a real clickable control instead of a
    plain span.
--}}
<table class="eval-paper-criteria-table">
    <thead>
        <tr>
            <th style="width: 62%;">Performance Indicators <span class="text-brand-muted fw-normal">(Group Rating)</span></th>
            <th>Rating ({{ $version->scale_min }}&ndash;{{ $version->scale_max }})</th>
        </tr>
    </thead>
    <tbody>
        @if ($version->scaleLabels->isNotEmpty())
            <tr class="eval-legend-row">
                <td colspan="2">
                    @foreach ($version->scaleLabels as $label)
                        <strong>{{ rtrim(rtrim(number_format((float) $label->value, 2), '0'), '.') }}</strong> &ndash; {{ $label->label }}@if (! $loop->last)&nbsp;&nbsp;&nbsp;@endif
                    @endforeach
                </td>
            </tr>
        @endif

        @forelse ($sections as $section)
            @php $items = $section->childCriteria->sortBy('sort_order')->values(); @endphp
            <tr class="eval-section-row">
                <td colspan="2">
                    {{ $loop->iteration }}. {{ $section->name }}
                    ({{ rtrim(rtrim(number_format($section->weight ?? 0, 2), '0'), '.') }}%)
                </td>
            </tr>
            @foreach ($items as $item)
                @php
                    $scoreKey = $item->id . ':' . ($proposedTitleId ?? '0');
                    $selectedScore = $scoreLookup->get($scoreKey)?->score;
                @endphp
                <tr class="eval-item-row">
                    <td class="ps-4">{{ $loop->parent->iteration }}.{{ $loop->iteration }} {{ $item->name }}</td>
                    <td>
                        @for ($v = $version->scale_min; $v <= $version->scale_max; $v++)
                            @php $isSelected = $selectedScore !== null && (int) round($selectedScore) === $v; @endphp
                            @if ($readOnly)
                                <span class="eval-rating-bubble {{ $isSelected ? 'is-selected' : '' }}">{{ $v }}</span>
                            @else
                                <button type="button" class="eval-rating-bubble {{ $isSelected ? 'is-selected' : '' }}"
                                        data-score-btn
                                        data-criterion-id="{{ $item->id }}"
                                        data-proposed-title-id="{{ $proposedTitleId ?? '' }}"
                                        data-value="{{ $v }}">{{ $v }}</button>
                            @endif
                        @endfor
                    </td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="2" class="text-brand-muted text-center py-4">No criteria configured on this form.</td></tr>
        @endforelse
    </tbody>
</table>
