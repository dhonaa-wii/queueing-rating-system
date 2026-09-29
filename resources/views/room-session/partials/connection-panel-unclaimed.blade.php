<div class="card-brand p-2 text-center">
    <h2 class="h6 mb-2">Scan to Join</h2>
    {{-- Width lives in home.blade.php's stylesheet, not inline: it scales with
         the column, and an inline max-width would override the stylesheet. --}}
    <div class="mb-2 d-flex justify-content-center" id="qr-code-container">{!! $qrSvg !!}</div>
    <p class="text-brand-muted mb-2" style="font-size: 0.75rem;">Scan with your phone, already logged into your panelist account.</p>

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
