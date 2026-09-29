<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\AccountStatus;
use App\Models\AttemptDecision;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptPanelParticipation;
use App\Models\AttemptRatingSummary;
use App\Models\AttemptRequirement;
use App\Models\AttemptSchedule;
use App\Models\CapacityAnalysisSnapshot;
use App\Models\CategoryAnnouncement;
use App\Models\CategoryRoom;
use App\Models\CategoryStatus;
use App\Models\ConnectionStatus;
use App\Models\EndOfDayProcessingLog;
use App\Models\EvaluationForm;
use App\Models\EvaluationFormVersion;
use App\Models\EvaluationScore;
use App\Models\EvaluationSubmission;
use App\Models\EvaluationSubmissionStudentScore;
use App\Models\EventDateStatus;
use App\Models\Notification;
use App\Models\PanelistProfile;
use App\Models\PanelSubstitutionRequest;
use App\Models\PaymentVerification;
use App\Models\PresentationAction;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\PresentationEvent;
use App\Models\PresentationMode;
use App\Models\PresentationOutcome;
use App\Models\PresentationPause;
use App\Models\PresentationRun;
use App\Models\ProposedTitle;
use App\Models\QueueAdjustment;
use App\Models\QueueEntry;
use App\Models\QueueStrategy;
use App\Models\ResearchGroup;
use App\Models\Role;
use App\Models\RoomSession;
use App\Models\RoomSessionAccount;
use App\Models\RoomTerminal;
use App\Models\RoomUseStatus;
use App\Models\ScheduleBreak;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TerminalAccessToken;
use App\Models\TerminalConnection;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use App\Services\EvaluationFormBuilderService;
use App\Services\EvaluationSubmissionService;
use App\Services\EventActivationService;
use App\Services\PanelAssignmentService;
use App\Services\PresentationControlService;
use App\Services\QueueGenerationService;
use App\Services\ResearchGroupRegistrationService;
use App\Services\TerminalConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds a clean, re-runnable set of demo data for recording the system
 * presentation video. Every run deletes the three demo categories below (and
 * only those — matched by exact name) and rebuilds them around --date, so a
 * take that pressed Start Room / Complete can simply be reset.
 *
 * Everything past the category shell is written through the same services the
 * app itself uses (registration, queue generation, panel assignment, Start
 * Room, terminal connection, presentation control, evaluation submission),
 * with the clock moved via Carbon::setTestNow() so the finished category's
 * history carries believable timestamps.
 */
class PrepareDemoData extends Command
{
    protected $signature = 'demo:prepare
        {--date= : Recording day (Y-m-d). Defaults to today.}
        {--start=08:00 : Start time of the live demo day}
        {--end=21:00 : End time of the live demo day}';

    protected $description = 'Delete and rebuild the demo categories, panelists and evaluation form used for the presentation video.';

    private const OPEN_CATEGORY = 'Capstone Proposal Defense';

    private const LIVE_CATEGORY = 'Final Capstone Defense';

    private const DONE_CATEGORY = 'Capstone Pre-Oral Defense';

    private const FORM_NAME = 'Capstone Project Evaluation Form';

    private const PANELIST_PASSWORD = 'Panel@2026';

    private const PANELISTS = [
        'elena' => ['Elena', 'Ramos', 'Software Engineering'],
        'francisco' => ['Francisco', 'Dela Cruz', 'Database Systems'],
        'gloria' => ['Gloria', 'Mercado', 'Human-Computer Interaction'],
        'hernan' => ['Hernan', 'Castillo', 'Networking and IoT'],
        'isabel' => ['Isabel', 'Navarro', 'Data Analytics'],
        'jonathan' => ['Jonathan', 'Lim', 'Mobile Development'],
        'katherine' => ['Katherine', 'Uy', 'Web Development'],
        'leonardo' => ['Leonardo', 'Garcia', 'Project Management'],
    ];

    private const RUBRIC = [
        ['Presentation and Delivery', 15, [
            'Clarity and organization of the presentation',
            'Confidence and mastery in answering questions',
        ]],
        ['Problem and Objectives', 15, [
            'Relevance and significance of the problem',
            'Clarity and attainability of the objectives',
        ]],
        ['System Design and Methodology', 20, [
            'Appropriateness of the methodology and tools used',
            'Quality of the system architecture and database design',
        ]],
        ['System Functionality', 30, [
            'Completeness of the required features',
            'Accuracy and reliability of the system output',
            'Usability of the user interface',
        ]],
        ['Documentation', 20, [
            'Organization and completeness of the manuscript',
            'Proper citation and technical writing',
        ]],
    ];

