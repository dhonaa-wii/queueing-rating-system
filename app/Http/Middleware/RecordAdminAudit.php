<?php

namespace App\Http\Middleware;

use App\Models\AttemptSchedule;
use App\Models\AuditLog;
use App\Models\EvaluationForm;
use App\Models\EvaluationLetterhead;
use App\Models\PresentationCategory;
use App\Models\PresentationOutcome;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\QueueEntry;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes an audit_logs row for every successful Admin save (user-directed
 * 2026-09-30: "in audit log include admin actions"). Until now only Super
 * Admin flows and backups were audited; rather than add a call to each of
 * the ~80 Admin write actions, this records them all from one place.
 *
 * - Only a signed-in ADMIN, only POST/PUT/PATCH/DELETE on an admin.* route.
 * - Only when the action succeeded: no validation errors and no flashed
 *   "error" in this request, and a non-error status for JSON (autosave)
 *   requests. A refused action isn't something that happened.
 * - The record it touched is captured *before* the action runs, so a delete
 *   still names what was deleted.
 * - Passwords, tokens and uploaded files are never stored.
 */
class RecordAdminAudit
{
    /** Route name (without "admin.") => action code shown in the Audit Log. */
    private const ACTIONS = [
        'categories.store' => 'CATEGORY_CREATED',
        'categories.update' => 'CATEGORY_UPDATED',
        'categories.destroy' => 'CATEGORY_DELETED',
        'categories.archive' => 'CATEGORY_ARCHIVED',
        'categories.unarchive' => 'CATEGORY_UNARCHIVED',
        'categories.announcements.store' => 'ANNOUNCEMENT_CREATED',
        'categories.announcements.update' => 'ANNOUNCEMENT_UPDATED',
        'categories.announcements.destroy' => 'ANNOUNCEMENT_DELETED',
        'categories.dates.span.store' => 'PRESENTATION_DATES_ADDED',
        'categories.dates.weekends.exclude' => 'PRESENTATION_DATE_DELETED',
        'categories.dates.update' => 'PRESENTATION_DATE_UPDATED',
        'categories.dates.destroy' => 'PRESENTATION_DATE_DELETED',
        'categories.dates.breaks.store' => 'BREAK_ADDED',
        'categories.dates.rooms.destroy' => 'DATE_ROOM_REMOVED',
        'categories.dates.rooms.breaks.destroy' => 'BREAK_REMOVED',
        'categories.evaluation-config.update' => 'EVALUATION_FORM_ASSIGNED',
        'categories.panelist-count-config.update' => 'PANEL_COUNT_UPDATED',
        'categories.payment-config.update' => 'PAYMENT_CONFIG_UPDATED',
        'categories.project-info-config.update' => 'REQUIREMENTS_UPDATED',
        'categories.queue-config.update' => 'QUEUE_CONFIG_UPDATED',
        'categories.schedule-config.update' => 'SCHEDULE_CONFIG_UPDATED',
        'categories.rooms.store' => 'ROOM_REGISTERED',
        'categories.rooms.update' => 'ROOM_UPDATED',
        'categories.rooms.destroy' => 'ROOM_DELETED',
        'categories.rooms.assign-to-dates' => 'ROOMS_ASSIGNED_TO_DATES',
        'categories.tracks.store' => 'TRACK_ADDED',
        'categories.tracks.update' => 'TRACK_RENAMED',
        'categories.tracks.destroy' => 'TRACK_REMOVED',

        'evaluation-library.store' => 'EVALUATION_FORM_CREATED',
        'evaluation-library.update' => 'EVALUATION_FORM_RENAMED',
        'evaluation-library.destroy' => 'EVALUATION_FORM_DELETED',
        'evaluation-library.archive' => 'EVALUATION_FORM_ARCHIVED',
        'evaluation-library.letterhead.update' => 'LETTERHEAD_UPDATED',
        'evaluation-library.outcomes.store' => 'OUTCOME_CREATED',
        'evaluation-library.outcomes.update' => 'OUTCOME_UPDATED',
        'evaluation-library.outcomes.toggle-active' => 'OUTCOME_TOGGLED',
        'evaluation-library.sections.store' => 'EVALUATION_SECTION_ADDED',
        'evaluation-library.sections.update' => 'EVALUATION_SECTION_UPDATED',
        'evaluation-library.sections.destroy' => 'EVALUATION_SECTION_DELETED',
        'evaluation-library.sections.move' => 'EVALUATION_SECTION_MOVED',
        'evaluation-library.items.store' => 'EVALUATION_CRITERION_ADDED',
        'evaluation-library.items.update' => 'EVALUATION_CRITERION_UPDATED',
        'evaluation-library.items.destroy' => 'EVALUATION_CRITERION_DELETED',
        'evaluation-library.items.move' => 'EVALUATION_CRITERION_MOVED',
        'evaluation-library.versions.letterhead.update' => 'EVALUATION_FORM_LETTERHEAD_TOGGLED',
        'evaluation-library.versions.modes.sync' => 'EVALUATION_FORM_MODE_SET',
        'evaluation-library.versions.outcomes.sync' => 'EVALUATION_FORM_OUTCOMES_SET',
        'evaluation-library.versions.publish' => 'EVALUATION_FORM_PUBLISHED',

        'live-monitoring.destroy' => 'CATEGORY_DELETED',
        'live-monitoring.complete' => 'CATEGORY_ENDED',
        'live-monitoring.room-accounts.reset' => 'ROOM_ACCOUNT_PASSWORD_RESET',
        'live-monitoring.rooms.start' => 'ROOM_STARTED',
        'live-monitoring.room-sessions.end' => 'ROOM_ENDED',
        'live-monitoring.room-sessions.pause' => 'ROOM_PAUSED',
        'live-monitoring.room-sessions.resume' => 'ROOM_RESUMED',
        'live-monitoring.schedules.complete' => 'PRESENTATION_COMPLETED_BY_ADMIN',
        'live-monitoring.schedules.delete' => 'PRESENTATION_DELETED',
        'live-monitoring.terminals.disconnect' => 'TERMINAL_DISCONNECTED',
        'live-monitoring.terminals.release-device' => 'TERMINAL_DEVICE_RELEASED',

        'panel-assignments.assign' => 'PANEL_ASSIGNED',
        'panel-assignments.replace-panelists' => 'PANELIST_REPLACED',
        'panel-assignments.re-defense' => 'RE_DEFENSE_SCHEDULED',
        'panel-assignments.verify-payment' => 'PAYMENT_VERIFIED',
        'panel-assignments.groups.store' => 'GROUP_ADDED',
        'panel-assignments.groups.update' => 'GROUP_UPDATED',
        'panel-assignments.defer' => 'GROUP_DEFERRED',
        'panel-assignments.reinsert' => 'GROUP_REINSERTED',
        'panel-assignments.schedules.destroy' => 'GROUP_DELETED',
        'panel-assignments.reorder' => 'GROUP_MOVED',
        'panel-assignments.transfer' => 'GROUP_TRANSFERRED',
        'panel-substitutions.approve' => 'SUBSTITUTION_APPROVED',
        'panel-substitutions.reject' => 'SUBSTITUTION_REJECTED',
        'panel-substitutions.assign-replacement' => 'REPLACEMENT_ASSIGNED',

        'panelists.store' => 'PANELIST_REGISTERED',
        'panelists.update' => 'PANELIST_UPDATED',
        'panelists.destroy' => 'PANELIST_DELETED',
        'panelists.activate' => 'PANELIST_ACTIVATED',
        'panelists.deactivate' => 'PANELIST_DEACTIVATED',
        'panelists.reset-password' => 'PANELIST_PASSWORD_RESET',
    ];

