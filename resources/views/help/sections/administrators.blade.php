<div class="hc-topic" id="ad-workflow">
    <h3><x-icon name="layers" /> The presentation workflow</h3>
    <p>An Administrator manages everything for <strong>one college</strong>. A typical category runs through these stages; each module of the side menu covers one of them.</p>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="book-open" /> Build an evaluation form</div><p class="hc-step-text"><strong>Evaluation Library</strong> — criteria, weights, remarks. Publish it. <a href="#ad-evaluation">More</a></p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="users" /> Register panelists</div><p class="hc-step-text"><strong>Panelist Management</strong> — create their accounts. <a href="#ad-panelists">More</a></p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="grid" /> Create and configure the category</div><p class="hc-step-text"><strong>Presentation Setup</strong> — project information, registration window, dates, rooms, queue, payment, evaluation form. <a href="#ad-setup">More</a></p></div></li>
        <li class="hc-step"><div class="hc-step-marker">4</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="file-plus" /> Groups register</div><p class="hc-step-text">Students register publicly while the window is open. You can also add groups yourself.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">5</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="list-check" /> The queue generates itself</div><p class="hc-step-text">When registration closes and setup is complete. <a href="#ad-generation">More</a></p></div></li>
        <li class="hc-step"><div class="hc-step-marker">6</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user-check" /> Assign panels</div><p class="hc-step-text"><strong>Group &amp; Panel Assignment</strong> — panelists, Lead and backup for every group. <a href="#ad-panels">More</a></p></div></li>
        <li class="hc-step"><div class="hc-step-marker">7</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="clock" /> Run the event</div><p class="hc-step-text"><strong>Event Control</strong> — start rooms, set up devices, watch the day, end rooms. <a href="#ad-event">More</a></p></div></li>
        <li class="hc-step"><div class="hc-step-marker">8</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="bar-chart" /> Report</div><p class="hc-step-text"><strong>Reports</strong> and <strong>Analytics</strong> — grades, sheets, exports. Then End Category. <a href="#ad-reports">More</a></p></div></li>
    </ol>
</div>

<div class="hc-topic" id="ad-dashboard">
    <h3><x-icon name="grid" /> Dashboard</h3>
    <p>Your college at a glance:</p>
    <ul class="hc-list">
        <li><strong>Stat strip</strong> — Registered Groups, Presentations Done, Rooms Live, Active Panelists, Avg. Group Score and Avg. Presentation length.</li>
        <li><strong>Activity</strong> chart — registrations and completed presentations per day (7, 14 or 30 days).</li>
        <li><strong>Presentation Outcomes</strong>, <strong>Queue Pipeline</strong> and <strong>Score Distribution</strong> charts. Hover over a mark for its value.</li>
        <li><strong>Categories</strong> table with status, progress and next day; <strong>Live Rooms</strong>, <strong>Upcoming Days</strong> and <strong>Panelist Workload</strong>.</li>
        <li><strong>Needs Attention</strong> — everything waiting on you. See <a href="#ad-attention">Needs Attention</a>.</li>
    </ul>
    <div class="hc-note hc-note-tip"><x-icon name="lightbulb" /><p>Clicking an item in Needs Attention opens the exact page and makes the row or field to fix <strong>blink</strong>, so you do not have to look for it.</p></div>
</div>

<div class="hc-topic" id="ad-panelists">
    <h3><x-icon name="users" /> Panelist Management</h3>
    <p>The list of your college's panelists, with search and filters. Click a row to open its details drawer (categories, assigned groups and evaluations done).</p>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user-plus" /> Add Panelist</div><p class="hc-step-text">Enter the name, and the panelist's own department or program (free text — they may come from another department). Their College is set to yours automatically.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="key" /> Copy the credentials</div><p class="hc-step-text">The username and temporary password are shown <strong>once</strong>. Give them to the panelist; they must change the password on first sign-in.</p></div></li>
    </ol>
    <div class="hc-panel">
        <div class="hc-legend-group">Row actions (⋮)</div>
        <div class="hc-legend-row"><span class="m-btn is-ghost"><x-icon name="eye" /> View / Edit</span><span>See or correct the panelist's profile.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-ghost"><x-icon name="key" /> Reset Password</span><span>Issues a new temporary password, shown once. Use it when a panelist forgets theirs.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-ghost"><x-icon name="power" /> Deactivate / Activate</span><span>Blocks or restores sign-in without deleting history.</span></div>
        <div class="hc-legend-row"><span class="m-btn is-danger"><x-icon name="trash" /> Delete</span><span>Removes the panelist. Prefer Deactivate for anyone who has served on a panel.</span></div>
    </div>
</div>

