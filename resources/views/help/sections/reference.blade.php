<div class="hc-topic" id="rf-statuses">
    <h3><x-icon name="list-check" /> Status reference</h3>
    <div class="hc-panel">
        <div class="hc-legend-group">A group's presentation</div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Scheduled / Queued</span><span>Placed in a room and waiting.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Called</span><span>The Lead called the group; it should come in now.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Ongoing</span><span>Presenting; the timer runs.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-danger">Paused</span><span>Timer stopped for an interruption.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Completed</span><span>Presented and evaluated, with an outcome.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-danger">Deferred</span><span>Set aside with a reason; will be reinserted.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-warn">To be scheduled</span><span>Still to present, but has no usable slot — waiting for a date or room.</span></div>

        <div class="hc-legend-group">A presentation day</div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Planned</span><span>Ahead; its start time has not come yet.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Standby</span><span>Its time has come; waiting for the first Start Room.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Active</span><span>At least one room started; the day is running.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-info">Completed</span><span>Every room ended (or it ended automatically after midnight).</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-danger">Cancelled</span><span>Passed without ever being started.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-danger">Overdue</span><span>Its window passed unstarted; edit the date to use it.</span></div>

        <div class="hc-legend-group">A room on the day</div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-info">Waiting</span><span>Started; no group on stage right now.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Active</span><span>A group is called or presenting.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-danger">Paused</span><span>Paused by an Administrator.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-info">Break</span><span>Inside a scheduled break with nobody on stage.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Closed</span><span>Ended for the day.</span></div>

        <div class="hc-legend-group">A terminal</div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Unclaimed</span><span>No device set up as this terminal.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Claimed</span><span>A device is set up as this terminal.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Empty</span><span>No panelist is signed in on it.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Connected</span><span>A panelist is signed in on it.</span></div>

        <div class="hc-legend-group">Payment</div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Verified / Paid</span><span>Reference number recorded by the Lead or an Administrator.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Unpaid</span><span>Not verified yet. A partly verified group shows e.g. "1/2 Verified".</span></div>
    </div>
</div>

<div class="hc-topic" id="rf-outcomes">
    <h3><x-icon name="trophy" /> Presentation outcomes</h3>
    <p>Each panelist picks one remark on their sheet. The <strong>Lead Panelist's</strong> remark becomes the group's official outcome. If the Lead filed no sheet, the presentation still completes but without an outcome.</p>
    <div class="hc-table-wrap">
        <table class="hc-table">
            <thead><tr><th>Outcome</th><th>Successful</th><th>Another attempt</th><th>In grade reports</th></tr></thead>
            <tbody>
                <tr><td>Pass with No Revision</td><td class="hc-check">Yes</td><td class="hc-dash">No</td><td>Yes</td></tr>
                <tr><td>Pass with Minor Revision</td><td class="hc-check">Yes</td><td class="hc-dash">No</td><td>Yes</td></tr>
                <tr><td>Pass with Major Revision</td><td class="hc-check">Yes</td><td class="hc-dash">No</td><td>Yes</td></tr>
                <tr><td>Re-Defense</td><td>Not yet</td><td class="hc-check">Yes — presents again last</td><td>No, until the final attempt</td></tr>
                <tr><td>Failed</td><td>No</td><td class="hc-dash">No — must register again</td><td>Yes, marked red</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="hc-topic" id="rf-grading">
    <h3><x-icon name="bar-chart" /> How scores and grades are computed</h3>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head">Section score</div><p class="hc-step-text">The average of the section's criterion ratings, out of 5, turned into a percentage: <code>average ÷ 5 × 100</code>.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head">One panelist's total score</div><p class="hc-step-text">Each section score times its weight, added up — out of 100. This is the "Total Score" on the sheet.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head">Group Grade</div><p class="hc-step-text">The average of every panelist's total score for the group.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">4</div><div class="hc-step-body"><div class="hc-step-head">Individual Grade Average</div><p class="hc-step-text">The average of the individual scores (0–100) each panelist gave that student.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">5</div><div class="hc-step-body"><div class="hc-step-head">Total</div><p class="hc-step-text"><code>30% × Individual Grade Average + 70% × Group Grade</code>.</p></div></li>
    </ol>
    <div class="hc-panel">
        <div class="hc-legend-group">Worked example</div>
        <p class="m-small" style="margin:0 0 .4rem;color:var(--hc-muted)">Sections: Presentation 20%, Functionality 30%, Documentation 50%. One panelist rates them 4.5, 4.0 and 4.2 on average.</p>
        <div class="hc-legend-row"><span>Sections</span><span>90% × 0.20 + 80% × 0.30 + 84% × 0.50 = 18 + 24 + 42 = <strong>84.00</strong></span></div>
        <div class="hc-legend-row"><span>Group Grade</span><span>Panel totals 84.00, 86.00, 84.00 → <strong>84.67</strong></span></div>
        <div class="hc-legend-row"><span>Individual Avg.</span><span>Panel gave the student 92, 90, 94 → <strong>92.00</strong></span></div>
        <div class="hc-legend-row"><span>Total</span><span>0.30 × 92.00 + 0.70 × 84.67 = <strong>86.87</strong></span></div>
    </div>
    <p>Title Proposal forms have no totals or individual scores; their result is the approved title and the outcome. If a group presented more than once, its latest completed attempt is the one graded.</p>
