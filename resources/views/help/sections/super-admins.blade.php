<div class="hc-topic" id="sa-dashboard">
    <h3><x-icon name="grid" /> Dashboard</h3>
    <p>System-wide totals (accounts, panelists, active categories, audit events today), an audit activity chart and the most recent audit entries. <strong>View All</strong> opens the full Audit Log.</p>
</div>

<div class="hc-topic" id="sa-admins">
    <h3><x-icon name="user" /> Admin Accounts</h3>
    <p>Only the Super Administrator creates Administrator accounts. Each Administrator manages exactly one <strong>college</strong>, chosen here — it decides every category, panelist and form they can see.</p>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user-plus" /> New Admin Account</div><p class="hc-step-text">Username, name, the college, and optionally contact number, email and employee reference.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="key" /> Copy the temporary password</div><p class="hc-step-text">A 12-character password is generated and shown <strong>once</strong>. The Administrator must change it at first sign-in.</p></div></li>
    </ol>
    <div class="hc-panel">
        <div class="hc-legend-group">Row actions</div>
        <div class="hc-legend-row"><span class="m-btn is-ghost"><x-icon name="edit" /> View / Edit</span><span>Correct details or move the Administrator to another college.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-ghost"><x-icon name="key" /> Reset Password</span><span>Issues a new temporary password, shown once.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-ghost"><x-icon name="power" /> Deactivate / Activate</span><span>Blocks or restores sign-in. Their college's data is untouched.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-danger"><x-icon name="trash" /> Delete</span><span>Removes the account. Prefer Deactivate to keep history.</span></div>
    </div>
</div>

<div class="hc-topic" id="sa-panelists">
    <h3><x-icon name="users" /> Panelist Oversight</h3>
    <p>A <strong>view-only</strong> list of every panelist in every college, with their college, department and assignment history. Panelists are created and edited by their college's Administrator, not here.</p>
</div>

<div class="hc-topic" id="sa-application">
    <h3><x-icon name="settings" /> Application Settings</h3>
    <p>Open from <strong>Settings → Application Settings</strong>. Four tabs of reference data, each with View, Edit, Activate/Deactivate (or Set Active) and Delete:</p>
    <div class="hc-table-wrap">
        <table class="hc-table">
            <thead><tr><th>Tab</th><th>Notes</th></tr></thead>
            <tbody>
                <tr><td>Campuses</td><td>Code and name.</td></tr>
                <tr><td>Colleges</td><td>Each Administrator, category, panelist, evaluation form and letterhead belongs to one. A college in use cannot be deleted.</td></tr>
                <tr><td>Academic Years</td><td><strong>Exactly one is active.</strong> New categories and panelist passwords use it. Change it only with <strong>Set Active</strong>; the active one cannot be deactivated or deleted.</td></tr>
                <tr><td>Semesters</td><td>Exactly one is active, with the same rules.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="hc-note hc-note-warn"><x-icon name="alert-triangle" /><p>Switch the active academic year and semester at the start of a new term, before Administrators create that term's categories. Existing categories keep the year they were created in.</p></div>
</div>

<div class="hc-topic" id="sa-security">
    <h3><x-icon name="shield-check" /> Security Settings</h3>
    <p>A list of named system configuration values (text, number, true/false or JSON) that you can add, edit and remove. They are stored for reference; changing them does not, by itself, change how sign-in works.</p>
</div>

<div class="hc-topic" id="sa-audit">
    <h3><x-icon name="file-text" /> Audit Log</h3>
    <p>A read-only record of important actions — who did what, when, to which record, with the values before and after. Filter by text, user, action, record type and date range; expand a row to see the details. <strong>Export CSV</strong> downloads the filtered entries (the export itself is recorded). Old entries can be pruned automatically — see <a href="#sa-maintenance">Health and maintenance</a>.</p>
</div>

