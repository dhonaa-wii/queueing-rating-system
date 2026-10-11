{{--
    Public room display (no panelist on this screen). Panelists join from
    their own account (panelist.live-room.*), so this only shows how full the
    room's panel is. The count is kept live by home.blade.php's poll.
--}}
<div class="card-brand p-2 text-center">
    <div class="small text-brand-muted mb-1">Panel</div>
    <span class="badge {{ $seats['full'] ? 'badge-success-tint' : 'badge-muted-tint' }}">
        {{ $seats['full'] ? 'Panel complete' : 'Panelists' }} &middot; <span id="seat-count">{{ $seats['taken'] }}/{{ $seats['total'] }}</span>
    </span>
</div>
