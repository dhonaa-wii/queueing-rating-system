{{--
    Left-hand feature panel, shared by the three room-session entry screens so
    they read as one flow rather than three unrelated cards.

    Optional: $introTitle overrides the headline for a screen where "set this
    device up" is no longer the step in front of the user.
--}}
<section class="rs-intro">
    <span class="rs-eyebrow"><x-icon name="monitor" /> Room Terminal</span>

    <h1 class="rs-title">{{ $introTitle ?? 'Set this device up as a room terminal.' }}</h1>

    <p class="rs-lede">
        A claimed device becomes one of the room's terminals for the day — showing the
        room's live queue and letting the assigned panel run the presentations from it.
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
                <p class="rs-feature-text">Each seat is claimed by scanning its QR code or signing in on the terminal itself.</p>
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