<div class="hc-topic" id="sa-backups">
    <h3><x-icon name="database" /> Backups</h3>
    <p><strong>Backup &amp; Maintenance</strong> has three tabs: Backups, Health and Maintenance. A backup is one <code>.zip</code> with the whole database and the uploaded letterhead logos.</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/super-admin/backups</div></div>
            <div class="hc-screen">
                <div class="m-between"><div class="m-tabs" style="flex:1"><span class="is-on">Backups</span><span>Health <i class="m-dot is-success"></i></span><span>Maintenance</span></div></div>
                <div class="m-grid-2" style="grid-template-columns:1.6fr 1fr;align-items:start;margin-top:.6rem;">
                    <div class="m-card">
                        <div class="m-between"><span class="m-label">Stored Backups</span><span class="m-row"><span class="m-btn"><x-icon name="database" /> Back Up Now</span><span class="m-btn is-ghost"><x-icon name="share" /> Upload Backup</span></span></div>
                        <div class="m-small" style="margin:.4rem 0 .2rem;">Backing up… 64%</div><div class="m-progress"><i style="width:64%"></i></div>
                        <table class="m-table" style="margin-top:.4rem;"><tbody>
                            <tr><td>Oct 3, 2026 · 2:00 AM</td><td>Scheduled</td><td>4.2 MB</td><td><span class="m-btn is-soft">Download</span> <span class="m-btn is-ghost">Restore</span></td></tr>
                            <tr><td>Oct 2, 2026 · 2:00 AM</td><td>Scheduled</td><td>4.1 MB</td><td><span class="m-btn is-soft">Download</span> <span class="m-btn is-ghost">Restore</span></td></tr>
                        </tbody></table>
                    </div>
                    <div class="m-card"><div class="m-label">Schedule</div>
                        <div class="m-col" style="margin-top:.3rem;"><div class="m-field"><span>Frequency</span><span class="m-input">Daily ▾</span></div><div class="m-grid-2"><div class="m-field"><span>Time</span><span class="m-input">02:00</span></div><div class="m-field"><span>Keep Latest</span><span class="m-input">7</span></div></div><div class="m-field"><span>Archive Password</span><span class="m-input">••••••</span></div><span class="m-btn is-block">Save Settings</span></div>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 18.</strong> Backup &amp; Maintenance — Backups tab.</figcaption>
    </figure>
    <ul class="hc-list">
        <li><strong>Back Up Now</strong> — runs a backup with a progress bar. Keep the page open until it finishes; if interrupted it picks up where it stopped.</li>
        <li><strong>Schedule</strong> — Off, Daily or Weekly at a set time, keeping the newest N backups. Scheduled backups need the server's scheduled task (cron) to be running.</li>
        <li><strong>Archive Password</strong> — optional; encrypts each archive. Without it a backup cannot be restored, so store it safely.</li>
        <li><strong>Download</strong> — keep a copy off the server regularly. Backups are never public.</li>
        <li><strong>Upload Backup</strong> — add an archive made elsewhere, for example to move to a new server.</li>
    </ul>
</div>

<div class="hc-topic" id="sa-restore">
    <h3><x-icon name="refresh" /> Restoring a backup</h3>
    <div class="hc-note hc-note-danger"><x-icon name="alert-triangle" /><p><span class="hc-note-title">A restore replaces all current data</span> with the backup's. Everything entered after the backup was taken is lost.</p></div>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head">Press Restore on the backup</div><p class="hc-step-text">Enter the archive password if it has one, and type <code>RESTORE</code> to continue.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head">The archive is checked first</div><p class="hc-step-text">A damaged, wrong-password or newer-version archive is refused before anything changes.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head">The site closes and a safety backup is taken</div><p class="hc-step-text">Everyone else sees a "restoring" page. A backup of the current data is made first, so the restore itself can be undone.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">4</div><div class="hc-step-body"><div class="hc-step-head">Data is replaced, then the site reopens</div><p class="hc-step-text">Keep the tab open. If it is closed, reopening the site continues it. If a restore fails, the site stays in Maintenance mode for you to check.</p></div></li>
    </ol>
</div>

<div class="hc-topic" id="sa-maintenance">
    <h3><x-icon name="settings" /> Health and maintenance</h3>
    <h4>Health tab</h4>
    <p>A traffic-light check of the system: scheduled tasks running, age of the last backup, last restore, free disk space, pending updates, last maintenance run, log size, maintenance mode and server configuration. The tab carries a coloured dot for the worst result.</p>
    <h4>Maintenance tab</h4>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon is-danger"><x-icon name="lock" /></div><div><div class="hc-option-title">Maintenance Mode</div><p><strong>Turn On</strong> to close the site to everyone except Super Administrators, with a message to visitors. <strong>Turn Off</strong> to reopen.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="refresh" /></div><div><div class="hc-option-title">Routine Cleanup</div><p>Runs daily, or now with <strong>Run Now</strong>: clears expired sessions and temporary files, rotates the application log, and prunes audit entries older than your setting.</p></div></div>
    </div>
    <div class="hc-note hc-note-info"><x-icon name="info" /><p>If you are locked out by maintenance mode, a server administrator can turn it off with <code>php artisan maintenance:mode off</code>.</p></div>
</div>
