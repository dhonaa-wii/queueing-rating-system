<?php

use App\Http\Controllers\Admin\CategoryAnnouncementController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EvaluationCriterionController;
use App\Http\Controllers\Admin\EvaluationFormController;
use App\Http\Controllers\Admin\EvaluationFormVersionController;
use App\Http\Controllers\Admin\EvaluationLetterheadController;
use App\Http\Controllers\Admin\EvaluationOutcomeController;
use App\Http\Controllers\Admin\LiveMonitoringController;
use App\Http\Controllers\Admin\PanelAssignmentController;
use App\Http\Controllers\Admin\PanelistController;
use App\Http\Controllers\Admin\PanelSubstitutionController;
use App\Http\Controllers\Admin\CategoryRoomController;
use App\Http\Controllers\Admin\CategoryTrackController;
use App\Http\Controllers\Admin\PresentationDateController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Panelist\AssignmentController as PanelistAssignmentController;
use App\Http\Controllers\Panelist\NotificationController as PanelistNotificationController;
use App\Http\Controllers\Panelist\DashboardController as PanelistDashboardController;
use App\Http\Controllers\Panelist\ScheduleController as PanelistScheduleController;
use App\Http\Controllers\Panelist\TerminalScanController;
use App\Http\Controllers\LetterheadLogoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomSessionController;
use App\Http\Controllers\Student\CategoryController as StudentCategoryController;
use App\Http\Controllers\Student\RegistrationController as StudentRegistrationController;
use App\Http\Controllers\SuperAdmin\AdminAccountController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\BackupController;
use App\Http\Controllers\SuperAdmin\MaintenanceController;
use App\Http\Controllers\SuperAdmin\RestoreController;
use App\Http\Controllers\SuperAdmin\ApplicationSettingController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PanelistOversightController;
use App\Http\Controllers\SuperAdmin\SecuritySettingController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

// Help Center / User Guide — public on purpose: students have no accounts, and
// someone locked out of their own needs it most. One page for every role.
Route::view('/help', 'help.index')->name('help');

// The pre-built PDF of the same page (php artisan help:export-pdf), sent as a
// download rather than opened in the browser's print dialog.
Route::get('/help/download', function () {
    $path = public_path(\App\Console\Commands\ExportHelpPdf::OUTPUT);
    abort_unless(is_file($path), 404);

    return response()->download($path, 'ARPQRS User Guide.pdf', ['Content-Type' => 'application/pdf']);
})->name('help.pdf');

// Letterhead logos, served by the app so they don't depend on the
// public/storage symlink (missing on shared hosting).
Route::get('/letterhead/{letterhead}/logo/{slot}', [LetterheadLogoController::class, 'show'])
    ->whereIn('slot', ['primary', 'secondary'])
    ->name('letterhead.logo');

