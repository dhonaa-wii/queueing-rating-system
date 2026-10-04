<div class="hc-topic" id="gs-about">
    <h3><x-icon name="info" /> What the system does</h3>
    <p>The <strong>Academic Research Presentation Queueing and Rating System (ARPQRS)</strong> runs research presentations — capstone proposals, pre-oral and final defenses, title proposals — from registration to final grades. It replaces paper sign-up sheets, hand-written queue lists and paper evaluation forms with one shared, live system.</p>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="file-plus" /></div><div><div class="hc-option-title">Online registration</div><p>Research groups register themselves for a presentation category, without an account.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="calendar" /></div><div><div class="hc-option-title">Automatic scheduling</div><p>Groups are placed into rooms, days and time slots automatically once registration closes.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="list-check" /></div><div><div class="hc-option-title">Live queue</div><p>Everyone sees who is presenting, who is next and the expected time of every group.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="clipboard-check" /></div><div><div class="hc-option-title">Digital evaluation</div><p>Panelists rate each group on a room device; scores and outcomes are computed instantly.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="bar-chart" /></div><div><div class="hc-option-title">Reports and analytics</div><p>Grades, sign-off sheets, recommendations and schedule analytics, exportable to Excel and PDF.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="shield-check" /></div><div><div class="hc-option-title">Accountability</div><p>Every important action is recorded, and data is protected by backups.</p></div></div>
    </div>
</div>

<div class="hc-topic" id="gs-roles">
    <h3><x-icon name="users" /> Who uses the system</h3>
    <p>There are four kinds of users, plus the shared devices used inside each presentation room. Each chapter of this guide is written for one of them.</p>
    <div class="hc-table-wrap">
        <table class="hc-table">
            <thead><tr><th>User</th><th>Account</th><th>What they do</th><th>Chapter</th></tr></thead>
            <tbody>
                <tr><td>Student / Research Group</td><td>None</td><td>Register the group, view the schedule and queue, follow announcements.</td><td><a href="#students">2</a></td></tr>
                <tr><td>Panelist</td><td>Created by an Administrator</td><td>View assignments, connect to a room terminal, evaluate groups; the Lead Panelist also runs the room.</td><td><a href="#panelists">3</a></td></tr>
                <tr><td>Room Session device</td><td>Room account, new each day</td><td>A tablet or laptop in the room that panelists sign in to.</td><td><a href="#room-devices">4</a></td></tr>
                <tr><td>Administrator</td><td>Created by the Super Administrator</td><td>Runs everything for one college: panelists, forms, categories, schedules, panels, event day, reports.</td><td><a href="#administrators">5</a></td></tr>
                <tr><td>Super Administrator</td><td>System owner</td><td>Creates Administrator accounts, manages colleges and school years, audit log, backups.</td><td><a href="#super-admins">6</a></td></tr>
            </tbody>
        </table>
    </div>
    <div class="hc-note hc-note-info"><x-icon name="info" /><p>Each Administrator belongs to one <strong>college</strong>. They only see and manage that college's categories, panelists and evaluation forms. Students can browse every college's open categories.</p></div>
</div>

<div class="hc-topic" id="gs-requirements">
    <h3><x-icon name="monitor" /> What you need</h3>
    <ul class="hc-list">
        <li><strong>A modern web browser</strong> — Google Chrome, Microsoft Edge, Mozilla Firefox or Safari, kept up to date. Nothing needs to be installed.</li>
        <li><strong>An internet connection</strong> to the address where your school hosts the system.</li>
        <li><strong>Panelists:</strong> a phone with a camera is useful for joining a room by QR code, but not required — you can type your username and password on the room device instead.</li>
        <li><strong>Rooms:</strong> one tablet or laptop per panel seat. See <a href="#rd-check">Checking a device</a> to confirm an older tablet is compatible.</li>
    </ul>
    <p>The system works on phones, tablets and computers. On a small screen the side menu folds into the <x-icon name="list-check" style="width:.9em;height:.9em;vertical-align:-.1em" /> menu button at the top left.</p>
</div>

<div class="hc-topic" id="gs-access">
    <h3><x-icon name="home" /> Opening the system</h3>
    <p>Open the system's web address. The <strong>home page</strong> is the starting point for everyone:</p>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="graduation-cap" /></div><div><div class="hc-option-title">Student / Research Group</div><p>Opens the list of presentation categories — register or view a schedule from there.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="log-in" /></div><div><div class="hc-option-title">Sign in</div><p>For panelists, administrators and super administrators.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="monitor" /></div><div><div class="hc-option-title">Room Session</div><p>Top-right button, used only on the devices inside a presentation room.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="help-circle" /></div><div><div class="hc-option-title">Help Center</div><p>This guide. It is linked from the top bar of every page and needs no sign-in.</p></div></div>
    </div>
</div>

