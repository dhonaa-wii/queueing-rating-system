{{--
    Dashboard list rows: the red past-session line above the first row of a
    run whose live expected time runs past its room's session end.
    Params: $row, $previous (the row before it, or null).
--}}
@if (($row['sessionEnd'] ?? null) && ! ($previous['sessionEnd'] ?? null))
    @include('partials.past-session-divider', ['sessionEnd' => $row['sessionEnd'], 'forStudents' => false])
@endif