// Public device-claim flow for the physical tablet in a room (user-directed
// 2026-08-15) — a room_session_accounts row is a shared credential the
// Administrator uses once per tablet to bind it to a terminal number, not a
// per-panelist login. See App\Http\Controllers\RoomSessionController. The
// per-panelist identification layer (QR scan) is instead under
// Panelist\TerminalScanController, gated by that role's normal auth.
Route::prefix('/room-session')->name('room-session.')->group(function () {
    Route::get('/', [RoomSessionController::class, 'login'])->name('entry');

    // Standalone diagnostic for the physical tablets — reports the browser and
    // which CSS features it actually supports. No session or account needed,
    // so it still answers on a device that can't get past anything else.
    Route::view('/device-check', 'room-session.device-check')->name('device-check');
    Route::post('/login', [RoomSessionController::class, 'authenticate'])->name('login');
    Route::get('/terminal', [RoomSessionController::class, 'terminalPicker'])->name('terminal');
    Route::post('/terminal', [RoomSessionController::class, 'claimTerminal'])->name('terminal.claim');
    Route::post('/cancel', [RoomSessionController::class, 'cancel'])->name('cancel');
    Route::get('/home', [RoomSessionController::class, 'home'])->name('home');
    Route::get('/home/status', [RoomSessionController::class, 'status'])->name('status');
    Route::post('/home/manual-login', [RoomSessionController::class, 'manualLogin'])->name('manual-login');
    Route::post('/home/logout', [RoomSessionController::class, 'logoutPanelist'])->name('logout');
    Route::post('/home/substitution/request', [RoomSessionController::class, 'requestSubstitution'])->name('substitution.request');
    Route::post('/release', [RoomSessionController::class, 'release'])->name('release');

    // Presentation Control (functional-spec §5.5/§9.9) — Terminal 1/Lead
    // only, enforced per-request in RoomSessionController::requireLeadConnection().
    Route::post('/home/call-next', [RoomSessionController::class, 'callNext'])->name('call-next');
    Route::post('/home/verify-payment', [RoomSessionController::class, 'verifyPayment'])->name('verify-payment');
    Route::post('/home/start', [RoomSessionController::class, 'startPresentation'])->name('start');
    Route::post('/home/pause', [RoomSessionController::class, 'pausePresentation'])->name('pause');
    Route::post('/home/resume', [RoomSessionController::class, 'resumePresentation'])->name('resume');
    Route::post('/home/complete', [RoomSessionController::class, 'completePresentation'])->name('complete');
    Route::post('/home/defer', [RoomSessionController::class, 'deferPresentation'])->name('defer');
    Route::post('/home/break/cancel', [RoomSessionController::class, 'cancelBreak'])->name('break.cancel');
    Route::post('/home/break/keep', [RoomSessionController::class, 'keepBreak'])->name('break.keep');
    Route::post('/home/break/end', [RoomSessionController::class, 'endBreak'])->name('break.end');

    // Live evaluation — any connected, panel-eligible terminal (Lead
    // included), not just Terminal 1. Each panelist only ever touches their
    // own submission, enforced in RoomSessionController::requireEvaluationSubmission().
    Route::post('/home/evaluation/score', [RoomSessionController::class, 'saveEvaluationScore'])->name('evaluation.score');
    Route::post('/home/evaluation/student-score', [RoomSessionController::class, 'saveEvaluationStudentScore'])->name('evaluation.student-score');
    Route::post('/home/evaluation/outcome', [RoomSessionController::class, 'saveEvaluationOutcome'])->name('evaluation.outcome');
    Route::post('/home/evaluation/remarks', [RoomSessionController::class, 'saveEvaluationRemarks'])->name('evaluation.remarks');
    Route::post('/home/evaluation/submit', [RoomSessionController::class, 'submitEvaluation'])->name('evaluation.submit');
});

// Student / Research Group side has no accounts (functional-spec §4, §4.9) —
// public, unauthenticated routes, outside the auth middleware group below.
Route::prefix('/categories')->name('student.categories.')->group(function () {
    Route::get('/', [StudentCategoryController::class, 'index'])->name('index');
    Route::get('/{category}/schedule', [StudentCategoryController::class, 'schedule'])->name('schedule');

    Route::prefix('/{category}/register')->name('registration.')->group(function () {
        Route::get('/', [StudentRegistrationController::class, 'create'])->name('create');
        Route::post('/', [StudentRegistrationController::class, 'store'])->name('store');
        Route::get('/confirmation/{groupReference}', [StudentRegistrationController::class, 'confirmation'])->name('confirmation');
    });
});