<div class="hc-topic" id="gs-sign-in">
    <h3><x-icon name="log-in" /> Signing in</h3>
    <p>Panelists, Administrators and Super Administrators sign in with a <strong>username</strong> and <strong>password</strong>. Accounts are never self-registered: an Administrator creates panelist accounts, and the Super Administrator creates Administrator accounts.</p>

    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/login</div></div>
            <div class="hc-screen is-tinted" style="display:grid;place-items:center;padding:1.5rem;">
                <div class="m-card" style="width:min(17rem,100%);padding:1rem;">
                    <div class="m-title" style="font-size:.95rem;">Welcome back</div>
                    <div class="m-muted m-small" style="margin-bottom:.6rem;">Sign in to continue</div>
                    <div class="m-col">
                        <div class="m-field"><span>Username <span class="m-pin">1</span></span><span class="m-input">jdelacruz</span></div>
                        <div class="m-field"><span>Password <span class="m-pin">2</span></span><span class="m-input">••••••••</span></div>
                        <span class="m-btn is-block"><x-icon name="log-in" /> Log in</span>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 1.</strong> The sign-in page.</figcaption>
    </figure>

    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user" /> Enter your username</div><p class="hc-step-text">Type it exactly as it was given to you.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="key" /> Enter your password and press Log in</div><p class="hc-step-text">You land on your own dashboard. If you were sent to the sign-in page from a link (for example a QR code), you are taken back to that link instead.</p></div></li>
    </ol>
    <div class="hc-note hc-note-warn"><x-icon name="alert-triangle" /><p><span class="hc-note-title">Forgot your password?</span> There is no self-service reset. Ask your Administrator (panelists) or the Super Administrator (administrators) to reset it — you will receive a new temporary password.</p></div>
</div>

<div class="hc-topic" id="gs-first-login">
    <h3><x-icon name="lock" /> Your first sign-in</h3>
    <p>New accounts and reset accounts start with a <strong>temporary password</strong>. The first time you sign in with it you are taken straight to <strong>Change Password</strong>:</p>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head">Type the temporary password as Current Password</div></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head">Choose a new password and type it twice</div><p class="hc-step-text">Pick something only you know. Never reuse the temporary one.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head">Save</div><p class="hc-step-text">You continue to your dashboard. Until you do this, the rest of the system stays locked; you can choose <strong>Log out instead</strong> to come back later.</p><div class="hc-result">Account <x-icon name="arrow-right" /> <span class="hc-badge hc-badge-success">Active</span></div></div></li>
    </ol>
    <div class="hc-note hc-note-info"><x-icon name="shield-check" /><p>Temporary passwords are shown <strong>once</strong>, to the person who created or reset the account, and are never stored in a readable form. If it is lost before first use, it has to be reset again.</p></div>
</div>

<div class="hc-topic" id="gs-navigation">
    <h3><x-icon name="grid" /> Finding your way around</h3>
    <p>Signed-in pages share the same layout:</p>

    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/admin/dashboard</div></div>
            <div class="hc-screen is-flush">
                <div class="m-app">
                    <div class="m-side">
                        <div class="m-side-brand">ARPQRS <span class="m-pin" style="font-style:normal">1</span></div>
                        <div class="m-nav is-on"><x-icon name="grid" /> Dashboard</div>
                        <div class="m-nav"><x-icon name="users" /> Panelist Management</div>
                        <div class="m-nav"><x-icon name="grid" /> Presentation Setup</div>
                        <div class="m-nav"><x-icon name="user-check" /> Group &amp; Panel…</div>
                        <div class="m-nav"><x-icon name="clock" /> Event Control</div>
                        <div class="m-nav"><x-icon name="book-open" /> Evaluation Library</div>
                        <div class="m-nav is-label"><x-icon name="bar-chart" /> Reports &amp; Analytics</div>
                        <div class="m-nav is-sub">Analytics</div>
                        <div class="m-nav is-sub">Reports</div>
                        <div class="m-side-foot"><div class="m-nav"><x-icon name="settings" /> Settings <span class="m-pin">2</span></div></div>
                    </div>
                    <div class="m-body">
                        <div class="m-top"><span class="m-title">Dashboard <span class="m-pin">3</span></span><span class="m-btn is-ghost"><x-icon name="help-circle" /> Help Center</span><span class="m-pin">4</span></div>
                        <div class="m-page">
                            <div class="m-stats">
                                <div class="m-stat"><div class="m-label">Groups</div><div class="m-stat-value">56</div></div>
                                <div class="m-stat"><div class="m-label">Done</div><div class="m-stat-value">31</div></div>
                                <div class="m-stat"><div class="m-label">Rooms Live</div><div class="m-stat-value">2</div></div>
                                <div class="m-stat"><div class="m-label">Panelists</div><div class="m-stat-value">14</div></div>
                            </div>
                            <div class="m-grid-2"><div class="m-card"><div class="m-line" style="width:60%"></div><div class="m-line" style="width:90%;margin-top:.4rem"></div><div class="m-line" style="width:75%;margin-top:.4rem"></div></div><div class="m-card"><div class="m-line" style="width:50%"></div><div class="m-line" style="width:85%;margin-top:.4rem"></div><div class="m-line" style="width:65%;margin-top:.4rem"></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 2.</strong> The signed-in layout (Administrator shown; other roles have their own menu items).</figcaption>
    </figure>
    <ul class="hc-pins">
        <li><span class="m-pin">1</span><span><strong>Side menu</strong> — every module of your role. The current page is highlighted.</span></li>
        <li><span class="m-pin">2</span><span><strong>Settings</strong> — your profile, password, appearance and Log out.</span></li>
        <li><span class="m-pin">3</span><span><strong>Page title</strong> — and, on some pages, a category picker or page actions.</span></li>
        <li><span class="m-pin">4</span><span><strong>Help Center</strong> — opens this guide at your role's chapter, in a new tab.</span></li>
    </ul>
    <h4>Common controls</h4>
    <div class="hc-panel">
        <div class="hc-legend-row"><span class="m-btn is-ghost" style="font-size:.7rem">⋮</span><span>A row's <strong>three-dot menu</strong> holds its actions (View, Edit, Delete…).</span></div>
        <div class="hc-legend-row"><span class="m-red-dot"></span><span>A <strong>red dot</strong> next to a tab or field marks something still incomplete.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Status</span><span>Coloured <strong>badges</strong> show status. See the <a href="#rf-statuses">Status reference</a>.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-disabled">Start</span><span>A <strong>faded button</strong> is not available yet. Hover over it (or press and hold on a tablet) to see why.</span></div>
        <div class="hc-legend-row"><span class="m-row"><span class="m-dot is-success"></span><span class="m-small">Saved</span></span><span>A short <strong>toast message</strong> in the corner confirms each save or explains an error.</span></div>
    </div>
    <div class="hc-note hc-note-tip"><x-icon name="lightbulb" /><p>Most pages refresh their live data on their own every few seconds. You do not need to reload to see a queue move.</p></div>
