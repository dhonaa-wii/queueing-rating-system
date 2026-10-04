@php
    $faqGroups = [
        'fq-account' => ['icon' => 'key', 'title' => 'Accounts and signing in', 'items' => [
            ['I forgot my password. How do I reset it?', 'There is no self-service reset. Panelists ask their Administrator (<strong>Panelist Management → Reset Password</strong>); Administrators ask the Super Administrator (<strong>Admin Accounts → Reset Password</strong>). You receive a new temporary password and must change it when you sign in.'],
            ['I never received my username or password.', 'Credentials are shown once, to whoever created your account. Ask them to reset your password to issue a new temporary one.'],
            ['Why am I sent to Change Password every time I sign in?', 'You are still using a temporary password. Complete the Change Password form once — the rest of the system unlocks afterwards.'],
            ['The sign-in page says my account is inactive.', 'Your account was deactivated. Contact your Administrator (panelists) or the Super Administrator (administrators).'],
            ['Can I change my username?', 'No. You can correct your name and contact number in <strong>Settings</strong>, but the username stays fixed.'],
            ['Do students need an account?', 'No. Registration and schedules are public. Students never sign in.'],
            ['Can I be signed in on my phone and a room device at the same time?', 'Yes. Your personal account and a room device are separate. Log out of the room device with its own Log Out button when you leave.'],
        ]],
        'fq-students' => ['icon' => 'graduation-cap', 'title' => 'Registration and schedules', 'items' => [
            ['The Register button is disabled.', 'Registration is not open: it either opens later (the card says when) or has closed. Late groups can be added by the Administrator.'],
            ['I made a mistake in our registration. Can I fix it?', 'Not yourself — registrations cannot be edited after submitting. Ask the Administrator to correct it. <strong>Do not register again</strong>; a second registration with the same leader or title is refused.'],
            ['It says "An active registration with matching information already exists".', 'Your group (same leader name, or the same project title in Standard mode) is already registered in this category. Search for it on the schedule page.'],
            ['Why is my section rejected?', 'Enter only the year level and a letter, like <code>4B</code> — no program name.'],
            ['I lost our group reference.', 'Open the category\'s schedule and search your leader\'s name or project title. The group status card shows everything, including the reference.'],
            ['When will we know our schedule?', 'After registration closes and the Administrator finishes setup, the queue is generated automatically. Check the schedule page.'],
            ['Our time keeps changing. Why?', 'While the day runs, times are live estimates that follow how fast the room is actually going. Come early; a group ahead may finish early.'],
            ['Our group shows "To be scheduled".', 'You still have to present, but there is currently no open day or room for you. Your place is kept; the Administrator will add a date.'],
            ['We were deferred. What happens now?', 'You stay in the system. The Deferred list shows why. The Administrator reinserts you, or you are moved to the end of the queue at the end of the day.'],
            ['We got Re-Defense. Do we register again?', 'No. The Administrator schedules your next attempt; it comes after every other group.'],
            ['We Failed. Can we present again?', 'Yes, by registering again as a new group in the same or a later category.'],
            ['Our payment reference number was refused.', 'Each reference number can be recorded only once in the whole system. Check you typed it correctly and that it is not another group\'s receipt.'],
        ]],
        'fq-panelists' => ['icon' => 'users', 'title' => 'Panelists and room devices', 'items' => [
            ['Call Next is disabled.', 'Every terminal in the room must have a panelist connected, the room must not be on a break, and nobody may already be called or presenting. Hover over the button to see which.'],
            ['Start is disabled.', 'A group must be called first, and in a payment category every payment type must be verified.'],
            ['Complete is disabled.', 'Every connected panelist must submit their evaluation first.'],
            ['I submitted my evaluation with a mistake.', 'Submitted evaluations are final. Tell your Administrator.'],
            ['I don\'t see the Presentation Controls.', 'Only the Lead Panelist (Chair) of the room\'s groups gets them, on whichever device they sign in to. Check your role in My Assignments.'],
            ['The QR code will not scan.', 'Make sure your phone is signed in to your own panelist account, give the browser camera permission, and wait for the code to refresh. Or use <strong>Log In Directly</strong> on the device.'],
            ['"This terminal seat is already occupied."', 'Another panelist is signed in on it. Ask them to log out, or ask an Administrator to Disconnect it from Event Control.'],
            ['"You are already connected to another seat in this room."', 'Log out of your other terminal first; one panelist, one seat.'],
            ['The device asks for a room username, not mine.', 'It has not been set up yet (or was released). An Administrator signs it in with the room account — see Setting up a device.'],
            ['A panelist on my panel did not come. What do I do?', 'Their backup signs in to a terminal and picks the seat they are filling. Without a backup, another panelist can sign in and request the seat; an Administrator confirms it.'],
            ['I cannot attend one of my assigned groups.', 'Use <strong>Mark Unavailable</strong> in My Assignments, and tell your Administrator directly if it is today.'],
            ['The device warned "Scheduled break at …".', 'The next group would run into the room\'s break. If the current group is still on when the break comes, the Lead chooses Cancel Break or Finish Group, Then Break.'],
        ]],
        'fq-admins' => ['icon' => 'layers', 'title' => 'Administration', 'items' => [
            ['The queue has not been generated.', 'It generates when registration has <strong>closed</strong>, setup has no red dots (dates, rooms, panel count, queue strategy, evaluation form) and at least one group exists. Open Presentation Setup to see what is missing.'],
            ['Some groups say "To be scheduled".', 'The days and rooms are full, or no room accepts their track. Add a date, a room, or a room for that track; they are placed automatically.'],
            ['Assign Panel is refused with a conflict.', 'A chosen panelist already sits on another group at an overlapping time (any category of your college). Choose someone else, or move one of the groups.'],
            ['Why can\'t I Move a group?', 'It is presenting, has already presented, is a pending re-defense (always last), or its day is over (use Transfer).'],
            ['Start Room is disabled.', 'The day\'s planned start has not arrived, the date is overdue (edit it), the room is already started or closed, or Panelists per Room is not set.'],
            ['End Room is refused.', 'A group is presenting or paused in that room. The Lead completes or defers it first.'],
            ['I forgot to end the day.', 'It ends automatically after midnight, and unfinished groups carry over to the next day.'],
            ['Can I change the presentation mode?', 'Only until the category\'s first day has started.'],
            ['Can I delete a date?', 'Yes, unless a presentation was completed on it. Its groups move to other open rooms.'],
            ['Can I edit a published evaluation form?', 'Yes — open it and press Edit Form. Criteria with recorded scores cannot be deleted, and changing weights does not re-score past evaluations.'],
            ['I cannot see another college\'s categories or panelists.', 'By design: an Administrator only sees their own college.'],
            ['End Category is disabled.', 'Some group still has to present, or a re-defense is pending.'],
            ['How do I get grades into a spreadsheet?', 'Reports → Generated Grades → <strong>Export to Excel</strong> (or PDF). The export follows the filters you set.'],
        ]],
        'fq-technical' => ['icon' => 'monitor', 'title' => 'Technical problems', 'items' => [
            ['A page looks broken or out of date.', 'Reload it. If that does not help, clear the browser cache or try another up-to-date browser.'],
            ['"Page Expired" (419) when I submit a form.', 'The page was open too long. Reload and submit again.'],
            ['The site says it is under maintenance or restoring.', 'The Super Administrator is working on the system. Try again later.'],
            ['Changes don\'t appear on other screens.', 'Most pages refresh every few seconds. Wait a moment or reload.'],
            ['An old tablet does not work.', 'Open <code>/room-session/device-check</code> on it and update the browser, or use another device.'],
            ['How do I save this guide as a PDF?', 'Press <strong>Download PDF</strong> at the top, choose "Save as PDF" as the printer and keep "Background graphics" on.'],
        ]],
    ];
    $chevron = '<svg class="hc-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