    private const TITLES = [
        'AgriSense: IoT-Based Soil Moisture Monitoring System',
        'MediQueue: Clinic Appointment and Patient Queueing System',
        'BrgyConnect: Barangay Document Request and Issuance System',
        'LibraTrack: RFID-Based Library Attendance and Borrowing System',
        'EduPath: Career Track Recommendation System for Senior High Students',
        'FloodWatch: Real-Time Flood Level Alert System',
        'CampusPass: QR-Based Visitor Management System',
        'StockWise: Inventory Forecasting System for Small Retailers',
        'TutorLink: Peer Tutoring Matching Platform',
        'SafeRide: Tricycle Fare and Route Information App',
        'HarvestHub: Online Marketplace for Local Farmers',
        'DormEase: Boarding House Finder and Reservation System',
        'GradeLens: Student Performance Analytics Dashboard',
        'AquaTrack: Household Water Consumption Monitoring System',
        'FiestaGo: Events and Tourism Guide Mobile App',
        'PayEase: Cashless Canteen Payment System',
        'CareBridge: Barangay Health Worker Record System',
        'ParkSmart: Campus Parking Slot Availability System',
        'SignSpeak: Filipino Sign Language Recognition App',
        'ScholarTrack: Scholarship Application and Monitoring System',
        'FishNet: Catch Logging App for Coastal Fisherfolk',
        'ReliefLink: Disaster Relief Goods Distribution Tracker',
        'JobMatch: Alumni Job Posting and Matching Portal',
        'EcoSort: Smart Waste Segregation Monitoring System',
    ];

    private const FIRST_NAMES = [
        'Andrea', 'Bryan', 'Carla', 'Daniel', 'Erika', 'Francis', 'Grace', 'Harvey', 'Ivy', 'John Paul',
        'Kristine', 'Lorenzo', 'Mika', 'Nathaniel', 'Angelica', 'Patrick', 'Jasmine', 'Rafael', 'Samantha', 'Tristan',
        'Bea', 'Vincent', 'Nicole', 'Mark Anthony', 'Denise', 'Kevin', 'Joanna', 'Paolo', 'Trisha', 'Renz',
    ];

    private const LAST_NAMES = [
        'Agustin', 'Bernardo', 'Cruz', 'Domingo', 'Espiritu', 'Fernandez', 'Gonzales', 'Hernandez', 'Ignacio', 'Javier',
        'Lopez', 'Macaraeg', 'Natividad', 'Ocampo', 'Pascual', 'Quiambao', 'Rivera', 'Salazar', 'Tolentino', 'Umali',
        'Vergara', 'Yap', 'Zamora', 'Aguilar', 'Belmonte', 'Cabrera', 'Dizon', 'Evangelista', 'Flores', 'Galang',
    ];

    private const TRACKS = ['Web Development', 'Mobile Development', 'Internet of Things', 'Data Analytics'];

    private const ADVISERS = ['Maricel Torres', 'Ricardo Manalo', 'Antonio Reyes', 'Cristina Valdez'];

    private const SECTIONS = ['4A', '4B', '4C'];

    private int $studentCursor = 0;

    private int $titleCursor = 0;

    private User $admin;

