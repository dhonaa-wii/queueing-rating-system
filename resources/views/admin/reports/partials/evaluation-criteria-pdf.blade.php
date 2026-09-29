{{--
    Dompdf counterpart of room-session/partials/evaluation-criteria-table
    (read-only). Expects $version, $sections, $scoreLookup, $proposedTitleId.
--}}
<table class="criteria">
    <thead>
        <tr>
            <th>Performance Indicators (Group Rating)</th>
            <th class="rating">Rating ({{ $version->scale_min }}&ndash;{{ $version->scale_max }})</th>
        </tr>
    </thead>
    <tbody>
        @if ($version->scaleLabels->isNotEmpty())
            <tr class="legend">
                <td colspan="2">
                    @foreach ($version->scaleLabels as $label)
                        <strong>{{ rtrim(rtrim(number_format((float) $label->value, 2), '0'), '.') }}</strong> &ndash; {{ $label->label }}@if (! $loop->last)&nbsp;&nbsp;&nbsp;@endif
                    @endforeach
                </td>
            </tr>
        @endif

        @forelse ($sections as $section)
            @php $items = $section->childCriteria->sortBy('sort_order')->values(); @endphp
            <tr class="section">
                <td colspan="2">{{ $loop->iteration }}. {{ $section->name }} ({{ rtrim(rtrim(number_format($section->weight ?? 0, 2), '0'), '.') }}%)</td>
            </tr>
            @foreach ($items as $item)
                @php $selectedScore = $scoreLookup->get($item->id . ':' . ($proposedTitleId ?? '0'))?->score; @endphp
                <tr class="item">
                    <td class="name">{{ $loop->parent->iteration }}.{{ $loop->iteration }} {{ $item->name }}</td>
                    <td class="rating">
                        @for ($v = $version->scale_min; $v <= $version->scale_max; $v++)
                            <span class="bub {{ $selectedScore !== null && (int) round($selectedScore) === $v ? 'on' : '' }}">{{ $v }}</span>
                        @endfor
                    </td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="2" style="text-align: center; padding: 12px;">No criteria configured on this form.</td></tr>
        @endforelse
    </tbody>
</table>