</div>

<div class="hc-topic" id="gs-settings">
    <h3><x-icon name="settings" /> Profile &amp; account settings</h3>
    <p>Open <strong>Settings</strong> at the bottom of the side menu (or the <x-icon name="settings" style="width:.9em;height:.9em;vertical-align:-.1em" /> gear in the top bar on pages without a menu).</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">Settings</div></div>
            <div class="hc-screen m-backdrop">
                <div class="m-modal" style="width:min(22rem,100%)">
                    <div class="m-modal-head">Profile &amp; Account Settings <span class="m-faint">✕</span></div>
                    <div class="m-modal-body">
                        <div class="m-between"><span class="m-bold">Appearance <span class="m-pin">1</span></span><span class="m-seg"><span class="is-on">Light</span><span>Dark</span></span></div>
                        <div class="m-rule"></div>
                        <div class="m-field"><span>Username</span><span class="m-input is-disabled">jdelacruz</span></div>
                        <div class="m-grid-2"><div class="m-field"><span>First Name</span><span class="m-input">Juan</span></div><div class="m-field"><span>Last Name</span><span class="m-input">Dela Cruz</span></div></div>
                        <div class="m-field"><span>Contact Number</span><span class="m-input is-empty">Optional</span></div>
                        <div class="m-row m-wrap"><span class="m-btn"><x-icon name="save" /> Save Profile</span> <span class="m-pin">2</span><span class="m-btn is-soft"><x-icon name="key" /> Change Password</span> <span class="m-pin">3</span></div>
                    </div>
                    <div class="m-modal-foot"><span class="m-btn is-danger"><x-icon name="log-out" /> Log out</span><span class="m-pin">4</span></div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 3.</strong> The Settings window.</figcaption>
    </figure>
    <ul class="hc-pins">
        <li><span class="m-pin">1</span><span><strong>Appearance</strong> — light or dark mode for this browser.</span></li>
        <li><span class="m-pin">2</span><span><strong>Profile</strong> — correct your name, suffix and contact number. Your username cannot be changed.</span></li>
        <li><span class="m-pin">3</span><span><strong>Change Password</strong> — any time; you will need your current password.</span></li>
        <li><span class="m-pin">4</span><span><strong>Log out</strong> — ends your session on this device.</span></li>
    </ul>
    <p>Super Administrators also see an <strong>Application Settings</strong> link here.</p>
</div>

<div class="hc-topic" id="gs-appearance">
    <h3><x-icon name="sun" /> Light and dark mode</h3>
    <p>Switch between light and dark mode in <strong>Settings → Appearance</strong>, or with the sun/moon button on pages without a menu (home page, sign-in, registration). The choice is remembered by your browser, so set it once per device. This guide always prints in light mode.</p>
</div>

<div class="hc-topic" id="gs-sign-out">
    <h3><x-icon name="log-out" /> Signing out</h3>
    <p>Open <strong>Settings</strong> and choose <strong>Log out</strong>. Always sign out on a shared or public computer. Closing the browser tab alone does not sign you out.</p>
    <div class="hc-note hc-note-warn"><x-icon name="alert-triangle" /><p>Signing out of your <strong>personal account</strong> does not sign you out of a <strong>room device</strong>. On a room device, use the device's own Log Out button — see <a href="#pn-join">Joining a room terminal</a>.</p></div>
</div>