<div class="hc-topic" id="ad-evaluation">
    <h3><x-icon name="book-open" /> Evaluation Library</h3>
    <p>Build the evaluation sheets panelists fill in. A form is drawn as the actual paper; you type directly on it.</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/admin/evaluation-library/12</div></div>
            <div class="hc-screen is-tinted">
                <div class="m-grid-2" style="grid-template-columns:1.6fr 1fr;align-items:start;">
                    <div class="m-paper">
                        <div style="text-align:center;" class="m-xs m-muted">Cagayan State University · College of Computing and Information Sciences</div>
                        <div class="m-title" style="text-align:center;margin:.3rem 0;">Capstone Project Evaluation Form <span class="m-pin">1</span></div>
                        <table class="m-table">
                            <thead><tr><th>Performance Indicators</th><th>Rating (1–5)</th></tr></thead>
                            <tbody>
                                <tr><td class="m-bold">I. Presentation <span class="m-input" style="display:inline-flex;width:2.4rem;min-height:1.1rem">20</span>% <span class="m-pin">2</span></td><td></td></tr>
                                <tr><td>Clarity of delivery</td><td><span class="m-bubbles"><i>1</i><i>2</i><i>3</i><i>4</i><i>5</i></span></td></tr>
                                <tr><td class="m-faint">+ Add Row <span class="m-pin">3</span></td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="m-col">
                        <div class="m-card"><div class="m-between"><span class="m-label">Status</span><span class="hc-badge hc-badge-muted">Draft</span></div><div class="m-between m-small" style="margin-top:.3rem;"><span>Weight total</span><span class="hc-badge hc-badge-success">100%</span></div><span class="m-btn is-block" style="margin-top:.4rem;"><x-icon name="send" /> Publish</span><span class="m-pin">4</span></div>
                        <div class="m-card"><div class="m-label">Presentation Mode</div><div class="m-row m-small"><span class="m-radio is-on"></span> Standard</div><div class="m-row m-small"><span class="m-radio"></span> Title Proposal</div></div>
                        <div class="m-card"><div class="m-label">Remarks</div><div class="m-row m-small"><span class="m-check is-on"></span> Pass with No Revision</div><div class="m-row m-small"><span class="m-check is-on"></span> Re-Defense</div></div>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 12.</strong> The form builder: the paper on the left, the tools panel on the right.</figcaption>
    </figure>
    <ul class="hc-pins">
        <li><span class="m-pin">1</span><span><strong>Title</strong> — click and type. Changes save when you leave the field.</span></li>
        <li><span class="m-pin">2</span><span><strong>Sections and weights</strong> — each section carries a percentage. All sections must total exactly <strong>100%</strong>.</span></li>
        <li><span class="m-pin">3</span><span><strong>Add Row</strong> — add a section or a criterion above or below. Hover a row to move it up/down or remove it.</span></li>
        <li><span class="m-pin">4</span><span><strong>Tools panel</strong> — weight total, Publish, presentation mode, letterhead on/off and which remarks (outcomes) the sheet offers.</span></li>
    </ul>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="plus" /> Add Form</div><p class="hc-step-text">Opens a new draft straight away.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="edit" /> Build it</div><p class="hc-step-text">Sections with weights, criteria under each section (every section needs at least one), the mode, and the remarks. The rating scale is fixed at 1–5.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="send" /> Publish</div><p class="hc-step-text">Possible once the weights total 100%, every section has a criterion and a mode is chosen. Only published forms can be assigned to a category.</p></div></li>
    </ol>
    <h4>Editing a published form</h4>
    <p>Open it and press <strong>Edit Form</strong>; press <strong>Done Editing</strong> when finished. There are no versions — you edit the one form. Two things to know:</p>
    <ul class="hc-list">
        <li>A criterion that already has recorded scores <strong>cannot be deleted</strong>; rename it instead.</li>
        <li>Changing <strong>weights</strong> does not re-compute evaluations already submitted. Avoid re-weighting a form in the middle of an event.</li>
    </ul>
    <h4>Letterhead and outcomes</h4>
    <p><strong>Letterhead</strong> sets your college's header: two logos (JPG, PNG, SVG or WEBP, up to 2&nbsp;MB) and up to four text lines. It prints on every form that has its letterhead switched on, and on reports. <strong>Manage Outcomes</strong> lists the five presentation outcomes used as remarks — see <a href="#rf-outcomes">Presentation outcomes</a>.</p>
</div>