    public function handle(): int
    {
        $date = Carbon::parse($this->option('date') ?: today())->startOfDay();
        $start = $this->option('start');
        $end = $this->option('end');

        $admin = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'ADMIN'))
            ->whereHas('administratorProfile', fn ($q) => $q->whereNotNull('college_id'))
            ->first();

        if (! $admin) {
            $this->error('No Administrator account with a College was found.');

            return self::FAILURE;
        }

        $this->admin = $admin;
        mt_srand(2026);

        try {
            DB::transaction(function () use ($date, $start, $end) {
                $this->purge();
                $panelists = $this->ensurePanelists();
                $formVersion = $this->ensureEvaluationForm();

                $this->line('Building '.self::DONE_CATEGORY.' (completed, with grades)...');
                $this->buildCompletedCategory($date->copy()->subDays(3), $formVersion, $panelists);

                $this->line('Building '.self::LIVE_CATEGORY.' (live on the recording day)...');
                $this->buildLiveCategory($date, $start, $end, $formVersion, $panelists);

                $this->line('Building '.self::OPEN_CATEGORY.' (registration open)...');
                $this->buildOpenCategory($date, $formVersion);
            });
        } catch (\Throwable $e) {
            Carbon::setTestNow();
            $this->error('Demo data was not created (everything rolled back): '.$e->getMessage());

            return self::FAILURE;
        } finally {
            Carbon::setTestNow();
        }

        PresentationCategory::whereIn('name', [self::OPEN_CATEGORY, self::LIVE_CATEGORY, self::DONE_CATEGORY])
            ->with('presentationDates.eventDateStatus', 'categoryStatus')
            ->get()
            ->each(function (PresentationCategory $category) {
                $category->presentationDates->each->refreshStatus();
                $category->refreshStatus();
            });

        $this->summary($date, $start, $end);

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    private function buildCompletedCategory(Carbon $day, EvaluationFormVersion $formVersion, array $panelists): void
    {
        $this->at($day->copy()->subDays(18)->setTime(9, 0));
        $category = $this->createCategory(self::DONE_CATEGORY, 'capstone1', 3, $formVersion);
        $category->update([
            'description' => 'Pre-oral defense of capstone projects for BSIT 4th year students.',
            'registration_opens_at' => $day->copy()->subDays(15)->setTime(8, 0),
            'registration_closes_at' => $day->copy()->subDays(5)->setTime(17, 0),
        ]);
        $this->addDaysAndRooms($category, [$day], '08:00', '17:00', ['Room 301'], 3);

        // Outcome and overall quality (1–5) for each group, in queue order.
        $plan = [
            ['PASS_NO_REVISION', 4.6],
            ['PASS_MINOR_REVISION', 4.1],
            ['PASS_NO_REVISION', 4.8],
            ['PASS_MAJOR_REVISION', 3.4],
            ['PASS_MINOR_REVISION', 4.0],
            ['RE_DEFENSE', 2.9],
            ['FAILED', 2.1],
        ];

        $this->registerGroups($category, count($plan), $day->copy()->subDays(14)->setTime(9, 30));

        $this->at($day->copy()->subDays(4)->setTime(10, 0));
        $this->generateQueue($category);

        $attempts = $this->attemptsInQueueOrder($category);
        $this->assignPanel($category, $attempts->pluck('id')->all(), [$panelists['elena'], $panelists['francisco'], $panelists['gloria']], $panelists['hernan'], $panelists['elena']);

        // Presentation day: start the room, seat the panel, run every group.
        $room = PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))->firstOrFail();

        $this->at($day->copy()->setTime(8, 0));
        $started = app(EventActivationService::class)->startRoom($room, $this->admin->id);
        $this->ensureOk($started, 'Start Room');
        $session = $started['session'];

        $this->at($day->copy()->setTime(8, 2));
        $connections = [];
        foreach ($session->roomTerminals()->orderBy('terminal_number')->get() as $index => $terminal) {
            $terminal->update(['device_identifier' => 'demo-tablet-'.$terminal->terminal_number]);
            $panelist = User::findOrFail([$panelists['elena'], $panelists['francisco'], $panelists['gloria']][$index]);
            $result = app(TerminalConnectionService::class)->connect($terminal, $panelist, 'QR', '192.168.1.20', 'Demo Tablet');
            $this->ensureOk($result, 'Connect panelist');
            $connections[$panelist->id] = $result['connection'];
        }

        $lead = $connections[$panelists['elena']];
        $control = app(PresentationControlService::class);
        $evaluations = app(EvaluationSubmissionService::class);
        $outcomes = PresentationOutcome::pluck('id', 'code');
        $formVersion->load('sections.childCriteria');

        $cursor = $day->copy()->setTime(8, 5);

        foreach ($plan as [$outcomeCode, $quality]) {
            $this->at($cursor);
            $this->ensureOk($control->callNext(RoomSession::findOrFail($session->id), $lead), 'Call Next');

            $this->at($cursor->copy()->addMinutes(2));
            $this->ensureOk($control->start(RoomSession::findOrFail($session->id), $lead), 'Start');

            $attempt = RoomSession::findOrFail($session->id)->currentAttempt;
            $students = $attempt->researchGroup->students;

            foreach (EvaluationSubmission::where('presentation_attempt_id', $attempt->id)->get() as $offset => $submission) {
                $this->at($cursor->copy()->addMinutes(19 + $offset));
                $bias = [0.2, 0, -0.2][$offset % 3];

                foreach ($formVersion->sections as $section) {
                    foreach ($section->childCriteria as $item) {
                        $score = (int) max(1, min(5, round($quality + $bias + mt_rand(-6, 6) / 10)));
                        $this->ensureOk($evaluations->saveScore(EvaluationSubmission::findOrFail($submission->id), $item, $score), 'Save score');
                    }
                }

                foreach ($students as $student) {
                    $raw = max(55, min(99, round($quality * 18 + mt_rand(0, 9) + $bias * 5)));
                    $this->ensureOk($evaluations->saveStudentScore($submission, $student, $raw), 'Save student score');
                }

                $evaluations->saveOutcome($submission, $outcomes[$outcomeCode]);
                $this->ensureOk($evaluations->submit($submission->fresh()), 'Submit evaluation');
            }

            $this->at($cursor->copy()->addMinutes(22));
            $this->ensureOk($control->complete(RoomSession::findOrFail($session->id), $lead), 'Complete');

            $cursor->addMinutes(25);
        }

        $this->at($cursor->copy()->addMinutes(5));
        $disconnected = ConnectionStatus::where('code', 'DISCONNECTED')->value('id');
        TerminalConnection::whereIn('id', collect($connections)->pluck('id'))->update([
            'disconnected_at' => now(),
            'connection_status_id' => $disconnected,
        ]);

        $ended = app(EventActivationService::class)->endRoom(RoomSession::findOrFail($session->id), $this->admin->id);
        $this->ensureOk($ended, 'End Room');
    }

    private function buildLiveCategory(Carbon $day, string $start, string $end, EvaluationFormVersion $formVersion, array $panelists): void
    {
        $this->at($day->copy()->subDays(20)->setTime(9, 0));
        $category = $this->createCategory(self::LIVE_CATEGORY, 'capstone2', 2, $formVersion);
        $category->update([
            'description' => 'Final oral defense of completed capstone projects for BSIT 4th year students.',
            'registration_opens_at' => $day->copy()->subDays(14)->setTime(8, 0),
            'registration_closes_at' => $day->copy()->subDays(2)->setTime(17, 0),
        ]);
        $this->addDaysAndRooms($category, [$day, $day->copy()->addDay()], $start, $end, ['Room 101', 'Room 102'], 2);

        $this->registerGroups($category, 12, $day->copy()->subDays(13)->setTime(10, 0));

        $this->at($day->copy()->subDays(1)->setTime(9, 0));
        $this->generateQueue($category);

        $byRoom = $this->attemptsInQueueOrder($category)->groupBy(fn ($attempt) => $attempt->attemptSchedule->presentationDateRoom->room_name);

        // Room 101 (used for the tablet shot) is fully assigned.
        $this->assignPanel($category, $byRoom['Room 101']->pluck('id')->all(), [$panelists['elena'], $panelists['francisco']], $panelists['gloria'], $panelists['elena']);

        // Room 102: only the first two groups have a panel, so the Assign Panel shot has groups left to assign.
        $this->assignPanel($category, $byRoom['Room 102']->take(2)->pluck('id')->all(), [$panelists['hernan'], $panelists['isabel']], $panelists['jonathan'], $panelists['hernan']);

        CategoryAnnouncement::create([
            'category_id' => $category->id,
            'title' => 'Final Defense Reminders',
            'message' => 'Be at your room 15 minutes before your expected time and bring three printed copies of your manuscript.',
            'starts_at' => $day->copy()->subDays(2)->setTime(8, 0),
            'ends_at' => $day->copy()->addDays(2)->setTime(23, 59),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    private function buildOpenCategory(Carbon $day, EvaluationFormVersion $formVersion): void
    {
        $this->at($this->notAfterNow($day->copy()->subDays(5)->setTime(9, 0)));
        $category = $this->createCategory(self::OPEN_CATEGORY, 'capstone1', 3, $formVersion);
        $category->update([
            'description' => 'Proposal defense for capstone project titles of BSIT 3rd year students.',
            'registration_opens_at' => $day->copy()->subDays(3)->setTime(8, 0),
            'registration_closes_at' => $day->copy()->addDays(7)->setTime(17, 0),
        ]);
        $this->addDaysAndRooms($category, [$day->copy()->addDays(10), $day->copy()->addDays(11)], '08:00', '17:00', ['Room 201', 'Room 202'], 3);

        $this->registerGroups($category, 5, $this->notAfterNow($day->copy()->subDays(2)->setTime(13, 0)), 5);

        CategoryAnnouncement::create([
            'category_id' => $category->id,
            'title' => 'Registration Now Open',
            'message' => 'Register your group before the deadline. Only the group leader needs to submit the registration.',
            'starts_at' => $day->copy()->subDays(3)->setTime(8, 0),
            'ends_at' => $day->copy()->addDays(7)->setTime(17, 0),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Building blocks
    // ------------------------------------------------------------------

    private function createCategory(string $name, string $subject, int $panelistCount, EvaluationFormVersion $formVersion): PresentationCategory
    {
        $category = PresentationCategory::create([
            'created_by' => $this->admin->id,
            'academic_year_id' => AcademicYear::where('is_active', true)->firstOrFail()->id,
            'semester_id' => Semester::where('is_active', true)->firstOrFail()->id,
            'college_id' => $this->admin->administratorProfile->college_id,
            'name' => $name,
            'subject_or_research_type' => $subject,
            'category_status_id' => CategoryStatus::where('code', 'DRAFT')->firstOrFail()->id,
            'presentation_mode_id' => PresentationMode::where('code', 'STANDARD')->firstOrFail()->id,
            'maximum_members' => 4,
            'panelist_count' => $panelistCount,
            'technical_adviser_required' => true,
            'research_track_required' => true,
            'public_queue_visible' => true,
        ]);

        $category->categoryScheduleSetting()->create(['duration_minutes' => 20, 'allow_extended_time' => true]);
        $category->categoryQueueSetting()->create([
            'queue_strategy_id' => QueueStrategy::where('code', 'FIFO')->firstOrFail()->id,
            'called_waiting_minutes' => 10,
            'allow_same_day_reinsertion' => true,
            'late_defer_enabled' => true,
            'unresolved_absent_end_of_day' => true,
        ]);
        $category->categoryPaymentSetting()->create(['payment_required' => false, 'allow_admin_referral' => true]);
        $category->categoryEvaluationForms()->create([
            'evaluation_form_version_id' => $formVersion->id,
            'effective_from' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        return $category;
    }

    /**
     * @param  Carbon[]  $days
     */
    private function addDaysAndRooms(PresentationCategory $category, array $days, string $start, string $end, array $roomNames, int $panelistCount): void
    {
        $planned = EventDateStatus::where('code', 'PLANNED')->firstOrFail()->id;
        $activeRoom = RoomUseStatus::where('code', 'ACTIVE')->firstOrFail()->id;

        foreach ($roomNames as $roomName) {
            CategoryRoom::create([
                'category_id' => $category->id,
                'room_name' => $roomName,
                'default_panelist_count' => $panelistCount,
                'is_active' => true,
                'added_by' => $this->admin->id,
            ]);
        }

        foreach ($days as $day) {
            $date = PresentationDate::create([
                'category_id' => $category->id,
                'presentation_date' => $day->toDateString(),
                'event_start_time' => $start,
                'event_end_time' => $end,
                'event_date_status_id' => $planned,
            ]);

            foreach ($roomNames as $roomName) {
                PresentationDateRoom::create([
                    'presentation_date_id' => $date->id,
                    'room_name' => $roomName,
                    'panelist_count' => $panelistCount,
                    'room_use_status_id' => $activeRoom,
                    'added_by' => $this->admin->id,
                    'added_at' => now(),
                ]);
            }
        }
    }

    private function registerGroups(PresentationCategory $category, int $count, Carbon $firstAt, int $minutesApart = 47): void
    {
        $service = app(ResearchGroupRegistrationService::class);

        for ($i = 0; $i < $count; $i++) {
            $this->at($firstAt->copy()->addMinutes($i * $minutesApart));
            $section = self::SECTIONS[$i % count(self::SECTIONS)];
            $memberCount = [3, 2, 3, 3, 2][$i % 5];

            $track = self::TRACKS[$i % count(self::TRACKS)];

            $members = [];
            for ($m = 0; $m < $memberCount; $m++) {
                $members[] = $this->nextStudent($section, $track);
            }

            $service->create($category, [
                'leader' => $this->nextStudent($section, $track),
                'members' => $members,
                'project_title' => self::TITLES[$this->titleCursor++ % count(self::TITLES)],
                'proposed_titles' => [],
                'technical_adviser_name' => self::ADVISERS[$i % count(self::ADVISERS)],
            ], false);
        }
    }

    private function generateQueue(PresentationCategory $category): void
    {
        $category->refresh();
        $category->refreshStatus();

        $result = app(QueueGenerationService::class)->autoGenerateIfEligible($category, $this->admin->id);

        if ($result['state'] !== 'generated') {
            throw new RuntimeException("Queue for {$category->name} was not generated: ".($result['reason'] ?? $result['state']));
        }
    }

    private function attemptsInQueueOrder(PresentationCategory $category)
    {
        return PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->with('attemptSchedule.queueEntry', 'attemptSchedule.presentationDateRoom')
            ->get()
            ->sortBy(fn ($attempt) => $attempt->attemptSchedule->queueEntry->queue_number)
            ->values();
    }

    private function assignPanel(PresentationCategory $category, array $attemptIds, array $assigned, int $backup, int $lead): void
    {
        $result = app(PanelAssignmentService::class)->assign($category, $attemptIds, $assigned, $backup, $lead, $this->admin->id);
        $this->ensureOk($result, "Assign panel ({$category->name})");
    }

    private function ensurePanelists(): array
    {
        $role = Role::where('code', 'PANELIST')->firstOrFail();
        $active = AccountStatus::where('code', 'ACTIVE')->firstOrFail();
        $ids = [];

        foreach (self::PANELISTS as $username => [$first, $last, $specialization]) {
            $user = User::where('username', $username)->with('profile')->first();

            if ($user && ($user->profile?->first_name !== $first || $user->profile?->last_name !== $last)) {
                throw new RuntimeException("The username \"{$username}\" already belongs to someone else — rename it before running this.");
            }

            $user ??= User::create([
                'username' => $username,
                'password' => self::PANELIST_PASSWORD,
                'account_status_id' => $active->id,
                'must_change_password' => false,
            ]);
            $user->update([
                'password' => self::PANELIST_PASSWORD,
                'account_status_id' => $active->id,
                'must_change_password' => false,
            ]);

            UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id], ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);
            UserProfile::updateOrCreate(['user_id' => $user->id], ['first_name' => $first, 'last_name' => $last]);
            PanelistProfile::updateOrCreate(['user_id' => $user->id], [
                'college_id' => $this->admin->administratorProfile?->college_id,
                'specialization' => $specialization,
                'temporary_password' => null,
                'registered_by' => $this->admin->id,
                'registered_at' => now(),
            ]);

            $ids[$username] = $user->id;
        }

        return $ids;
    }

    private function ensureEvaluationForm(): EvaluationFormVersion
    {
        $form = EvaluationForm::where('name', self::FORM_NAME)->first();
        $active = $form?->activeVersion();

        if ($active) {
            return $active;
        }

        $builder = app(EvaluationFormBuilderService::class);
        $form ??= $builder->createForm(['name' => self::FORM_NAME], $this->admin);
        $version = $form->evaluationFormVersions()->latest('version_number')->firstOrFail();

        $version->evaluationCriteria()->whereNotNull('parent_criterion_id')->delete();
        $version->evaluationCriteria()->delete();

        foreach (self::RUBRIC as [$sectionName, $weight, $items]) {
            $section = $builder->addSection($version, ['name' => $sectionName, 'weight' => $weight]);

            foreach ($items as $item) {
                $builder->addItem($section, ['name' => $item]);
            }
        }

        $builder->updateShowLetterhead($version, true);
        $builder->syncApplicableModes($version, [PresentationMode::where('code', 'STANDARD')->firstOrFail()->id]);
        $builder->syncOutcomes($version, PresentationOutcome::whereIn('code', ['PASS_NO_REVISION', 'PASS_MINOR_REVISION', 'PASS_MAJOR_REVISION', 'RE_DEFENSE', 'FAILED'])
            ->get()
            ->sortBy(fn ($o) => array_search($o->code, ['PASS_NO_REVISION', 'PASS_MINOR_REVISION', 'PASS_MAJOR_REVISION', 'RE_DEFENSE', 'FAILED'], true))
            ->pluck('id')
            ->all());

        return $builder->publish($version->fresh());
    }

    // ------------------------------------------------------------------
    // Reset
    // ------------------------------------------------------------------

    private function purge(): void
    {
        $categories = PresentationCategory::whereIn('name', [self::OPEN_CATEGORY, self::LIVE_CATEGORY, self::DONE_CATEGORY])->get();

        if ($categories->isEmpty()) {
            return;
        }

        $this->line("Removing {$categories->count()} existing demo categor".($categories->count() === 1 ? 'y' : 'ies').'...');

        $categoryIds = $categories->pluck('id');
        $groupIds = ResearchGroup::whereIn('category_id', $categoryIds)->pluck('id');
        $attemptIds = PresentationAttempt::whereIn('research_group_id', $groupIds)->pluck('id');
        $dateIds = PresentationDate::whereIn('category_id', $categoryIds)->pluck('id');
        $roomIds = PresentationDateRoom::whereIn('presentation_date_id', $dateIds)->pluck('id');
        $eventIds = PresentationEvent::whereIn('presentation_date_id', $dateIds)->pluck('id');
        $sessionIds = RoomSession::whereIn('presentation_event_id', $eventIds)->pluck('id');
        $terminalIds = RoomTerminal::whereIn('room_session_id', $sessionIds)->pluck('id');
        $scheduleIds = AttemptSchedule::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
        $entryIds = QueueEntry::whereIn('attempt_schedule_id', $scheduleIds)->pluck('id');
        $requestIds = PanelSubstitutionRequest::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');

        foreach ([
            PresentationCategory::class => $categoryIds,
            PresentationDate::class => $dateIds,
            PresentationAttempt::class => $attemptIds,
            QueueEntry::class => $entryIds,
            PanelSubstitutionRequest::class => $requestIds,
        ] as $type => $ids) {
            Notification::where('related_type', $type)->whereIn('related_id', $ids)->delete();
        }

        RoomSession::whereIn('id', $sessionIds)->update(['current_attempt_id' => null]);
        PresentationAttempt::whereIn('id', $attemptIds)->update(['previous_attempt_id' => null]);

        $runIds = PresentationRun::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
        PresentationAction::whereIn('presentation_run_id', $runIds)->delete();
        PresentationPause::whereIn('presentation_run_id', $runIds)->delete();
        PresentationRun::whereIn('id', $runIds)->delete();

        $submissionIds = EvaluationSubmission::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
        EvaluationScore::whereIn('evaluation_submission_id', $submissionIds)->delete();
        EvaluationSubmissionStudentScore::whereIn('evaluation_submission_id', $submissionIds)->delete();
        EvaluationSubmission::whereIn('id', $submissionIds)->delete();

        AttemptPanelParticipation::whereIn('presentation_attempt_id', $attemptIds)->delete();
        AttemptPanelAssignment::whereIn('presentation_attempt_id', $attemptIds)->delete();
        PanelSubstitutionRequest::whereIn('id', $requestIds)->delete();
        PaymentVerification::whereIn('presentation_attempt_id', $attemptIds)->delete();
        AttemptDecision::whereIn('presentation_attempt_id', $attemptIds)->delete();
        AttemptRequirement::whereIn('presentation_attempt_id', $attemptIds)->delete();
        AttemptRatingSummary::whereIn('presentation_attempt_id', $attemptIds)->delete();

        QueueAdjustment::whereIn('queue_entry_id', $entryIds)->delete();
        QueueEntry::whereIn('id', $entryIds)->delete();
        AttemptSchedule::whereIn('id', $scheduleIds)->delete();

        ProposedTitle::whereIn('research_group_id', $groupIds)->delete();
        PresentationAttempt::whereIn('id', $attemptIds)->delete();
        Student::whereIn('research_group_id', $groupIds)->delete();
        ResearchGroup::whereIn('id', $groupIds)->delete();

        TerminalConnection::whereIn('room_terminal_id', $terminalIds)->delete();
        TerminalAccessToken::whereIn('room_terminal_id', $terminalIds)->delete();
        RoomTerminal::whereIn('id', $terminalIds)->delete();
        RoomSession::whereIn('id', $sessionIds)->delete();
        PresentationEvent::whereIn('id', $eventIds)->delete();

        ScheduleBreak::whereIn('presentation_date_room_id', $roomIds)->delete();
        CapacityAnalysisSnapshot::whereIn('presentation_date_room_id', $roomIds)->delete();
        RoomSessionAccount::whereIn('presentation_category_id', $categoryIds)->delete();
        PresentationDateRoom::whereIn('id', $roomIds)->delete();
        EndOfDayProcessingLog::whereIn('presentation_date_id', $dateIds)->delete();
        PresentationDate::whereIn('id', $dateIds)->delete();

        CategoryAnnouncement::whereIn('category_id', $categoryIds)->delete();
        DB::table('category_evaluation_forms')->whereIn('category_id', $categoryIds)->delete();
        DB::table('category_queue_settings')->whereIn('category_id', $categoryIds)->delete();
        DB::table('category_schedule_settings')->whereIn('category_id', $categoryIds)->delete();
        DB::table('category_payment_settings')->whereIn('category_id', $categoryIds)->delete();
        DB::table('category_payment_types')->whereIn('category_id', $categoryIds)->delete();
        CategoryRoom::whereIn('category_id', $categoryIds)->delete();
        PresentationCategory::whereIn('id', $categoryIds)->delete();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function at(Carbon $moment): void
    {
        Carbon::setTestNow($moment);
    }

    private function notAfterNow(Carbon $moment): Carbon
    {
        Carbon::setTestNow();
        $real = now()->subMinutes(30);

        return $moment->gt($real) ? $real : $moment;
    }

    private function nextStudent(string $section, string $track): array
    {
        $i = $this->studentCursor++;
        $last = self::LAST_NAMES[($i * 7 + 3 + intdiv($i, count(self::FIRST_NAMES)) * 11) % count(self::LAST_NAMES)];

        return [
            'last_name' => $last,
            'first_name' => self::FIRST_NAMES[$i % count(self::FIRST_NAMES)],
            'middle_name' => self::LAST_NAMES[($i * 5 + 1) % count(self::LAST_NAMES)],
            'sex' => $i % 2 === 0 ? 'Female' : 'Male',
            'section' => $section,
            'research_track_name' => $track,
        ];
    }

    private function ensureOk(array $result, string $step): void
    {
        if (! ($result['ok'] ?? false)) {
            throw new RuntimeException("{$step} failed: ".($result['error'] ?? 'unknown error'));
        }
    }

    private function summary(Carbon $date, string $start, string $end): void
    {
        $this->newLine();
        $this->info('Demo data ready.');
        $this->newLine();

        $this->table(['Category', 'State', 'Use it for'], [
            [self::OPEN_CATEGORY, 'Registration open', 'Student registration (3A)'],
            [self::LIVE_CATEGORY, "Live on {$date->format('M j, Y')} {$start}–{$end}", 'Setup, panel assignment, Event Control, tablet (2B, 2E, 2F, 3B)'],
            [self::DONE_CATEGORY, 'Completed with grades', 'Reports, Evaluation sheets, Re-Defense tab (2G)'],
        ]);

        $rows = [];
        foreach (self::PANELISTS as $username => [$first, $last]) {
            $role = match ($username) {
                'elena' => 'Chair, Room 101',
                'francisco' => 'Member 1, Room 101',
                'gloria' => 'Alternate Panel, Room 101',
                'hernan' => 'Chair, Room 102 (first 2 groups)',
                'isabel' => 'Member 1, Room 102 (first 2 groups)',
                'jonathan' => 'Alternate Panel, Room 102 (first 2 groups)',
                default => 'Free, use in the Assign Panel shot',
            };
            $rows[] = ["{$first} {$last}", $username, self::PANELIST_PASSWORD, $role];
        }

        $this->table(['Panelist', 'Username', 'Password', 'In '.self::LIVE_CATEGORY], $rows);

        $liveEnd = $date->copy()->setTimeFromTimeString($end);
        if (now()->gt($liveEnd)) {
            $this->warn("The live day's end time ({$end}) has already passed — re-run with --end or a later --date, or Start Room will be refused.");
        }
    }
}