</div>

<div class="hc-topic" id="rf-permissions">
    <h3><x-icon name="lock" /> Who can do what</h3>
    <div class="hc-table-wrap">
        <table class="hc-table">
            <thead><tr><th>Task</th><th>Student</th><th>Panelist</th><th>Admin</th><th>Super Admin</th></tr></thead>
            <tbody>
                <tr><td>Register a group</td><td class="hc-check">✓</td><td class="hc-dash">—</td><td class="hc-check">✓ (Add Group)</td><td class="hc-dash">—</td></tr>
                <tr><td>Edit a registration</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-check">✓</td><td class="hc-dash">—</td></tr>
                <tr><td>View schedule and queue</td><td class="hc-check">✓</td><td class="hc-check">✓</td><td class="hc-check">✓</td><td class="hc-dash">—</td></tr>
                <tr><td>Evaluate a group</td><td class="hc-dash">—</td><td class="hc-check">✓ (seated)</td><td class="hc-dash">—</td><td class="hc-dash">—</td></tr>
                <tr><td>Call, start, complete, defer</td><td class="hc-dash">—</td><td class="hc-check">✓ (Lead)</td><td>Defer / override</td><td class="hc-dash">—</td></tr>
                <tr><td>Create panelist accounts</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-check">✓</td><td class="hc-dash">View only</td></tr>
                <tr><td>Categories, forms, panels, event day</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-check">✓ (own college)</td><td class="hc-dash">—</td></tr>
                <tr><td>Reports and analytics</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-check">✓</td><td class="hc-dash">—</td></tr>
                <tr><td>Administrator accounts, colleges, school year</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-check">✓</td></tr>
                <tr><td>Audit log, backups, maintenance</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-dash">—</td><td class="hc-check">✓</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="hc-topic" id="rf-glossary">
    <h3><x-icon name="book-open" /> Glossary</h3>
    <div class="hc-panel">
        <div class="hc-legend-row"><strong>Attempt</strong><span>One presentation by a group. A re-defense is the group's second (or later) attempt.</span></div>
        <div class="hc-legend-row"><strong>Backup / Alternate Panel</strong><span>The standby panelist who can take an assigned seat.</span></div>
        <div class="hc-legend-row"><strong>Category</strong><span>One presentation event, e.g. "Capstone Final Defense", with its own registration, dates, rooms and form.</span></div>
        <div class="hc-legend-row"><strong>Chair / Lead Panelist</strong><span>The panelist who runs the room and whose remark is the official outcome.</span></div>
        <div class="hc-legend-row"><strong>College</strong><span>The unit an Administrator manages; categories, panelists and forms belong to it.</span></div>
        <div class="hc-legend-row"><strong>Defer</strong><span>Take a group out of the active queue for now, with a reason.</span></div>
        <div class="hc-legend-row"><strong>Evaluation form</strong><span>The rating sheet: weighted sections of criteria rated 1–5, plus remarks.</span></div>
        <div class="hc-legend-row"><strong>Expected time</strong><span>A live estimate of when a group will present, while its day runs.</span></div>
        <div class="hc-legend-row"><strong>Group reference</strong><span>A group's ID, e.g. CFD-2026-0012.</span></div>
        <div class="hc-legend-row"><strong>Outcome / Remark</strong><span>The verdict on a presentation (Pass…, Re-Defense, Failed).</span></div>
        <div class="hc-legend-row"><strong>Planned time</strong><span>The slot the schedule assigned before the day started.</span></div>
        <div class="hc-legend-row"><strong>Presentation mode</strong><span>Standard (one project) or Title Proposal (several proposed titles).</span></div>
        <div class="hc-legend-row"><strong>Queue strategy</strong><span>How groups are ordered: First In First Out, Section Based or Random Draw.</span></div>
        <div class="hc-legend-row"><strong>Reinsert</strong><span>Put a deferred group back in a queue.</span></div>
        <div class="hc-legend-row"><strong>Room account</strong><span>A room's shared, day-only login used to set up its devices.</span></div>
        <div class="hc-legend-row"><strong>Terminal</strong><span>One numbered device seat in a room, one per panelist.</span></div>
        <div class="hc-legend-row"><strong>Track</strong><span>A research area (e.g. Web, Mobile). Track rooms only take their tracks' groups.</span></div>
        <div class="hc-legend-row"><strong>Transfer</strong><span>Move a group to another room or day.</span></div>
    </div>
</div>