<div class="hc-topic" id="ad-setup">
    <h3><x-icon name="grid" /> Presentation Setup</h3>
    <p>Each <strong>category</strong> is one presentation event. The list has <strong>Active</strong> and <strong>Archived</strong> tabs; press <strong>New Category</strong> to create one, or a card's button to open its setup.</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/admin/categories/18</div></div>
            <div class="hc-screen">
                <div class="m-between" style="margin-bottom:.5rem;"><span class="m-title">Capstone Final Defense</span><span class="m-seg"><span class="is-on">Standard</span><span>Title Proposal</span></span></div>
                <div class="m-tabs"><span class="is-on">Project Information</span><span>Schedules <i class="m-red-dot"></i></span><span>Queue &amp; Payment</span><span>Evaluation Configuration <i class="m-red-dot"></i></span><span>Announcements</span></div>
                <div class="m-grid-2" style="margin-top:.6rem;">
                    <div class="m-col">
                        <div class="m-field"><span>Category Name</span><span class="m-input">Capstone Final Defense</span></div>
                        <div class="m-grid-2"><div class="m-field"><span>Academic Year</span><span class="m-input is-disabled">2026–2027</span></div><div class="m-field"><span>Semester</span><span class="m-input is-disabled">First</span></div></div>
                        <div class="m-field"><span>Max Group Members</span><span class="m-input">4</span></div>
                    </div>
                    <div class="m-col">
                        <div class="m-card is-alt"><div class="m-label">Requirement Checklist</div><div class="m-row m-small"><span class="m-check is-on"></span> Technical adviser required</div><div class="m-row m-small"><span class="m-check is-on"></span> Track required</div></div>
                        <div class="m-card is-alt"><div class="m-label">Panel Count Configuration</div><div class="m-between m-small"><span>Panelists per Room</span><span class="m-input" style="width:2.5rem">3</span></div></div>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 13.</strong> A category's setup. Red dots mark tabs with something still missing.</figcaption>
    </figure>
    <div class="hc-table-wrap">
        <table class="hc-table">
            <thead><tr><th>Tab</th><th>What you set</th></tr></thead>
            <tbody>
                <tr><td>Project Information</td><td>Name, description, subject, presentation mode, max group members, proposed title count (Title Proposal), requirement checklist (technical adviser, track), the track list, and <strong>Panelists per Room</strong>. Academic year, semester and college come from the active settings.</td></tr>
                <tr><td>Schedules</td><td>Registration opens/closes, duration per group, presentation dates, rooms and their tracks, breaks. <a href="#ad-schedules">Details</a></td></tr>
                <tr><td>Queue &amp; Payment</td><td>Queue strategy, called-group waiting period, payment requirement, payment types and instructions. <a href="#ad-queue">Details</a></td></tr>
                <tr><td>Evaluation Configuration</td><td>Which published form panelists use. Only forms for the category's mode are offered.</td></tr>
                <tr><td>Announcements</td><td>Messages shown on the public schedule page, optionally only between a start and end time.</td></tr>
            </tbody>
        </table>
    </div>
    <h4>Category status</h4>
    <p>The status is worked out by the system — you never set it by hand:</p>
    <div class="hc-panel">
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Draft</span><span>No registration window yet. Hidden from students.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-info">Upcoming</span><span>Registration opens later. Listed publicly.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Registration Open</span><span>Students can register.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-warn">Setup Incomplete</span><span>Registration closed but something is still missing (red dots) — the queue waits.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Awaiting Groups</span><span>Registration closed and setup complete, but no group has registered yet.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Queue Generated</span><span>Registration closed, setup complete and the queue has been built.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Completed</span><span>Ended with <strong>End Category</strong>. Setup becomes view only.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Archived</span><span>Put away with <strong>Archive</strong>. Hidden from students and from the working modules; <strong>Unarchive</strong> brings it back.</span></div>
    </div>
    <div class="hc-note hc-note-warn"><x-icon name="lock" /><p>Once any day of the category has started, the <strong>presentation mode can no longer be changed</strong>.</p></div>
</div>

