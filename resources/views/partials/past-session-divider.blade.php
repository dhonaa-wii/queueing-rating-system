{{--
    The red line above the first group whose live expected time runs past the
    room's session end (AttemptSchedule::markPastSessionEnd()). Inside a table
    pass $colspan; elsewhere it renders as a block.
    Params: $sessionEnd (Carbon|null), $forStudents (bool), $colspan (int|null).
--}}
@php
    $pastSessionText = \App\Models\AttemptSchedule::pastSessionLine($sessionEnd ?? null, (bool) ($forStudents ?? false));
@endphp
@if (! empty($colspan))
    <tr class="past-session-divider">
        <td colspan="{{ $colspan }}"><div class="past-session-divider-line">{{ $pastSessionText }}</div></td>
    </tr>
@else
    <div class="past-session-divider"><div class="past-session-divider-line">{{ $pastSessionText }}</div></div>
@endif
