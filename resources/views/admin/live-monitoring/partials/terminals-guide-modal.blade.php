{{-- Help for the Room Data card's Terminals table, opened by the "i" beside
     its heading. Rendered once per page (show.blade.php) since every room tab
     shares it. A modal rather than a page: it is reference for the table the
     admin is looking at, and leaving Event Control mid-event to read it would
     lose their place. Copy says "device" (any tablet/laptop the room uses);
     the artwork and icons draw a tablet because that is the usual one. --}}
<div class="modal fade" id="terminals-guide-modal" tabindex="-1" aria-labelledby="terminals-guide-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content tg">
            <div class="tg-header">
                <div class="tg-header-icon"><x-icon name="tablet" /></div>
                <div class="min-w-0">
                    <h5 class="tg-title" id="terminals-guide-title">How Terminals Work</h5>
                    <p class="tg-subtitle">A terminal is one device seat in the room, one for each panelist on the panel.</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body tg-body">
                {{-- Room at a glance: the Lead's seat, then the two ways a
                     panelist gets onto a seat. --}}
                <div class="tg-hero" aria-hidden="true">
                    <svg viewBox="0 0 440 168" class="tg-hero-art">
                        @foreach ([[30, '1', 'lead'], [158, '2', 'typed'], [286, '3', 'qr']] as [$x, $n, $kind])
                            <g transform="translate({{ $x }} 14)">
                                <rect class="tg-art-device" x="0" y="0" width="96" height="120" rx="12"/>
                                <rect class="tg-art-screen" x="8" y="9" width="80" height="94" rx="5"/>
                                <rect class="tg-art-home" x="40" y="108" width="16" height="3" rx="1.5"/>
                                @if ($kind === 'lead')
                                    <circle class="tg-art-avatar" cx="48" cy="42" r="13"/>
                                    <path class="tg-art-avatar-body" d="M27 76a21 15 0 0 1 42 0Z"/>
                                    <path class="tg-art-crown" d="M38 24l3-7 4 4 3-6 3 6 4-4 3 7Z"/>
                                @elseif ($kind === 'typed')
                                    {{-- Username / password form --}}
                                    <rect class="tg-art-field" x="18" y="24" width="60" height="13" rx="3"/>
                                    <rect class="tg-art-field-text" x="23" y="29" width="28" height="3" rx="1.5"/>
                                    <rect class="tg-art-field" x="18" y="43" width="60" height="13" rx="3"/>
                                    @foreach ([25, 32, 39, 46, 53] as $dot)
                                        <circle class="tg-art-field-dot" cx="{{ $dot }}" cy="49.5" r="1.8"/>
                                    @endforeach
                                    <rect class="tg-art-field-btn" x="18" y="63" width="60" height="13" rx="3"/>
                                @else
                                    <g transform="translate(26 26)" class="tg-art-qr">
                                        <rect x="0" y="0" width="13" height="13" rx="2"/>
                                        <rect x="31" y="0" width="13" height="13" rx="2"/>
                                        <rect x="0" y="31" width="13" height="13" rx="2"/>
                                        <rect x="19" y="19" width="6" height="6" rx="1"/>
                                        <rect x="31" y="31" width="5" height="5" rx="1"/>
                                        <rect x="39" y="39" width="5" height="5" rx="1"/>
                                        <rect x="19" y="4" width="6" height="6" rx="1"/>
                                        <rect x="4" y="19" width="6" height="6" rx="1"/>
                                    </g>
                                @endif
                                <circle class="tg-art-badge" cx="88" cy="0" r="11"/>
                                <text class="tg-art-badge-text" x="88" y="4" text-anchor="middle">{{ $n }}</text>
                                <text class="tg-art-caption {{ $kind === 'lead' ? 'tg-art-caption-ok' : '' }}" x="48" y="142" text-anchor="middle">
                                    {{ ['lead' => 'Lead connected', 'typed' => 'Manual login', 'qr' => 'Scan QR code'][$kind] }}
                                </text>
                            </g>
                        @endforeach
                        {{-- Panelist's own phone scanning terminal 3 --}}
                        <path class="tg-art-beam" d="M407 84 C 412 66, 400 52, 384 50"/>
                        <g transform="translate(392 86)">
                            <rect class="tg-art-device tg-art-phone" x="0" y="0" width="32" height="50" rx="7"/>
                            <rect class="tg-art-screen" x="4" y="5" width="24" height="36" rx="3"/>
                            <path class="tg-art-check" d="M10 23l4 4 8-8"/>
                        </g>
                    </svg>
                </div>

                <h6 class="tg-section-title">Getting a room ready</h6>
                <ol class="tg-timeline">
                    <li class="tg-tl-item">
                        <div class="tg-tl-marker">1</div>
                        <div class="tg-tl-body">
                            <div class="tg-tl-head"><x-icon name="play" /> Start the room</div>
                            <p class="tg-step-text">Start Room creates one terminal per panelist and the room's login account, shown above in this card. The account is new each day and is removed when the room ends.</p>
                        </div>
                    </li>
                    <li class="tg-tl-item">
                        <div class="tg-tl-marker">2</div>
                        <div class="tg-tl-body">
                            <div class="tg-tl-head"><x-icon name="tablet" /> Set up each device</div>
                            <p class="tg-step-text">On the device, open <strong>Room Session</strong>, sign in with the room's username and password, then pick its terminal number.</p>
                            <div class="tg-result">Device <x-icon name="arrow-right" /> <span class="badge badge-brand-tint">Claimed</span></div>
                        </div>
                    </li>
                    <li class="tg-tl-item">
                        <div class="tg-tl-marker">3</div>
                        <div class="tg-tl-body">
                            <div class="tg-tl-head"><x-icon name="user-check" /> Panelist connects, using either way</div>
                            <div class="tg-options">
                                <div class="tg-option">
                                    <div class="tg-option-icon"><x-icon name="qr-code" /></div>
                                    <div>
                                        <div class="tg-option-title">Scan QR Code</div>
                                        <p class="tg-step-text">Scan the device's QR code with a phone already signed in to the panelist's own account, then confirm.</p>
                                    </div>
                                </div>
                                <div class="tg-option-or" aria-hidden="true">or</div>
                                <div class="tg-option">
                                    <div class="tg-option-icon"><x-icon name="key" /></div>
                                    <div>
                                        <div class="tg-option-title">Manual Login</div>
                                        <p class="tg-step-text">Enter the panelist's own username and password in the device's <strong>Log In Directly</strong> form.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="tg-result">Status <x-icon name="arrow-right" /> <span class="badge badge-success-tint">Connected</span></div>
                        </div>
                    </li>
                    <li class="tg-tl-item">
                        <div class="tg-tl-marker">4</div>
                        <div class="tg-tl-body">
                            <div class="tg-tl-head"><x-icon name="crown" /> The Lead runs the queue</div>
                            <p class="tg-step-text">The panelist assigned as Lead gets Call Next, Start and Complete on whichever device they use. Call Next stays locked until every terminal is connected.</p>
                        </div>
                    </li>
                    <li class="tg-tl-item">
                        <div class="tg-tl-marker">5</div>
                        <div class="tg-tl-body">
                            <div class="tg-tl-head"><x-icon name="clipboard-check" /> Panelists evaluate</div>
                            <p class="tg-step-text">Each panelist rates the group and submits their evaluation on their own device. Complete stays locked until every panelist's evaluation is submitted, and the Lead's remark becomes the group's official outcome.</p>
                        </div>
                    </li>
                </ol>

                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="tg-section-title">Reading the table</h6>
                        <div class="tg-panel">
                            <div class="tg-legend-group">Device</div>
                            <div class="tg-legend-row">
                                <span class="badge badge-muted-tint">Unclaimed</span>
                                <span>No device has taken this terminal number yet.</span>
                            </div>
                            <div class="tg-legend-row">
                                <span class="badge badge-brand-tint">Claimed</span>
                                <span>A device is set up as this terminal.</span>
                            </div>
                            <div class="tg-legend-group">Status</div>
                            <div class="tg-legend-row">
                                <span class="badge badge-muted-tint">Empty</span>
                                <span>No panelist is signed in on the seat.</span>
                            </div>
                            <div class="tg-legend-row">
                                <span class="badge badge-success-tint">Connected</span>
                                <span>The panelist named in the row is on the seat.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="tg-section-title">Your actions</h6>
                        <div class="tg-panel">
                            <div class="tg-action">
                                <div class="tg-option-icon tg-action-icon-danger"><x-icon name="unlink" /></div>
                                <div>
                                    <div class="tg-option-title">Disconnect</div>
                                    <p class="tg-step-text">Signs the panelist out of the seat. The device stays set up for the next panelist.</p>
                                </div>
                            </div>
                            <div class="tg-action">
                                <div class="tg-option-icon"><x-icon name="log-out" /></div>
                                <div>
                                    <div class="tg-option-title">Release Device</div>
                                    <p class="tg-step-text">Frees the terminal number. The device has to sign in with the room account again, and anyone on it is signed out.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tg-note">
                    <x-icon name="shield-check" />
                    <span>A backup who connects takes an assigned panelist's seat right away. Any other panelist can connect, but taking a seat needs an Administrator to confirm it.</span>
                </div>
            </div>

            <div class="modal-footer">
                <a href="{{ route('help') }}#room-devices" class="btn btn-outline-brand me-auto" target="_blank" rel="noopener"><x-icon name="book-open" /> More in the User Guide</a>
                <button type="button" class="btn btn-brand" data-bs-dismiss="modal"><x-icon name="check" /> Got it</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    #terminals-guide-modal {
        --bs-modal-bg: var(--brand-surface);
    }
    /* The modal theme lifts muted badges to --brand-surface, which is this
       modal's own background — give them back a visible fill. */
    #terminals-guide-modal .badge-muted-tint {
        background-color: var(--brand-surface-alt);
        box-shadow: inset 0 0 0 1px var(--brand-border);
    }
    .tg-header {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        padding: 1.1rem var(--modal-pad-x, 1.25rem) 0.9rem;
        border-bottom: 1px solid var(--brand-border);
    }
    .tg-header-icon {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 2.6rem;
        height: 2.6rem;
        border-radius: 0.75rem;
        background: var(--brand-accent-tint);
        color: var(--brand-accent);
    }
    .tg-header-icon svg { width: 1.35rem; height: 1.35rem; }
    .tg-title {
        margin: 0.1rem 0 0.15rem;
        font-size: var(--modal-fs-title, 1.06rem);
        font-weight: 600;
    }
    .tg-subtitle {
        margin: 0;
        color: var(--brand-muted);
        font-size: var(--modal-fs-sm, 0.82rem);
    }
    .tg-body { padding-top: 1rem; }

    /* Illustration */
    .tg-hero {
        border: 1px solid var(--brand-border);
        border-radius: 0.85rem;
        padding: 0.75rem 0.5rem 0.35rem;
        margin-bottom: 1.35rem;
        background:
            radial-gradient(circle at 1px 1px, var(--brand-border) 1px, transparent 0) 0 0 / 16px 16px,
            var(--brand-surface);
    }
    .tg-hero-art { display: block; width: 100%; max-width: 30rem; height: auto; margin: 0 auto; }
    .tg-art-device { fill: var(--brand-surface); stroke: var(--brand-text); stroke-width: 2; }
    .tg-art-phone { stroke-width: 1.8; }
    .tg-art-screen { fill: var(--brand-surface-alt); stroke: var(--brand-border); stroke-width: 1; }
    .tg-art-home { fill: var(--brand-border); }
    .tg-art-qr rect { fill: var(--brand-text); }
    .tg-art-avatar,
    .tg-art-avatar-body { fill: var(--brand-success-tint); stroke: var(--brand-success); stroke-width: 1.6; }
    .tg-art-crown { fill: var(--brand-accent); }
    .tg-art-field { fill: var(--brand-surface); stroke: var(--brand-control-border); stroke-width: 1; }
    .tg-art-field-text,
    .tg-art-field-dot { fill: var(--brand-text); }
    .tg-art-field-btn { fill: var(--brand-accent); }
    .tg-art-caption { font-size: 10.5px; font-weight: 600; fill: var(--brand-muted); }
    .tg-art-caption-ok { fill: var(--brand-success); }
    .tg-art-badge { fill: var(--brand-accent); stroke: var(--brand-surface); stroke-width: 3; }
    .tg-art-badge-text { font-size: 11px; font-weight: 700; fill: var(--brand-accent-contrast); }
    .tg-art-beam { fill: none; stroke: var(--brand-accent); stroke-width: 1.6; stroke-dasharray: 3 4; }
    .tg-art-check { fill: none; stroke: var(--brand-success); stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }

    .tg-section-title {
        font-size: var(--modal-fs-xs, 0.72rem);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--brand-muted);
        margin: 0 0 0.7rem;
    }

    /* Timeline: numbered markers joined by a rail, so the order reads at a glance. */
    .tg-timeline {
        list-style: none;
        padding: 0;
        margin: 0 0 1.4rem;
    }
    .tg-tl-item {
        position: relative;
        display: flex;
        gap: 0.9rem;
        padding-bottom: 1rem;
    }
    .tg-tl-item:last-child { padding-bottom: 0; }
    .tg-tl-item:not(:last-child)::before {
        content: "";
        position: absolute;
        left: 0.8rem;
        top: 1.75rem;
        bottom: 0.2rem;
        width: 2px;
        margin-left: -1px;
        background: var(--brand-border);
    }
    .tg-tl-marker {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 1.6rem;
        height: 1.6rem;
        border-radius: 50%;
        background: var(--brand-accent);
        color: var(--brand-accent-contrast);
        font-size: 0.78rem;
        font-weight: 700;
        box-shadow: 0 0 0 4px var(--brand-accent-tint);
    }
    .tg-tl-body {
        flex: 1;
        min-width: 0;
        padding-top: 0.1rem;
    }
    .tg-tl-head {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-weight: 600;
        font-size: var(--modal-fs, 0.9rem);
        margin-bottom: 0.2rem;
    }
    .tg-tl-head svg { width: 1rem; height: 1rem; color: var(--brand-accent); flex-shrink: 0; }
    .modal-body p.tg-step-text {
        margin: 0;
        color: var(--brand-muted);
        font-size: var(--modal-fs-sm, 0.82rem);
        line-height: 1.5;
    }
    .tg-result {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-top: 0.45rem;
        font-size: var(--modal-fs-xs, 0.72rem);
        font-weight: 600;
        color: var(--brand-muted);
    }
    .tg-result svg { width: 0.85rem; height: 0.85rem; }

    /* Step 3's two ways in, side by side with an "or" between. */
    .tg-options {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: stretch;
        gap: 0.6rem;
        margin-top: 0.5rem;
    }
    .tg-option,
    .tg-action {
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
    }
    .tg-option {
        padding: 0.75rem 0.8rem;
        border: 1px solid var(--brand-border);
        border-radius: 0.75rem;
        background: var(--brand-surface);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .tg-option:hover { border-color: var(--brand-accent); box-shadow: var(--brand-shadow); }
    .tg-option-or {
        align-self: center;
        font-size: var(--modal-fs-xs, 0.72rem);
        font-weight: 600;
        text-transform: uppercase;
        color: var(--brand-muted);
    }
    .tg-option-icon {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 2.1rem;
        height: 2.1rem;
        border-radius: 0.6rem;
        background: var(--brand-accent-tint);
        color: var(--brand-accent);
    }
    .tg-option-icon svg { width: 1.05rem; height: 1.05rem; }
    .tg-action-icon-danger { background: var(--brand-danger-tint); color: var(--brand-danger); }
    .tg-option-title {
        font-weight: 600;
        font-size: var(--modal-fs, 0.9rem);
        margin-bottom: 0.1rem;
    }

    .tg-panel {
        border: 1px solid var(--brand-border);
        border-radius: 0.75rem;
        padding: 0.75rem 0.85rem;
        height: calc(100% - 1.7rem);
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .tg-legend-group {
        font-size: var(--modal-fs-xs, 0.72rem);
        font-weight: 600;
        color: var(--brand-text);
    }
    .tg-legend-group:not(:first-child) { margin-top: 0.25rem; padding-top: 0.55rem; border-top: 1px dashed var(--brand-border); }
    .tg-legend-row {
        display: grid;
        grid-template-columns: 5.6rem 1fr;
        align-items: center;
        gap: 0.6rem;
        font-size: var(--modal-fs-sm, 0.82rem);
        color: var(--brand-muted);
    }
    .tg-legend-row .badge { justify-self: start; }
    .tg-action + .tg-action { padding-top: 0.65rem; border-top: 1px dashed var(--brand-border); }

    .tg-note {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin-top: 1rem;
        padding: 0.6rem 0.8rem;
        border-radius: 0.65rem;
        background: var(--brand-info-tint);
        color: var(--brand-info);
        font-size: var(--modal-fs-sm, 0.82rem);
    }
    .tg-note svg { width: 1rem; height: 1rem; flex-shrink: 0; }

    @media (max-width: 575.98px) {
        .tg-panel { height: auto; }
        .tg-hero { padding-inline: 0.25rem; }
        .tg-options { grid-template-columns: 1fr; }
        .tg-option-or { justify-self: center; }
    }
</style>
@endpush