<div class="hc-topic" id="ad-schedules">
    <h3><x-icon name="calendar" /> Dates, rooms, tracks and breaks</h3>
    <h4>Registration window and duration</h4>
    <p>Set <strong>Registration Opens</strong> and <strong>Registration Closes</strong>, and the <strong>Duration per Group</strong> in minutes. Groups are scheduled back to back with no gap.</p>
    <h4>Adding presentation dates</h4>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="calendar" /> Add Presentation Dates</div><p class="hc-step-text">Start Date, End Date, Daily Start Time, Daily End Time and, optionally, a daily Break Start/End Time. One date is created for every day in the range (up to 60). Re-running a range only adds missing days.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="sun" /> Include Saturday and Sunday?</div><p class="hc-step-text">If the range has weekend days you are asked. <strong>No</strong> removes them.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="monitor" /> Assign rooms</div><p class="hc-step-text">Tick rooms and days, then <strong>Assign to Selected Days</strong>. You can register a new room right there.</p></div></li>
    </ol>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">Created</div></div>
            <div class="hc-screen m-backdrop">
                <div class="m-modal" style="width:min(23rem,100%)">
                    <div class="m-modal-head">Created <span class="m-faint">✕</span></div>
                    <div class="m-modal-body">
                        <div class="m-small">5 presentation dates were created.</div>
                        <div class="m-card is-alt m-small"><div class="m-bold">Include Saturday and Sunday?</div><div class="m-row" style="margin-top:.3rem;"><span class="m-btn">Yes</span><span class="m-btn is-ghost">No</span></div></div>
                        <div class="m-label">Rooms</div>
                        <div class="m-row m-small"><span class="m-check is-on"></span> Room 101 <span class="hc-badge hc-badge-info">Web</span></div>
                        <div class="m-row m-small"><span class="m-check is-on"></span> Room 102</div>
                        <div class="m-label">Days</div>
                        <div class="m-row m-small m-wrap"><span class="m-check is-on"></span> Oct 20 <span class="m-check is-on"></span> Oct 21 <span class="m-check"></span> Oct 22</div>
                    </div>
                    <div class="m-modal-foot"><span class="m-btn">Assign to Selected Days</span></div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 14.</strong> After saving a date range: the weekend question, then room assignment.</figcaption>
    </figure>
    <h4>Rooms</h4>
    <p>Rooms are typed in, not picked from a fixed list. <strong>Register Room</strong> adds a room name to the category; the panel size comes from <strong>Panelists per Room</strong>. A room only takes part on the days it is assigned to. Use <strong>Edit Room</strong> to rename it or change its tracks; the per-day <strong>×</strong> removes it from one day.</p>
    <h4>Tracks</h4>
    <p>If <strong>Track required</strong> is on and you list tracks in Project Information, students pick a track at registration and you can limit a room to certain tracks when registering it. Track rooms are <strong>strict</strong>:</p>
    <ul class="hc-list">
        <li>A room limited to tracks only ever takes groups of those tracks.</li>
        <li>A room with no tracks takes groups with no track, or with a track no room serves.</li>
        <li>A group with nowhere to go waits as <span class="hc-badge hc-badge-warn">To be scheduled</span> until a suitable room exists.</li>
    </ul>
    <h4>Breaks</h4>
    <p>A daily break set on the date range is copied to every room as it is assigned. To add or remove a break for one day, open the day's <strong>Edit</strong> and use <strong>Break Periods</strong> (one room, or <strong>All rooms</strong>). Groups after a break move automatically.</p>
    <h4>Editing and removing a date</h4>
    <ul class="hc-list">
        <li><strong>Edit</strong> a day's date and times, including a day that came and went without starting (<span class="hc-badge hc-badge-danger">Overdue</span>) — it cannot be started until you move it.</li>
        <li><strong>Remove Date</strong> moves that day's groups to another open room on their track; a date with a completed presentation on it cannot be removed.</li>
        <li>A finished day can take a <strong>second session</strong>: if every room ended early, add a new date range for the same day.</li>
        <li>When you add a date earlier than the one groups are waiting on, they are pulled forward to the nearest open day.</li>
    </ul>
</div>

<div class="hc-topic" id="ad-queue">
    <h3><x-icon name="list-check" /> Queue strategy and payment</h3>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="sort" /></div><div><div class="hc-option-title">First In, First Out</div><p>Groups present in the order they registered.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="users" /></div><div><div class="hc-option-title">Section Based</div><p>Groups together by section. Type a <strong>Section Order</strong> (e.g. 4C, 4A) or leave it empty for A–Z; unlisted sections follow.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="repeat" /></div><div><div class="hc-option-title">Random Draw</div><p>A fair, recorded random order. The same draw is kept every time the queue is rebuilt; a late group lands at a random spot. Saving another strategy discards the draw.</p></div></div>
    </div>
    <p><strong>Called-Group Waiting Period</strong> is how many minutes a called group has to appear.</p>
    <h4>Payment</h4>
    <p>Tick <strong>Payment verification required</strong>, add one or more <strong>Payment Types</strong> (e.g. "Defense Fee", "Documentation Fee") and write the <strong>Verification Instructions</strong> students will read. The Lead Panelist must verify every type, by reference number, before a group can start. Reference numbers are unique across the whole system.</p>
</div>

<div class="hc-topic" id="ad-generation">
    <h3><x-icon name="refresh" /> How the queue is generated</h3>
    <p>There is no Generate button. The queue builds itself the first time registration is closed and setup is complete (no red dots) and has at least one group. Then:</p>
    <ul class="hc-list">
        <li>Groups are ordered by the chosen strategy.</li>
        <li>Days are filled in date order. Within a day, groups are dealt <strong>round-robin</strong> across that day's rooms, so rooms run in parallel; the next day is used only when the day is full.</li>
        <li>Each room only takes groups its tracks accept.</li>
        <li>Groups that do not fit anywhere are kept as <span class="hc-badge hc-badge-warn">To be scheduled</span> — add a date or room and they are placed.</li>
    </ul>
    <p>Changing the <strong>strategy</strong> before any day has started rebuilds the whole queue — and removes panels already assigned, so settle the strategy before assigning panels. Changing <strong>tracks or rooms</strong> rebuilds only while no panel is assigned; after that, groups are re-placed without touching their panels. Groups registered later are added to the end. The <strong>Capacity Analysis</strong> card on the Schedules tab shows whether the remaining days and rooms can hold every group.</p>
