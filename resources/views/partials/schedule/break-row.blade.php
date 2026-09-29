{{--
    A break line inside a schedule list: one cell across the whole row with the
    break's title centered and its start and end time beside it on one line.
    $span is the table's total column count. $rowAttrs is optional extra
    markup for the row (the roster's tab/room filter hooks).
--}}
<tr class="schedule-break-row" {!! $rowAttrs ?? '' !!}>
    <td colspan="{{ $span }}" class="text-center text-nowrap">
        <span class="fw-semibold">{{ $break->name ?: 'Break' }}</span>
        <span class="ms-2">{{ $break->planned_start_at->format('g:i A') }} &ndash; {{ $break->planned_end_at->format('g:i A') }}</span>
    </td>
</tr>
