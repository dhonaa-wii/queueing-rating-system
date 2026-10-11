<div class="hc-topic" id="rd-what">
    <h3><x-icon name="tablet" /> What a room device is</h3>
    <p>Any device can show a room: a shared tablet, a panelist's own laptop, or the room's PC on a projector. Open <strong>Room Session</strong>, pick a running room, and the device shows that room's live queue. Any number of devices can watch the same room.</p>
    <p>A room has one <strong>seat</strong> for each panelist on its panel. A panelist takes a seat by signing in on a device with their own account. When every seat is taken the room is <strong>Panel complete</strong> and nobody else can sign in.</p>
    <div class="hc-panel">
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Viewer</span><span>A device with nobody signed in. It shows the queue, the QR code and the Log In form.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Personal account</span><span>Each panelist's own username and password. Signing in takes a seat; the Lead Panelist also gets the Presentation Controls.</span></div>
    </div>
</div>

<div class="hc-topic" id="rd-check">
    <h3><x-icon name="monitor" /> Checking a device</h3>
    <p>Before the event, open <code>/room-session/device-check</code> on each device. It reports the browser and whether it supports what the room screens need. It needs no account, so it works even on a device that cannot get any further. Update the browser (or use another device) if it reports a problem.</p>
    <div class="hc-note hc-note-tip"><x-icon name="lightbulb" /><p>Keep devices charged and on the same reliable network. Turn off auto-lock or set it long, so the screen does not sleep mid-presentation.</p></div>
</div>

<div class="hc-topic" id="rd-setup">
    <h3><x-icon name="settings" /> Opening a room</h3>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="play" /> Start the room</div><p class="hc-step-text">In <strong>Event Control</strong>, press <strong>Start Room</strong>. The room now appears in the Room Session list.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="monitor" /> Open it on the device</div><p class="hc-step-text">On the home page press <strong>Room Session</strong>, choose the room under its category and press <strong>Open Room</strong>. Each room shows how many seats are taken, e.g. <strong>Panel · 1/3</strong>.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user-check" /> Panelists sign in</div><p class="hc-step-text">Each panelist scans the QR code or uses the Log In form with their own account, on a shared device or their own laptop. See <a href="#pn-join">Joining a room</a>.</p><div class="hc-result">Seat <x-icon name="arrow-right" /> <span class="hc-badge hc-badge-success">Connected</span></div></div></li>
    </ol>
    <div class="hc-note hc-note-info"><x-icon name="info" /><p>A presentation cannot start until every seat has a panelist.</p></div>
</div>

<div class="hc-topic" id="rd-screen">
    <h3><x-icon name="tablet" /> The room screen</h3>
    <p>The top bar shows the system name and the <strong>room</strong>. Below it:</p>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="list-check" /></div><div><div class="hc-option-title">Room Queue</div><p>Today's counts, the current or last group, who is called and next, and <strong>View All</strong> for the full queue in a side panel.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="clock" /></div><div><div class="hc-option-title">Status card</div><p>The group on stage, its timer, payment status, break warnings and notices from the Administrator.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="user" /></div><div><div class="hc-option-title">Connection panel</div><p>The QR code, seat count and Log In form while a seat is free; <strong>Panel complete</strong> once every seat is taken; the signed-in panelist and their badges otherwise.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="clipboard-check" /></div><div><div class="hc-option-title">Evaluation and controls</div><p>The evaluation sheet for the group on stage, and the Presentation Controls for the Lead.</p></div></div>
    </div>
</div>

<div class="hc-topic" id="rd-release">
    <h3><x-icon name="log-out" /> Leaving and switching devices</h3>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="log-out" /></div><div><div class="hc-option-title">Log Out (panelist)</div><p>Frees the panelist's seat. The device keeps showing the room for the next panelist.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="refresh" /></div><div><div class="hc-option-title">Reconnect</div><p>A panelist whose device died signs in again on another one. Their seat moves to the new device and the old one goes back to showing the room. On a full room, use <strong>Reconnect</strong> under Panel complete.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="arrow-left" /></div><div><div class="hc-option-title">Leave Room</div><p>In the device's settings. Goes back to the room list. Log out first if a panelist is signed in.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon is-info"><x-icon name="stop" /></div><div><div class="hc-option-title">End Room (Event Control)</div><p>Closes the room for the day and signs every panelist out. Devices showing it go back to the room list.</p></div></div>
    </div>
    <p>Administrators can also <strong>Disconnect</strong> a panelist from the room's Panel Seats table in Event Control, except while a group is presenting.</p>
</div>