</div>

<div class="hc-topic" id="ad-assignment">
    <h3><x-icon name="user-check" /> Group &amp; Panel Assignment</h3>
    <p>The working list of every group in a category. Use <strong>Category</strong> at the top right to switch categories.</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/admin/panel-assignments/18</div></div>
            <div class="hc-screen">
                <div class="m-grid-2" style="grid-template-columns:2fr 1fr;align-items:start;">
                    <div class="m-col">
                        <div class="m-row m-wrap"><span class="m-input is-empty m-grow">Search</span><span class="m-input">All Rooms ▾</span><span class="m-btn is-soft">Move</span><span class="m-btn is-soft">Defer</span><span class="m-pin">1</span><span class="m-btn"><x-icon name="plus" /> Add Group</span></div>
                        <div class="m-tabs"><span class="is-on">Scheduled</span><span>Completed</span><span>Deferred <span class="hc-badge hc-badge-danger" style="padding:0 .3rem">2</span></span><span>Re-Defense</span><span class="m-pin" style="padding:0">2</span></div>
                        <table class="m-table">
                            <thead><tr><th></th><th>Time</th><th>#</th><th>Group</th><th>Panel</th><th></th></tr></thead>
                            <tbody>
                                <tr><td class="m-band" colspan="6">Tuesday, October 20, 2026 · Room 101</td></tr>
                                <tr><td><span class="m-check is-on"></span></td><td>8:00 AM</td><td>1</td><td>CFD-2026-0004</td><td><span class="hc-badge hc-badge-info">Lead: Santos</span></td><td>⋮</td></tr>
                                <tr class="is-conflict"><td><span class="m-check"></span></td><td>8:20 AM</td><td>2</td><td>CFD-2026-0011</td><td><span class="hc-badge hc-badge-danger">Conflict: Reyes</span> <span class="m-pin">3</span></td><td>⋮</td></tr>
                                <tr><td><span class="m-check is-on"></span></td><td>8:40 AM</td><td>3</td><td>CFD-2026-0009</td><td class="m-faint">No panelists assigned</td><td>⋮ <span class="m-pin">4</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="m-card">
                        <div class="m-label">Assign Panel <span class="m-pin">5</span></div>
                        <div class="m-small m-muted" style="margin:.2rem 0;">2 groups selected</div>
                        <div class="m-pills"><span class="hc-badge hc-badge-success">Panelists 3 / 3</span><span class="hc-badge hc-badge-success">Chair ✓</span><span class="hc-badge hc-badge-muted">Backup</span></div>
                        <div class="m-col" style="margin-top:.4rem;">
                            <div class="m-between m-small"><span class="m-row"><span class="m-check is-on"></span> Santos, M.</span><span class="m-row"><span class="m-radio is-on"></span> Chair</span></div>
                            <div class="m-between m-small"><span class="m-row"><span class="m-check is-on"></span> Garcia, L.</span><span class="m-row"><span class="m-radio"></span> Chair</span></div>
                            <div class="m-between m-small"><span class="m-row"><span class="m-check is-on"></span> Lim, R.</span><span class="m-row"><span class="m-radio"></span> Chair</span></div>
                            <span class="m-input is-empty">Alternate Panel (backup) ▾</span>
                            <span class="m-btn is-block">Assign to Selected Groups</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 15.</strong> Group &amp; Panel Assignment.</figcaption>
    </figure>
    <ul class="hc-pins">
        <li><span class="m-pin">1</span><span><strong>Toolbar</strong> — search (leader, title, reference), room filter, bulk actions for ticked groups, and Add Group.</span></li>
        <li><span class="m-pin">2</span><span><strong>Tabs</strong> — Scheduled, Completed, Deferred and Re-Defense. Rows are grouped by day and room.</span></li>
        <li><span class="m-pin">3</span><span><strong>Red row</strong> — a panelist on this group is double-booked. Hover for details; click the row to replace that panelist.</span></li>
        <li><span class="m-pin">4</span><span><strong>Row menu (⋮)</strong> — Edit, Move, Transfer, Defer, Delete.</span></li>
        <li><span class="m-pin">5</span><span><strong>Assign Panel</strong> — choose the panel for every ticked group at once.</span></li>
    </ul>
    <h4>Adding and editing groups</h4>
    <p><strong>Add Group</strong> registers a group with the same form students use — handy for late or walk-in registrations — and places it in the queue. <strong>Edit</strong> corrects a registration (names, members, titles, track, adviser) without moving it.</p>
