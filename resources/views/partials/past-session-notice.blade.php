{{--
    One group on its own whose live expected time runs past its room's session
    end (AttemptSchedule::markPastSessionEnd()).
    Params: $sessionEnd (Carbon|null), $forStudents (bool).
--}}
<div class="past-session-notice">
    <x-icon name="alert-triangle" />
    <span>{{ \App\Models\AttemptSchedule::pastSessionNotice($sessionEnd ?? null, (bool) ($forStudents ?? false)) }}</span>
</div>
