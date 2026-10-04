<div class="hc-topic" id="rd-what">
    <h3><x-icon name="tablet" /> What a room device is</h3>
    <p>Every presentation room uses one device — usually a tablet, sometimes a laptop — <strong>per panel seat</strong>. Each device is one numbered <strong>terminal</strong>: a room with a 3-person panel has Terminals 1, 2 and 3. Panelists sign in on these devices to evaluate, and the Lead Panelist runs the room from whichever one they use.</p>
    <p>Setting up the devices is done once per room per day, normally by an Administrator, with the room's <strong>room account</strong>:</p>
    <div class="hc-panel">
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Room account</span><span>Shared username and password for one room, e.g. <code>cfd-room101</code>. It only <strong>registers a device</strong> as a terminal. It is created when the room is started in Event Control, and deleted when the room ends — a new one is made every day.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Personal account</span><span>Each panelist's own username and password, used to sit at a terminal that is already set up.</span></div>
    </div>
</div>

<div class="hc-topic" id="rd-check">
    <h3><x-icon name="monitor" /> Checking a device</h3>
    <p>Before the event, open <code>/room-session/device-check</code> on each device. It reports the browser and whether it supports what the room screens need. It needs no account, so it works even on a device that cannot get any further. Update the browser (or use another device) if it reports a problem.</p>
    <div class="hc-note hc-note-tip"><x-icon name="lightbulb" /><p>Keep devices charged and on the same reliable network. Turn off auto-lock or set it long, so the screen does not sleep mid-presentation.</p></div>
</div>

<div class="hc-topic" id="rd-setup">
    <h3><x-icon name="settings" /> Setting up a device</h3>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="play" /> Start the room</div><p class="hc-step-text">In <strong>Event Control</strong>, press <strong>Start Room</strong>. The room's card then shows its room account. Press <strong>Reset Password</strong> to see a password — a new one is issued and shown once each time.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="monitor" /> Open Room Session on the device</div><p class="hc-step-text">On the home page press <strong>Room Session</strong>, then sign in with the room account's username and password on <strong>Join Room Session</strong>.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="hash" /> Pick the terminal number</div><p class="hc-step-text">"Which terminal is this tablet?" — choose a number and press <strong>Set Up This Tablet</strong>. A number already used by another device shows <strong>Already set up on another device</strong> and cannot be picked until an Administrator releases it in Event Control.</p><div class="hc-result">Terminal <x-icon name="arrow-right" /> <span class="hc-badge hc-badge-brand">Claimed</span></div></div></li>
        <li class="hc-step"><div class="hc-step-marker">4</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user-check" /> Hand it to the panelist</div><p class="hc-step-text">The device now waits with a QR code and a Log In form. The panelist connects with their own account — see <a href="#pn-join">Joining a room terminal</a>.</p><div class="hc-result">Status <x-icon name="arrow-right" /> <span class="hc-badge hc-badge-success">Connected</span></div></div></li>
    </ol>
    <div class="hc-note hc-note-info"><x-icon name="info" /><p>The device stays set up for the rest of the day, even as panelists sign in and out. The room cannot call its first group until every terminal is connected.</p></div>
</div>

<div class="hc-topic" id="rd-screen">
    <h3><x-icon name="tablet" /> The device screen</h3>
    <p>The top bar shows the system name, the <strong>room</strong> and the <strong>terminal number</strong>. Below it:</p>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="list-check" /></div><div><div class="hc-option-title">Room Queue</div><p>Today's counts, the current or last group, who is called and next, and <strong>View All</strong> for the full queue in a side panel.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="clock" /></div><div><div class="hc-option-title">Status card</div><p>The group on stage, its timer, payment status, break warnings and notices from the Administrator.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="user" /></div><div><div class="hc-option-title">Connection panel</div><p>The QR code and Log In form when empty; the connected panelist and their badges otherwise.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="clipboard-check" /></div><div><div class="hc-option-title">Evaluation and controls</div><p>The evaluation sheet for the called group, and the Presentation Controls for the Lead.</p></div></div>
    </div>
</div>

<div class="hc-topic" id="rd-release">
    <h3><x-icon name="log-out" /> Releasing a device</h3>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="log-out" /></div><div><div class="hc-option-title">Log Out (panelist)</div><p>Signs the panelist out. The device stays set up for the next panelist.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon is-danger"><x-icon name="power" /></div><div><div class="hc-option-title">Release This Device</div><p>Frees the terminal number. The device has to sign in with the room account again. Use it to swap a broken tablet.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon is-info"><x-icon name="stop" /></div><div><div class="hc-option-title">End Room (Event Control)</div><p>Closes the room for the day and deletes its room account, so every device in it is released.</p></div></div>
    </div>
    <p>Administrators can also <strong>Disconnect</strong> a panelist or release a device remotely from the room's Terminals table in Event Control.</p>
</div>