</div>

<div class="hc-topic" id="ad-panels">
    <h3><x-icon name="crown" /> Assigning panels</h3>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="check" /> Tick the groups</div><p class="hc-step-text">Usually a whole room's day. All ticked groups must need the same panel size.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="users" /> Tick exactly the required number of panelists</div><p class="hc-step-text">The required number is <strong>Panelists per Room</strong>.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="crown" /> Pick the Chair (Lead)</div><p class="hc-step-text">One of the ticked panelists. The Chair runs the room and gives the official outcome.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">4</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="user-plus" /> Pick the Alternate Panel (backup)</div><p class="hc-step-text">Exactly one backup, not one of the seated panelists.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">5</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="send" /> Assign to Selected Groups</div><p class="hc-step-text">If a ticked group already has a panel, you are asked to confirm replacing it. Panelists are notified.</p></div></li>
    </ol>
    <div class="hc-note hc-note-warn"><x-icon name="alert-triangle" /><p>A panelist cannot be in two places at once. Assigning someone whose time overlaps another of their groups — in any category of your college — is refused and names the clash. Only your own college's panelists can be assigned.</p></div>
    <h4>Substitution requests</h4>
    <p>When a panelist marks themselves unavailable, or someone at a room device asks to take a seat, it appears in <strong>Needs Attention</strong>. Confirm or Reject a seat request; for an unavailability report, choose the replacement with <strong>Assign Replacement</strong>.</p>
</div>

<div class="hc-topic" id="ad-adjust">
    <h3><x-icon name="move" /> Editing, moving and deferring groups</h3>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="move" /></div><div><div class="hc-option-title">Move</div><p>Change a group's position within its room. The list shows the room's order; already-presented positions cannot be taken.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="transfer" /></div><div><div class="hc-option-title">Transfer</div><p>Send to another room or day — ongoing or upcoming only, and only a room that accepts the group's track. Lands at the end of that room.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon is-danger"><x-icon name="defer" /></div><div><div class="hc-option-title">Defer</div><p>Take the group out of the active queue, with a reason. It appears under Deferred.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon is-success"><x-icon name="reinsert" /></div><div><div class="hc-option-title">Reinsert</div><p>Put a deferred group back, at a position you choose. In a payment category you can <strong>Verify Payment</strong> for it first.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon is-danger"><x-icon name="trash" /></div><div><div class="hc-option-title">Delete</div><p>Removes the group's place in the queue (its registration stays). Refused once evaluations exist.</p></div></div>
    </div>
    <p>Every action works on one row (⋮) or on all ticked rows (toolbar). Times recalculate automatically, panelists are told when their groups move, and panel conflicts are re-checked.</p>
    <div class="hc-panel">
        <div class="hc-legend-group">During a running day</div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-success">Ongoing / Paused</span><span>Untouchable — no checkbox, no menu. Only the Lead can defer it.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-brand">Called</span><span>Can be changed. The room is told to call the next group.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-muted">Completed</span><span>Never moves.</span></div>
        <div class="hc-legend-row"><span class="hc-badge hc-badge-warn">Day already over</span><span>The group can only be Transferred to an open day.</span></div>
    </div>
</div>

<div class="hc-topic" id="ad-redefense">
    <h3><x-icon name="repeat" /> Re-defense</h3>
    <p>When a group's official outcome is <span class="hc-badge hc-badge-info">Re-Defense</span>, it appears under the <strong>Re-Defense</strong> tab with every attempt, its date, outcome and <strong>View Evaluation</strong>. Press <strong>Reinsert for Another Attempt</strong> to schedule the next attempt.</p>
    <ul class="hc-list">
        <li>The new attempt goes to the <strong>last open day</strong>, in the room that finishes last, at the <strong>end of the queue</strong> — and stays last even when other groups are added.</li>
        <li>It starts <strong>without a panel</strong>; assign one as usual. The earlier attempt's panel and scores are kept as history.</li>
        <li>A Failed group never gets another attempt; it must register again.</li>
    </ul>
</div>

