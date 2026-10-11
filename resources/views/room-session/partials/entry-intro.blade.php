{{--
    Left-hand feature panel on the room-session entry screen.

    Optional: $introTitle overrides the headline.
--}}
<section class="rs-intro">
    <span class="rs-eyebrow"><x-icon name="monitor" /> Room Session</span>

    <h1 class="rs-title">{{ $introTitle ?? 'Open a running room on this device.' }}</h1>

    <p class="rs-lede">
        Any device can show a room's live queue. Panelists sign in on it with their own
        account to evaluate and, for the Chair, to run the presentations.
    </p>

    <ul class="rs-features">
        <li class="rs-feature">
            <span class="rs-feature-icon"><x-icon name="list-check" /></span>
            <div class="rs-feature-body">
                <p class="rs-feature-title">Live room queue</p>
                <p class="rs-feature-text">Current, called and next groups, with expected times that follow the room's real pace.</p>
            </div>
        </li>
        <li class="rs-feature">
            <span class="rs-feature-icon"><x-icon name="user-check" /></span>
            <div class="rs-feature-body">
                <p class="rs-feature-title">Panelists join with their own account</p>
                <p class="rs-feature-text">Scan the room's QR code or sign in on the device. The room takes as many panelists as its panel needs.</p>
            </div>
        </li>
        <li class="rs-feature">
            <span class="rs-feature-icon"><x-icon name="play" /></span>
            <div class="rs-feature-body">
                <p class="rs-feature-title">The panel controls the flow</p>
                <p class="rs-feature-text">The designated Chair calls, starts, pauses, completes and defers from the room.</p>
            </div>
        </li>
        <li class="rs-feature">
            <span class="rs-feature-icon"><x-icon name="file-text" /></span>
            <div class="rs-feature-body">
                <p class="rs-feature-title">Evaluations filed on the spot</p>
                <p class="rs-feature-text">Panelists score each group against the category's evaluation form as it presents.</p>
            </div>
        </li>
    </ul>
</section>