// Outside every auth group on purpose: a restore rewrites the users table, so
// this authenticates with the run's own token. Also exempt from CSRF (see
// bootstrap/app.php) and the one route the maintenance gate always lets through.
Route::post('/restore/{restore}/step', [RestoreController::class, 'step'])->name('super-admin.restores.step');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');

    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
        ->name('admin.dashboard')->middleware('role:ADMIN');

    // Global (not category-nested) — the Dashboard's Needs Attention card and
    // Group & Panel Assignment's needs-attention list both post here. The
    // index (JSON) and notifications.read routes went out with the topbar
    // notification bell (2026-09-22), which was their only caller.
    Route::prefix('/admin/panel-substitutions')->name('admin.panel-substitutions.')->middleware('role:ADMIN')->group(function () {
        Route::post('/{substitutionRequest}/approve', [PanelSubstitutionController::class, 'approve'])->name('approve');
        Route::post('/{substitutionRequest}/reject', [PanelSubstitutionController::class, 'reject'])->name('reject');
        Route::post('/{substitutionRequest}/assign-replacement', [PanelSubstitutionController::class, 'assignReplacement'])->name('assign-replacement');
    });

    Route::prefix('/admin/panelists')->name('admin.panelists.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [PanelistController::class, 'index'])->name('index');
        Route::post('/', [PanelistController::class, 'store'])->name('store');
        Route::get('/{panelist}', [PanelistController::class, 'show'])->name('show');
        Route::put('/{panelist}', [PanelistController::class, 'update'])->name('update');
        Route::post('/{panelist}/activate', [PanelistController::class, 'activate'])->name('activate');
        Route::post('/{panelist}/deactivate', [PanelistController::class, 'deactivate'])->name('deactivate');
        Route::post('/{panelist}/reset-password', [PanelistController::class, 'resetPassword'])->name('reset-password');
        Route::delete('/{panelist}', [PanelistController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/admin/categories')->name('admin.categories.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::post('/{category}/archive', [CategoryController::class, 'archive'])->name('archive');
        Route::post('/{category}/unarchive', [CategoryController::class, 'unarchive'])->name('unarchive');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');

        Route::put('/{category}/project-info-config', [CategoryController::class, 'updateProjectInfoConfig'])->name('project-info-config.update');
        Route::put('/{category}/panelist-count-config', [CategoryController::class, 'updatePanelistCountConfig'])->name('panelist-count-config.update');
        Route::put('/{category}/schedule-config', [CategoryController::class, 'updateScheduleConfig'])->name('schedule-config.update');
        Route::put('/{category}/queue-config', [CategoryController::class, 'updateQueueConfig'])->name('queue-config.update');
        Route::put('/{category}/payment-config', [CategoryController::class, 'updatePaymentConfig'])->name('payment-config.update');
        Route::put('/{category}/evaluation-config', [CategoryController::class, 'updateEvaluationConfig'])->name('evaluation-config.update');

        Route::post('/{category}/dates/span', [PresentationDateController::class, 'storeSpan'])->name('dates.span.store');
        Route::post('/{category}/dates/exclude-weekends', [PresentationDateController::class, 'excludeWeekends'])->name('dates.weekends.exclude');
        Route::put('/{category}/dates/{date}', [PresentationDateController::class, 'update'])->name('dates.update');
        Route::delete('/{category}/dates/{date}', [PresentationDateController::class, 'destroy'])->name('dates.destroy');
        Route::delete('/{category}/dates/{date}/rooms/{room}', [PresentationDateController::class, 'destroyRoom'])->name('dates.rooms.destroy');
        Route::post('/{category}/dates/{date}/breaks', [PresentationDateController::class, 'storeBreak'])->name('dates.breaks.store');
        Route::delete('/{category}/dates/{date}/rooms/{room}/breaks/{break}', [PresentationDateController::class, 'destroyBreak'])->name('dates.rooms.breaks.destroy');

        Route::post('/{category}/tracks', [CategoryTrackController::class, 'store'])->name('tracks.store');
        Route::put('/{category}/tracks/{track}', [CategoryTrackController::class, 'update'])->name('tracks.update');
        Route::delete('/{category}/tracks/{track}', [CategoryTrackController::class, 'destroy'])->name('tracks.destroy');
        Route::put('/{category}/tracks/{track}/evaluation-form', [CategoryController::class, 'updateTrackEvaluationConfig'])->name('tracks.evaluation-form.update');

        Route::post('/{category}/rooms', [CategoryRoomController::class, 'store'])->name('rooms.store');
        Route::put('/{category}/rooms/{room}', [CategoryRoomController::class, 'update'])->name('rooms.update');
        Route::delete('/{category}/rooms/{room}', [CategoryRoomController::class, 'destroy'])->name('rooms.destroy');
        Route::post('/{category}/rooms/assign-to-dates', [CategoryRoomController::class, 'assignToDates'])->name('rooms.assign-to-dates');

        Route::post('/{category}/announcements', [CategoryAnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/{category}/announcements/{announcement}', [CategoryAnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/{category}/announcements/{announcement}', [CategoryAnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    Route::prefix('/admin/panel-assignments')->name('admin.panel-assignments.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [PanelAssignmentController::class, 'index'])->name('index');
        Route::get('/{category}', [PanelAssignmentController::class, 'show'])->name('show');
        Route::post('/{category}/groups', [PanelAssignmentController::class, 'storeGroup'])->name('groups.store');
        Route::put('/{category}/groups/{group}', [PanelAssignmentController::class, 'updateGroup'])->name('groups.update');
        Route::post('/{category}/assign', [PanelAssignmentController::class, 'assign'])->name('assign');
        Route::post('/{category}/schedules/reorder', [PanelAssignmentController::class, 'reorder'])->name('reorder');
        Route::post('/{category}/schedules/transfer', [PanelAssignmentController::class, 'transfer'])->name('transfer');
        Route::delete('/{category}/schedules', [PanelAssignmentController::class, 'destroySchedules'])->name('schedules.destroy');
        Route::post('/{category}/queue-entries/defer', [PanelAssignmentController::class, 'defer'])->name('defer');
        Route::post('/{category}/queue-entries/{entry}/reinsert', [PanelAssignmentController::class, 'reinsert'])->name('reinsert');
        Route::post('/{category}/attempts/{attempt}/verify-payment', [PanelAssignmentController::class, 'verifyPayment'])->name('verify-payment');
        Route::post('/{category}/attempts/{attempt}/replace-panelists', [PanelAssignmentController::class, 'replacePanelists'])->name('replace-panelists');
        Route::post('/{category}/attempts/{attempt}/re-defense', [PanelAssignmentController::class, 'scheduleReDefense'])->name('re-defense');
    });

    Route::prefix('/admin/event-control')->name('admin.live-monitoring.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [LiveMonitoringController::class, 'index'])->name('index');
        Route::get('/{category}', [LiveMonitoringController::class, 'show'])->name('show');
        Route::post('/{category}/rooms/{room}/start', [LiveMonitoringController::class, 'startRoom'])->name('rooms.start');
        Route::post('/{category}/room-sessions/{roomSession}/end', [LiveMonitoringController::class, 'endRoom'])->name('room-sessions.end');
        Route::post('/{category}/room-sessions/{roomSession}/pause', [LiveMonitoringController::class, 'pauseRoomSession'])->name('room-sessions.pause');
        Route::post('/{category}/room-sessions/{roomSession}/resume', [LiveMonitoringController::class, 'resumeRoomSession'])->name('room-sessions.resume');
        Route::post('/{category}/room-accounts/{account}/reset', [LiveMonitoringController::class, 'resetRoomAccountCredentials'])->name('room-accounts.reset');
        Route::post('/{category}/terminals/{terminal}/disconnect', [LiveMonitoringController::class, 'forceDisconnectTerminal'])->name('terminals.disconnect');
        Route::post('/{category}/terminals/{terminal}/release-device', [LiveMonitoringController::class, 'releaseTerminalDevice'])->name('terminals.release-device');
        Route::post('/{category}/schedules/{schedule}/complete', [LiveMonitoringController::class, 'completeAttempt'])->name('schedules.complete');
        Route::delete('/{category}/schedules/{schedule}/delete', [LiveMonitoringController::class, 'deleteAttempt'])->name('schedules.delete');
        Route::post('/{category}/complete', [LiveMonitoringController::class, 'completeCategory'])->name('complete');
        Route::delete('/{category}', [LiveMonitoringController::class, 'deleteCategory'])->name('destroy');
    });

    Route::prefix('/admin/evaluation-library')->name('admin.evaluation-library.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [EvaluationFormController::class, 'index'])->name('index');
        Route::post('/', [EvaluationFormController::class, 'store'])->name('store');
        Route::get('/letterhead', [EvaluationLetterheadController::class, 'edit'])->name('letterhead.edit');
        Route::put('/letterhead', [EvaluationLetterheadController::class, 'update'])->name('letterhead.update');

        Route::post('/outcomes', [EvaluationOutcomeController::class, 'store'])->name('outcomes.store');
        Route::put('/outcomes/{outcome}', [EvaluationOutcomeController::class, 'update'])->name('outcomes.update');
        Route::post('/outcomes/{outcome}/toggle-active', [EvaluationOutcomeController::class, 'toggleActive'])->name('outcomes.toggle-active');

        Route::get('/{evaluationForm}', [EvaluationFormController::class, 'show'])->name('show');
        Route::put('/{evaluationForm}', [EvaluationFormController::class, 'update'])->name('update');
        Route::post('/{evaluationForm}/archive', [EvaluationFormController::class, 'archive'])->name('archive');
        Route::delete('/{evaluationForm}', [EvaluationFormController::class, 'destroy'])->name('destroy');
        Route::post('/{evaluationForm}/versions/{version}/publish', [EvaluationFormVersionController::class, 'publish'])->name('versions.publish');
        Route::put('/{evaluationForm}/versions/{version}/modes', [EvaluationFormVersionController::class, 'syncModes'])->name('versions.modes.sync');
        Route::put('/{evaluationForm}/versions/{version}/letterhead', [EvaluationFormVersionController::class, 'updateShowLetterhead'])->name('versions.letterhead.update');
        Route::put('/{evaluationForm}/versions/{version}/outcomes', [EvaluationOutcomeController::class, 'syncForVersion'])->name('versions.outcomes.sync');

        Route::post('/{evaluationForm}/versions/{version}/sections', [EvaluationCriterionController::class, 'storeSection'])->name('sections.store');
        Route::put('/{evaluationForm}/versions/{version}/sections/{section}', [EvaluationCriterionController::class, 'updateSection'])->name('sections.update');
        Route::delete('/{evaluationForm}/versions/{version}/sections/{section}', [EvaluationCriterionController::class, 'destroySection'])->name('sections.destroy');
        Route::post('/{evaluationForm}/versions/{version}/sections/{section}/move', [EvaluationCriterionController::class, 'moveSection'])->name('sections.move');

        Route::post('/{evaluationForm}/versions/{version}/sections/{section}/items', [EvaluationCriterionController::class, 'storeItem'])->name('items.store');
        Route::put('/{evaluationForm}/versions/{version}/items/{item}', [EvaluationCriterionController::class, 'updateItem'])->name('items.update');
        Route::delete('/{evaluationForm}/versions/{version}/items/{item}', [EvaluationCriterionController::class, 'destroyItem'])->name('items.destroy');
        Route::post('/{evaluationForm}/versions/{version}/items/{item}/move', [EvaluationCriterionController::class, 'moveItem'])->name('items.move');
    });

    // Analytics sits beside Reports rather than inside it: it is cross-category
    // by default, so it has no category to bind to (user-directed 2026-09-17).
    Route::get('/admin/analytics', [AnalyticsController::class, 'index'])
        ->name('admin.analytics.index')->middleware('role:ADMIN');

    Route::prefix('/admin/reports')->name('admin.reports.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/{category}/grades', [ReportController::class, 'grades'])->name('grades');
        Route::get('/{category}/grades/export', [ReportController::class, 'exportGrades'])->name('grades.export');
        Route::get('/{category}/grades/export-pdf', [ReportController::class, 'exportGradesPdf'])->name('grades.export-pdf');
        Route::get('/{category}/top/{type}/export-pdf', [ReportController::class, 'exportTopPdf'])->name('top.export-pdf');
        Route::get('/{category}/recommendations', [ReportController::class, 'recommendations'])->name('recommendations');
        Route::get('/{category}/recommendations/export-pdf', [ReportController::class, 'exportRecommendationsPdf'])->name('recommendations.export-pdf');
        Route::get('/{category}/panelist-sheet', [ReportController::class, 'panelistSheet'])->name('panelist-sheet');
        Route::get('/{category}/panelist-sheet/export-pdf', [ReportController::class, 'exportPanelistSheetPdf'])->name('panelist-sheet.export-pdf');
        Route::get('/{category}/attempts/{attempt}/evaluation-sheet', [ReportController::class, 'evaluationSheet'])->name('evaluation-sheet');
        Route::get('/{category}/attempts/{attempt}/evaluation-sheet/export-pdf', [ReportController::class, 'exportEvaluationSheetPdf'])->name('evaluation-sheet.export-pdf');
    });

    Route::get('/panelist/dashboard', [PanelistDashboardController::class, 'index'])
        ->name('panelist.dashboard')->middleware('role:PANELIST');

    Route::prefix('/panelist/assignments')->name('panelist.assignments.')->middleware('role:PANELIST')->group(function () {
        Route::get('/', [PanelistAssignmentController::class, 'index'])->name('index');
        Route::post('/{assignment}/unavailable', [PanelistAssignmentController::class, 'requestUnavailability'])->name('unavailable');
        Route::get('/{assignment}/evaluation-sheet', [PanelistAssignmentController::class, 'evaluationSheet'])->name('evaluation-sheet');
        Route::get('/{assignment}/evaluation-sheet/export-pdf', [PanelistAssignmentController::class, 'exportEvaluationSheetPdf'])->name('evaluation-sheet.export-pdf');
    });

    Route::prefix('/panelist/notifications')->name('panelist.notifications.')->middleware('role:PANELIST')->group(function () {
        Route::get('/', [PanelistNotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/read', [PanelistNotificationController::class, 'markRead'])->name('read');
    });

    Route::prefix('/panelist/schedule')->name('panelist.schedule.')->middleware('role:PANELIST')->group(function () {
        Route::get('/', [PanelistScheduleController::class, 'index'])->name('index');
        Route::get('/{category}', [PanelistScheduleController::class, 'show'])->name('show');
    });

    Route::prefix('/panelist/scan-terminal')->name('panelist.terminal-scan.')->middleware('role:PANELIST')->group(function () {
        Route::get('/{token}', [TerminalScanController::class, 'show'])->name('show');
        Route::post('/{token}', [TerminalScanController::class, 'claim'])->name('claim');
    });

    Route::get('/super-admin/dashboard', [SuperAdminDashboardController::class, 'index'])
        ->name('super-admin.dashboard')->middleware('role:SUPER_ADMIN');

    Route::prefix('/super-admin/administrators')->name('super-admin.administrators.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [AdminAccountController::class, 'index'])->name('index');
        Route::get('/create', [AdminAccountController::class, 'create'])->name('create');
        Route::post('/', [AdminAccountController::class, 'store'])->name('store');
        Route::get('/{admin}/edit', [AdminAccountController::class, 'edit'])->name('edit');
        Route::put('/{admin}', [AdminAccountController::class, 'update'])->name('update');
        Route::post('/{admin}/activate', [AdminAccountController::class, 'activate'])->name('activate');
        Route::post('/{admin}/deactivate', [AdminAccountController::class, 'deactivate'])->name('deactivate');
        Route::post('/{admin}/reset-password', [AdminAccountController::class, 'resetPassword'])->name('reset-password');
        Route::delete('/{admin}', [AdminAccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/super-admin/panelists')->name('super-admin.panelists.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [PanelistOversightController::class, 'index'])->name('index');
        Route::get('/{panelist}', [PanelistOversightController::class, 'show'])->name('show');
    });

    Route::prefix('/super-admin/backups')->name('super-admin.backups.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'store'])->name('store');
        Route::post('/upload', [BackupController::class, 'upload'])->name('upload');
        Route::put('/settings', [BackupController::class, 'updateSettings'])->name('settings');
        Route::post('/{backup}/step', [BackupController::class, 'step'])->name('step');
        Route::post('/{backup}/restore', [RestoreController::class, 'store'])->name('restore');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->name('download');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/super-admin/maintenance')->name('super-admin.maintenance.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::put('/settings', [MaintenanceController::class, 'updateSettings'])->name('settings');
        Route::post('/run', [MaintenanceController::class, 'run'])->name('run');
        Route::post('/mode', [MaintenanceController::class, 'setMode'])->name('mode');
    });

    Route::prefix('/super-admin/audit-logs')->name('super-admin.audit-logs.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/export', [AuditLogController::class, 'export'])->name('export');
    });

    Route::prefix('/super-admin/settings/security')->name('super-admin.settings.security.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [SecuritySettingController::class, 'index'])->name('index');
        Route::post('/', [SecuritySettingController::class, 'store'])->name('store');
        Route::put('/{setting}', [SecuritySettingController::class, 'update'])->name('update');
        Route::delete('/{setting}', [SecuritySettingController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/super-admin/settings/application')->name('super-admin.settings.application.')->middleware('role:SUPER_ADMIN')
        ->group(function () {
            $typePattern = 'campuses|colleges|academic-years|semesters';

            Route::get('/', [ApplicationSettingController::class, 'index'])->name('index');
            Route::post('/{type}', [ApplicationSettingController::class, 'store'])->name('store')->where('type', $typePattern);
            Route::put('/{type}/{id}', [ApplicationSettingController::class, 'update'])->name('update')->where('type', $typePattern);
            Route::delete('/{type}/{id}', [ApplicationSettingController::class, 'destroy'])->name('destroy')->where('type', $typePattern);
            Route::post('/{type}/{id}/toggle-active', [ApplicationSettingController::class, 'toggleActive'])->name('toggle-active')->where('type', $typePattern);
            Route::post('/{type}/{id}/set-active', [ApplicationSettingController::class, 'setActive'])->name('set-active')->where('type', $typePattern);
        });
});