<div class="hc-topic" id="ad-event">
    <h3><x-icon name="clock" /> Event Control</h3>
    <p>Run the presentation days. The page shows the featured day (today's, or the next one) with a tab per room; <strong>View All Days</strong> lists the rest.</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/admin/event-control/18</div></div>
            <div class="hc-screen">
                <div class="m-between"><span class="m-title">Tuesday, October 20, 2026 <span class="hc-badge hc-badge-success">Active</span></span><span class="m-btn is-ghost">End Category</span></div>
                <div class="m-tabs" style="margin:.4rem 0 .6rem;"><span class="is-on">Room 101</span><span>Room 102</span></div>
                <div class="m-grid-2" style="align-items:start;">
                    <div class="m-card">
                        <div class="m-label">Room Data <span class="m-pin">1</span></div>
                        <div class="m-grid-3 m-small" style="margin:.3rem 0;"><div><div class="m-faint m-xs">Room</div>101</div><div><div class="m-faint m-xs">Panelists</div>3</div><div><div class="m-faint m-xs">Session</div><span class="hc-badge hc-badge-success">Active</span></div></div>
                        <div class="m-card is-alt m-small"><div class="m-label">Room Session Account <span class="m-pin">2</span></div>Username <b>cfd-room101</b><br>Password •••••••• <span class="m-btn is-soft" style="margin-left:.3rem">Reset Password</span></div>
                        <div class="m-row" style="margin-top:.45rem;"><span class="m-btn is-soft"><x-icon name="pause" /> Pause</span><span class="m-btn is-danger"><x-icon name="stop" /> End Room</span><span class="m-pin">3</span></div>
                    </div>
                    <div class="m-card">
                        <div class="m-label">Terminals <span class="m-pin">4</span></div>
                        <table class="m-table">
                            <thead><tr><th>#</th><th>Device</th><th>Status</th><th>Panelist</th></tr></thead>
                            <tbody>
                                <tr><td>1</td><td><span class="hc-badge hc-badge-brand">Claimed</span></td><td><span class="hc-badge hc-badge-success">Connected</span></td><td>Santos</td></tr>
                                <tr><td>2</td><td><span class="hc-badge hc-badge-brand">Claimed</span></td><td><span class="hc-badge hc-badge-success">Connected</span></td><td>Garcia</td></tr>
                                <tr><td>3</td><td><span class="hc-badge hc-badge-muted">Unclaimed</span></td><td><span class="hc-badge hc-badge-muted">Empty</span></td><td class="m-faint">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <figcaption><strong>Figure 16.</strong> A room in Event Control.</figcaption>
    </figure>
    <ul class="hc-pins">
        <li><span class="m-pin">1</span><span><strong>Room Data</strong> — room, panel size, session status, start time, today's counts and the full queue (View All).</span></li>
        <li><span class="m-pin">2</span><span><strong>Room Session Account</strong> — for setting up the room's devices. Reset Password shows a new one.</span></li>
        <li><span class="m-pin">3</span><span><strong>Start Room / Pause / Resume / End Room</strong>.</span></li>
        <li><span class="m-pin">4</span><span><strong>Terminals</strong> — which devices are set up and who is connected, with Disconnect and Release Device. The <x-icon name="info" style="width:.85em;height:.85em;vertical-align:-.1em" /> button explains them.</span></li>
    </ul>
    <ol class="hc-steps">
        <li class="hc-step"><div class="hc-step-marker">1</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="play" /> Start Room</div><p class="hc-step-text">Available on the day, once its planned time arrives. The first room started makes the day <span class="hc-badge hc-badge-success">Active</span>. Each room creates its terminals and room account.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">2</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="tablet" /> Set up the devices</div><p class="hc-step-text">See <a href="#rd-setup">Setting up a device</a>. From then on the Lead Panelist runs the room.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">3</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="pause" /> Pause if needed</div><p class="hc-step-text">An emergency stop for the whole room, available at any time.</p></div></li>
        <li class="hc-step"><div class="hc-step-marker">4</div><div class="hc-step-body"><div class="hc-step-head"><x-icon name="stop" /> End Room</div><p class="hc-step-text">When the room is done. Refused while a group is presenting. The confirmation lists groups still queued in it.</p></div></li>
    </ol>
</div>

<div class="hc-topic" id="ad-day-end">
    <h3><x-icon name="calendar-copy" /> How a day and a category end</h3>
    <ul class="hc-list">
        <li>A day ends when <strong>every room</strong> on it has been started and ended. If you forget, it ends automatically after midnight.</li>
        <li>At the end of a day, unfinished groups move to the <strong>front</strong> of the next day's matching room, and deferred groups go to the <strong>end</strong> of the category's queue. With no later day they wait as "To be scheduled" — add a date.</li>
        <li>A day that was never started is <strong>cancelled</strong> after it passes, and its groups move on the same way.</li>
        <li><strong>End Category</strong> (Event Control) closes the category once no group still has to present (a pending re-defense blocks it). Nothing is deleted; registration and new dates stop.</li>
        <li>An ended category can then be archived in Presentation Setup, or deleted from Event Control.</li>
    </ul>
</div>

<div class="hc-topic" id="ad-reports">
    <h3><x-icon name="file-text" /> Reports</h3>
    <p><strong>Generated Grades</strong> lists every student of the category's graded groups:</p>
    <figure class="hc-figure">
        <div class="hc-window">
            <div class="hc-window-bar"><div class="hc-window-dots"><i></i><i></i><i></i></div><div class="hc-window-url">/admin/reports/18/grades</div></div>
            <div class="hc-screen">
                <div class="m-grid-2" style="grid-template-columns:2fr 1fr;align-items:start;">
                    <div class="m-card"><div class="m-label">Filters</div><div class="m-row m-wrap" style="margin-top:.3rem;"><span class="m-input is-empty" style="min-width:6rem">Name</span><span class="m-input">All sections ▾</span><span class="m-input">All dates ▾</span><span class="m-input">Section, then name ▾</span></div></div>
                    <div class="m-card"><div class="m-row"><x-icon name="trophy" style="width:.9rem;height:.9rem;color:var(--hc-accent)" /><span class="m-label">Best Group</span></div><div class="m-bold">CFD-2026-0005</div><div class="m-small">Group Grade 96.40</div></div>
                </div>
                <table class="m-table" style="margin-top:.5rem;">
                    <thead><tr><th>Name</th><th>Section</th><th>Individual Avg.</th><th>Group Grade</th><th>Total</th><th>Outcome</th><th>Fee</th></tr></thead>
                    <tbody>
                        <tr><td>Dela Cruz, Juan S.</td><td>4B</td><td>92.00</td><td>84.67</td><td>86.87</td><td><span class="hc-badge hc-badge-success">Pass with Minor Revision</span></td><td>Paid</td></tr>
                        <tr><td>Reyes, Ana L.</td><td>4B</td><td>90.00</td><td>84.67</td><td>86.27</td><td><span class="hc-badge hc-badge-success">Pass with Minor Revision</span></td><td>Paid</td></tr>
                    </tbody>
                </table>
                <div class="m-row" style="margin-top:.45rem;"><span class="m-btn is-soft"><x-icon name="download" /> Export to Excel</span><span class="m-btn is-soft"><x-icon name="download" /> Export to PDF</span></div>
            </div>
        </div>
        <figcaption><strong>Figure 17.</strong> Generated Grades.</figcaption>
    </figure>
    <ul class="hc-list">
        <li><strong>Filters</strong> — name, section, presentation date, and sort (section then name, or last name). Exports follow the filters.</li>
        <li><strong>Evaluation</strong> — view the evaluation sheets the panel submitted for a group.</li>
        <li><strong>Best Group</strong> and <strong>Best Presenter</strong> — the category's leaders; <strong>View</strong> shows the top five, exportable to PDF.</li>
        <li><strong>Panelist Sign-off Sheet</strong> — every panelist and how many groups they evaluated, with signature lines. View, print or export to PDF at any time.</li>
        <li><strong>Recommendations Summary</strong> — the panel's remarks and comments per group, viewable and exportable.</li>
        <li>Groups awaiting a re-defense are left out until their final attempt; Failed groups appear, marked in red.</li>
    </ul>
    <p>How the numbers are computed is explained in <a href="#rf-grading">How scores and grades are computed</a>.</p>
</div>

<div class="hc-topic" id="ad-analytics">
    <h3><x-icon name="bar-chart" /> Analytics</h3>
    <p>Compares the <strong>planned</strong> schedule with what <strong>actually happened</strong>, across all categories or one: presentations measured, median duration against the configured slot, hours used of hours booked, room utilization, days run, and time from call to start. When there is enough data it <strong>recommends a slot length</strong> (based on the 90th percentile, so almost every group fits) with a link to change it in Presentation Setup.</p>
    <div class="hc-note hc-note-info"><x-icon name="info" /><p>Only presentations run on a room device are measured; cancelled or never-started days are ignored. Each figure shows how many presentations it is based on.</p></div>
</div>

<div class="hc-topic" id="ad-attention">
    <h3><x-icon name="bell" /> Needs Attention</h3>
    <p>The dashboard's Needs Attention card (and the chip on Group &amp; Panel Assignment) collects:</p>
    <div class="hc-table-wrap">
        <table class="hc-table">
            <thead><tr><th>Item</th><th>What to do</th></tr></thead>
            <tbody>
                <tr><td>Substitution request</td><td>Confirm or Reject the proposed seat swap.</td></tr>
                <tr><td>Panelist unavailable</td><td>Assign a replacement on the group.</td></tr>
                <tr><td>Panel schedule conflict</td><td>Replace the double-booked panelist (click the red row).</td></tr>
                <tr><td>Group deferred</td><td>Review the deferred group and reinsert it.</td></tr>
                <tr><td>Groups waiting, no open date</td><td>Add a presentation date or room.</td></tr>
                <tr><td>Presentation date overdue</td><td>Edit the date — a past, never-started day cannot be started.</td></tr>
                <tr><td>Day could not end</td><td>A presentation is still running in a room; complete or defer it.</td></tr>
            </tbody>
        </table>
    </div>
</div>
