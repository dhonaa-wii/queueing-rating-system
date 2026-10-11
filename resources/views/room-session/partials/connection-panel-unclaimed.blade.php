{{--
    Sign-in card for a device with nobody signed in on it. While the room still
    has a free seat: the QR code plus the username/password form. Once every
    seat is taken (user-directed 2026-10-11) the sign-in is muted to "Panel
    complete"; only Reconnect remains, which a panelist already on this panel
    uses to move their seat to this device (TerminalConnectionService::
    connectToRoom() refuses anyone else).
--}}
@if ($seats['full'])
    <div class="card-brand p-2 text-center">
        <span class="badge badge-success-tint mb-2">Panel complete &middot; {{ $seats['taken'] }}/{{ $seats['total'] }}</span>

        <details class="rs-reconnect text-start mt-1">
            <summary class="small text-brand-muted" style="cursor: pointer;">Reconnect</summary>
            <form method="POST" action="{{ route('room-session.manual-login') }}" class="mt-2">
                @csrf
                <div class="mb-2">
                    <label class="form-label small mb-1">Username</label>
                    <input type="text" name="username" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Password</label>
                    <input type="password" name="password" class="form-control form-control-sm" required>
                </div>
                <button type="submit" class="btn btn-sm btn-brand w-100"><x-icon name="log-in" /> Reconnect</button>
            </form>
        </details>
    </div>
@else
    <div class="card-brand p-2 text-center">
        <h2 class="h6 mb-2">Scan to Join</h2>
        {{-- Width lives in home.blade.php's stylesheet, not inline: it scales with
             the column, and an inline max-width would override the stylesheet. --}}
        <div class="mb-2 d-flex justify-content-center" id="qr-code-container">{!! $qrSvg !!}</div>
        <p class="text-brand-muted mb-2" style="font-size: 0.75rem;">Scan with your phone, already logged into your panelist account.</p>

        <span class="badge badge-muted-tint mb-2">Panel &middot; <span id="seat-count">{{ $seats['taken'] }}/{{ $seats['total'] }}</span></span>

        <hr class="my-2" style="border-color: var(--brand-border);">

        <h3 class="h6 mt-2 mb-2">Or Log In Directly</h3>
        <form method="POST" action="{{ route('room-session.manual-login') }}" class="text-start">
            @csrf
            <div class="mb-2">
                <label class="form-label small mb-1">Username</label>
                <input type="text" name="username" class="form-control form-control-sm" required>
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1">Password</label>
                <input type="password" name="password" class="form-control form-control-sm" required>
            </div>
            <button type="submit" class="btn btn-sm btn-brand w-100"><x-icon name="log-in" /> Log In</button>
        </form>
    </div>
@endif