    /**
     * Record type for the actions whose URL names no record (a create, or a
     * singleton). audit_logs.entity_type is required, so without this every
     * create was refused by the database and its audit row lost.
     */
    private const ENTITY_TYPES = [
        'categories.store' => PresentationCategory::class,
        'evaluation-library.store' => EvaluationForm::class,
        'evaluation-library.outcomes.store' => PresentationOutcome::class,
        'evaluation-library.letterhead.update' => EvaluationLetterhead::class,
        'panelists.store' => User::class,
    ];

    /** Never written to the log, at any depth. */
    private const SECRET_KEYS = ['_token', '_method', 'password', 'password_confirmation', 'current_password', 'temporary_password', 'archive_password', 'token'];

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->shouldRecord($request) ? $this->before($request) : null;

        $response = $next($request);

        if ($context !== null && $this->succeeded($request, $response)) {
            try {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => $context['action'],
                    'entity_type' => $context['entity_type'],
                    'entity_id' => $context['entity_id'],
                    'new_values' => $this->payload($request, $context),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                ]);
            } catch (Throwable $e) {
                // An audit failure must never undo or hide the action itself.
                report($e);
            }
        }

        return $response;
    }

    private function shouldRecord(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        $name = $request->route()?->getName();

        return $name !== null
            && str_starts_with($name, 'admin.')
            && (bool) $request->user()?->hasRole('ADMIN');
    }

    private function before(Request $request): array
    {
        $name = Str::after($request->route()->getName(), 'admin.');
        $models = collect($request->route()->parameters())->filter(fn ($value) => $value instanceof Model);

        // The most specific record is the last one in the URL (a group inside
        // a category, a date inside a category); the category is context.
        $entity = $models->last();

        return [
            'action' => self::ACTIONS[$name] ?? Str::upper(Str::snake(str_replace(['.', '-'], '_', $name))),
            'route' => $name,
            // Any other route without a record still needs a type; its own
            // name is the most honest one.
            'entity_type' => $entity ? $entity::class : (self::ENTITY_TYPES[$name] ?? Str::limit('admin.' . $name, 100, '')),
            'entity_id' => $entity?->getKey(),
            'labels' => $models->map(fn (Model $model) => $this->labelFor($model))->filter()->values()->all(),
            'groups' => $this->bulkGroups($request),
        ];
    }

    /**
     * Bulk roster actions send ids in the body rather than a record in the
     * URL — resolve them to group references now, before a delete removes
     * the rows they point at.
     */
    private function bulkGroups(Request $request): array
    {
        $scheduleIds = array_filter((array) $request->input('schedule_ids', []), 'is_numeric');
        $entryIds = array_filter((array) $request->input('entry_ids', []), 'is_numeric');
        $attemptIds = array_filter((array) $request->input('attempt_ids', []), 'is_numeric');

        if (! $scheduleIds && ! $entryIds && ! $attemptIds) {
            return [];
        }

        return AttemptSchedule::query()
            ->where(fn ($q) => $q
                ->whereIn('id', $scheduleIds ?: [0])
                ->orWhereIn('presentation_attempt_id', $attemptIds ?: [0])
                ->orWhereHas('queueEntry', fn ($e) => $e->whereIn('id', $entryIds ?: [0])))
            ->with('presentationAttempt.researchGroup')
            ->get()
            ->map(fn (AttemptSchedule $s) => $s->presentationAttempt?->researchGroup?->group_reference)
            ->filter()->unique()->values()->all();
    }

    private function succeeded(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        // Only what was flashed during this request, not a leftover.
        $flashed = (array) $request->session()->get('_flash.new', []);

        return ! in_array('error', $flashed, true) && ! in_array('errors', $flashed, true);
    }

    private function payload(Request $request, array $context): array
    {
        $input = $this->scrub($request->except(array_keys($request->allFiles())));

        return array_filter([
            'records' => $context['labels'] ?: null,
            'groups' => $context['groups'] ?: null,
            'input' => $input ?: null,
        ]);
    }

    private function scrub(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($value)
                ? $this->scrub($value)
                : (is_string($value) ? Str::limit($value, 500) : $value);
        }

        return $clean;
    }

    /** A human name for the audit row, so it reads without looking ids up. */
    private function labelFor(Model $model): ?string
    {
        $type = Str::headline(class_basename($model));

        // Rows about a presentation read by the group they belong to.
        $group = match (true) {
            $model instanceof PresentationAttempt => $model->researchGroup?->group_reference,
            $model instanceof AttemptSchedule, $model instanceof PanelSubstitutionRequest => $model->presentationAttempt?->researchGroup?->group_reference,
            $model instanceof QueueEntry => $model->attemptSchedule?->presentationAttempt?->researchGroup?->group_reference,
            default => null,
        };

        $name = $group
            ?? $model->group_reference
            ?? $model->name
            ?? $model->room_name
            ?? $model->username
            ?? $model->title
            ?? $model->setting_key
            ?? null;

        if ($name === null && $model->getAttribute('presentation_date')) {
            $name = optional($model->presentation_date)->format('M j, Y');
        }

        return $type . ' #' . $model->getKey() . ($name ? ' — ' . $name : '');
    }
}