@endphp

@foreach ($faqGroups as $groupId => $group)
    <div class="hc-topic" id="{{ $groupId }}">
        <h3><x-icon :name="$group['icon']" /> {{ $group['title'] }}</h3>
        <div class="hc-faq">
            @foreach ($group['items'] as $i => $item)
                <details id="{{ $groupId }}-{{ $i + 1 }}">
                    <summary><span class="hc-faq-q">Q</span><span class="hc-faq-text">{{ $item[0] }}</span>{!! $chevron !!}</summary>
                    <div class="hc-faq-a"><p>{!! $item[1] !!}</p></div>
                </details>
            @endforeach
        </div>
    </div>
@endforeach

<div class="hc-topic" id="fq-support">
    <h3><x-icon name="help-circle" /> Getting more help</h3>
    <div class="hc-options">
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="graduation-cap" /></div><div><div class="hc-option-title">Students</div><p>Contact your college's Administrator or your capstone coordinator. Have your group reference ready.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="users" /></div><div><div class="hc-option-title">Panelists</div><p>Contact the Administrator who registered you. On the day, ask the Administrator running Event Control.</p></div></div>
        <div class="hc-option"><div class="hc-option-icon"><x-icon name="layers" /></div><div><div class="hc-option-title">Administrators</div><p>Contact the Super Administrator for accounts, colleges, school years, backups or system problems.</p></div></div>
    </div>
    <div class="hc-note hc-note-tip"><x-icon name="lightbulb" /><p>When reporting a problem, say what page you were on, what you clicked, the exact message shown, and the time. A screenshot helps.</p></div>
</div>
