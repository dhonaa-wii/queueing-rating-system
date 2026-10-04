{{-- Help Center / User Guide (route `help`, public). One page for every
     role: students, panelists, room devices, administrators and super
     administrators. Standalone rather than inside a role layout so it reads
     the same signed in or out, stays white in both themes, and prints to a
     clean PDF (Download PDF = the browser's Save as PDF on this page's print
     stylesheet). The chapter list below drives the sidebar, the printed
     Contents page and the search index, so a topic added to a chapter
     partial must also be listed here with the same id. --}}
@php
    $user = auth()->user();
    $roleCode = $user?->roleCode();
    $youAre = match ($roleCode) {
        'SUPER_ADMIN' => 'super-admins',
        'ADMIN' => 'administrators',
        'PANELIST' => 'panelists',
        default => null,
    };
    $backUrl = $user ? route($user->dashboardRouteName()) : route('landing');
    $backLabel = $user ? 'Back to Dashboard' : 'Back to Home';
    // "Updated" is when the guide's own files last changed, not today's date.
    $guideFiles = array_merge(glob(resource_path('views/help/*.php')) ?: [], glob(resource_path('views/help/*/*.php')) ?: []);
    $guideUpdated = $guideFiles ? \Illuminate\Support\Carbon::createFromTimestamp(max(array_map('filemtime', $guideFiles))) : now();

    $chapters = [
        ['id' => 'getting-started', 'icon' => 'home', 'title' => 'Getting Started', 'sub' => 'What the system is, who uses it, and the basics every user needs.', 'topics' => [
            'gs-about' => 'What the system does',
            'gs-roles' => 'Who uses the system',
            'gs-requirements' => 'What you need',
            'gs-access' => 'Opening the system',
            'gs-sign-in' => 'Signing in',
            'gs-first-login' => 'Your first sign-in',
            'gs-navigation' => 'Finding your way around',
            'gs-settings' => 'Profile & account settings',
            'gs-appearance' => 'Light and dark mode',
            'gs-sign-out' => 'Signing out',
        ]],
        ['id' => 'students', 'icon' => 'graduation-cap', 'title' => 'Students & Research Groups', 'sub' => 'Register your group, follow the queue and get ready for presentation day. No account needed.', 'topics' => [
            'st-overview' => 'No account needed',
            'st-categories' => 'Browsing presentation categories',
            'st-register' => 'Registering your group',
            'st-rules' => 'Registration rules',
            'st-confirmation' => 'Your group reference',
            'st-schedule' => 'Viewing the schedule and queue',
            'st-times' => 'Planned, expected and actual times',
            'st-day' => 'On presentation day',
            'st-outcomes' => 'Outcomes and what comes next',
        ]],
        ['id' => 'panelists', 'icon' => 'users', 'title' => 'Panelists', 'sub' => 'See your assignments, join a room terminal, evaluate groups and run the room as Lead.', 'topics' => [
            'pn-account' => 'Your panelist account',
            'pn-dashboard' => 'Dashboard',
            'pn-assignments' => 'My Assignments',
            'pn-unavailable' => 'Marking yourself unavailable',
            'pn-notifications' => 'Notifications',
            'pn-schedule' => 'View Schedule',
            'pn-roles' => 'Lead Panelist, Member and Backup',
            'pn-join' => 'Joining a room terminal',
            'pn-evaluate' => 'Evaluating a group',
            'pn-control' => 'Running the room (Lead Panelist)',
            'pn-breaks' => 'Scheduled breaks',
            'pn-substitute' => 'Backups and substitutions',
        ]],
        ['id' => 'room-devices', 'icon' => 'tablet', 'title' => 'Room Session Devices', 'sub' => 'Preparing the tablets or laptops that panelists use inside each room.', 'topics' => [
            'rd-what' => 'What a room device is',
            'rd-check' => 'Checking a device',
            'rd-setup' => 'Setting up a device',
            'rd-screen' => 'The device screen',
            'rd-release' => 'Releasing a device',
        ]],
        ['id' => 'administrators', 'icon' => 'layers', 'title' => 'Administrators', 'sub' => 'Configure categories, build evaluation forms, assign panels, run the event and produce reports.', 'topics' => [
            'ad-workflow' => 'The presentation workflow',
            'ad-dashboard' => 'Dashboard',
            'ad-panelists' => 'Panelist Management',
            'ad-evaluation' => 'Evaluation Library',
            'ad-setup' => 'Presentation Setup',
            'ad-schedules' => 'Dates, rooms, tracks and breaks',
            'ad-queue' => 'Queue strategy and payment',
            'ad-generation' => 'How the queue is generated',
            'ad-assignment' => 'Group & Panel Assignment',
            'ad-panels' => 'Assigning panels',
            'ad-adjust' => 'Editing, moving and deferring groups',
            'ad-redefense' => 'Re-defense',
            'ad-event' => 'Event Control',
            'ad-day-end' => 'How a day and a category end',
            'ad-reports' => 'Reports',
            'ad-analytics' => 'Analytics',
            'ad-attention' => 'Needs Attention',
        ]],
        ['id' => 'super-admins', 'icon' => 'shield-check', 'title' => 'Super Administrators', 'sub' => 'Accounts, institutional settings, audit trail, backups and system health.', 'topics' => [
            'sa-dashboard' => 'Dashboard',
            'sa-admins' => 'Admin Accounts',
            'sa-panelists' => 'Panelist Oversight',
            'sa-application' => 'Application Settings',
            'sa-security' => 'Security Settings',
            'sa-audit' => 'Audit Log',
            'sa-backups' => 'Backups',
            'sa-restore' => 'Restoring a backup',
            'sa-maintenance' => 'Health and maintenance',
        ]],
        ['id' => 'reference', 'icon' => 'list-check', 'title' => 'Reference', 'sub' => 'Statuses, outcomes, grade computation, permissions and terms in one place.', 'topics' => [
            'rf-statuses' => 'Status reference',
            'rf-outcomes' => 'Presentation outcomes',
            'rf-grading' => 'How scores and grades are computed',
            'rf-permissions' => 'Who can do what',
            'rf-glossary' => 'Glossary',
        ]],
        ['id' => 'faq', 'icon' => 'help-circle', 'title' => 'FAQ & Troubleshooting', 'sub' => 'Answers to the questions users ask most, grouped by topic.', 'topics' => [
            'fq-account' => 'Accounts and signing in',
            'fq-students' => 'Registration and schedules',
            'fq-panelists' => 'Panelists and room devices',
            'fq-admins' => 'Administration',
            'fq-technical' => 'Technical problems',
            'fq-support' => 'Getting more help',
        ]],
    ];

    $roleCards = [
        ['id' => 'students', 'icon' => 'graduation-cap', 'title' => 'Student / Research Group', 'text' => 'Register your group and track your place in the queue.'],
        ['id' => 'panelists', 'icon' => 'users', 'title' => 'Panelist', 'text' => 'Evaluate groups and, as Lead, run the room.'],
        ['id' => 'administrators', 'icon' => 'layers', 'title' => 'Administrator', 'text' => 'Set up categories, panels, the event day and reports.'],
        ['id' => 'super-admins', 'icon' => 'shield-check', 'title' => 'Super Administrator', 'text' => 'Manage accounts, settings, audit and backups.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ARPQRS User Guide</title>
    <meta name="description" content="User guide for the Academic Research Presentation Queueing and Rating System.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@1,400&display=swap" rel="stylesheet">
    @include('help.partials.styles')
</head>
<body>
    <header class="hc-topbar">
        <button type="button" class="hc-btn hc-btn-soft hc-toc-toggle" id="hc-toc-toggle" aria-label="Contents" aria-controls="hc-toc" aria-expanded="false">
            <x-icon name="list-check" />
        </button>
        <a href="{{ route('help') }}" class="hc-topbar-brand">
            <span class="brand-mark">ARPQRS</span>
        </a>
        <span class="hc-topbar-divider" aria-hidden="true"></span>
        <span class="hc-topbar-title">User Guide</span>

        <div class="hc-search" role="search">
            <x-icon name="search" class="hc-search-icon" />
            <input type="search" id="hc-search" class="hc-search-input" placeholder="Search the guide" autocomplete="off" aria-label="Search the guide" aria-controls="hc-search-results">
            <kbd class="hc-search-kbd" aria-hidden="true">/</kbd>
            <div class="hc-search-results" id="hc-search-results" role="listbox"></div>
        </div>

        <div class="hc-topbar-actions">
            <a href="{{ route('help.pdf') }}" class="hc-btn hc-btn-solid" download="ARPQRS User Guide.pdf"><x-icon name="download" /><span class="hc-btn-label">Download PDF</span></a>
            <a href="{{ $backUrl }}" class="hc-btn hc-btn-soft"><x-icon name="arrow-left" /><span class="hc-btn-label">{{ $backLabel }}</span></a>
        </div>
    </header>

    <div class="hc-shell">
        <nav class="hc-toc" id="hc-toc" aria-label="Contents">
            <p class="hc-toc-label">Contents</p>
            <ol>
                @foreach ($chapters as $i => $chapter)
                    <li class="hc-toc-chapter" data-toc-chapter="{{ $chapter['id'] }}">
                        <a href="#{{ $chapter['id'] }}"><x-icon :name="$chapter['icon']" /> <span>{{ $i + 1 }}. {{ $chapter['title'] }}</span></a>
                        <ol class="hc-toc-sub">
                            @foreach ($chapter['topics'] as $topicId => $topicTitle)
                                <li><a href="#{{ $topicId }}" data-toc-topic="{{ $topicId }}">{{ $topicTitle }}</a></li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </nav>

        <main class="hc-main">
            <div class="hc-content">
                <section class="hc-cover" id="top">
                    <div class="hc-cover-head">
                        <div class="hc-cover-icon"><x-icon name="book-open" /></div>
                        <div>
                            <div class="hc-eyebrow">Help Center</div>
                            <h1>User Guide</h1>
                            <p class="hc-cover-lead">Academic Research Presentation Queueing and Rating System (ARPQRS) — registration, scheduling, live queueing and panel evaluation for academic research presentations.</p>
                        </div>
                    </div>
                    <div class="hc-cover-meta">
                        <span><x-icon name="users" /> For students, panelists, administrators and super administrators</span>
                        <span><x-icon name="calendar" /> Updated {{ $guideUpdated->format('F j, Y') }}</span>
                    </div>

                    <div class="hc-eyebrow" style="margin-top: 2rem;">Start with your role</div>
                    <div class="hc-roles">
                        @foreach ($roleCards as $card)
                            <a href="#{{ $card['id'] }}" class="hc-role {{ $youAre === $card['id'] ? 'is-you' : '' }}">
                                <div class="hc-option-icon"><x-icon :name="$card['icon']" /></div>
                                <div>
                                    <div class="hc-role-title">{{ $card['title'] }} @if ($youAre === $card['id'])<span class="hc-you hc-screen-only">You</span>@endif</div>
                                    <p class="hc-role-text">{{ $card['text'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="hc-note hc-note-tip hc-screen-only" style="margin-top: 1.25rem;">
                        <x-icon name="lightbulb" />
                        <p>Press <kbd>/</kbd> to search. Use <strong>Download PDF</strong> for a copy of the whole guide.</p>
                    </div>

                    <div class="hc-print-only" style="margin-top: auto; padding-top: 12mm; font-size: 8.5pt; color: #6B6560;">
                        BSIT Department, Cagayan State University &ndash; Aparri
                    </div>
                </section>

                <section class="hc-print-only hc-print-contents" aria-hidden="true">
                    <h2>Contents</h2>
                    <ol>
                        @foreach ($chapters as $i => $chapter)
                            <li>
                                <div class="hc-pc-chapter"><span>{{ $i + 1 }}</span>{{ $chapter['title'] }}</div>
                                <div class="hc-pc-topics">{{ implode(' · ', $chapter['topics']) }}</div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @foreach ($chapters as $i => $chapter)
                    <section class="hc-chapter" id="{{ $chapter['id'] }}" data-chapter="{{ $chapter['id'] }}" data-search-title="{{ $chapter['title'] }}">
                        <div class="hc-chapter-head">
                            <div class="hc-chapter-icon"><x-icon :name="$chapter['icon']" /></div>
                            <div>
                                <div class="hc-chapter-num">Chapter {{ $i + 1 }}</div>
                                <h2>{{ $chapter['title'] }}</h2>
                                <p class="hc-chapter-sub">{{ $chapter['sub'] }}</p>
                            </div>
                        </div>
                        @include('help.sections.' . $chapter['id'])
                    </section>
                @endforeach

                <footer class="hc-footer">
                    <span><span class="brand-mark">ARPQRS</span> &middot; Academic Research Presentation Queueing and Rating System</span>
                    <span>&copy; {{ date('Y') }} BSIT Department, Cagayan State University &ndash; Aparri</span>
                </footer>
            </div>
        </main>
    </div>

    <button type="button" class="hc-btn hc-btn-soft hc-top-link" id="hc-top" aria-label="Back to top"><x-icon name="arrow-right" style="transform: rotate(-90deg)" /></button>

    @include('help.partials.script')
</body>
</html>
