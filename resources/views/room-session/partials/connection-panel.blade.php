@if ($connection)
    @include('room-session.partials.connection-panel-connected')
@else
    @include('room-session.partials.connection-panel-unclaimed')
@endif
