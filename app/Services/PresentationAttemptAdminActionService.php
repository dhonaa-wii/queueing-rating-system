<?php

namespace App\Services;

use App\Models\AttemptDecision;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptPanelParticipation;
use App\Models\AttemptRatingSummary;
use App\Models\AttemptRequirement;
use App\Models\AttemptSchedule;
use App\Models\EvaluationScore;
use App\Models\EvaluationSubmission;
use App\Models\Notification;
use App\Models\PanelSubstitutionRequest;
use App\Models\PaymentVerification;
use App\Models\PresentationAction;
use App\Models\PresentationActionType;
use App\Models\PresentationAttempt;
use App\Models\PresentationPause;
use App\Models\PresentationRun;
use App\Models\PresentationStatus;
use App\Models\ProposedTitle;
use App\Models\QueueAdjustment;
use App\Models\RoomSession;
use App\Models\RoomSessionStatus;
use App\Models\TimerStatus;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side "Complete Presentation" / "Delete Presentation" overrides for
 * Live Monitoring, sitting next to the existing "Pause (Admin Override)"
 * capability there. PresentationControlService already covers the panelist/
 * Terminal-1 versions of Complete (functional-spec §5.5/§9.9) but that path
 * requires a real TerminalConnection — this is the equivalent action for an
 * Admin working the queue directly, for a group whose panel never went
 * through the terminal flow at all, or to correct a mistake afterward.
 *
 * delete() is deliberately NOT gated on presentation_status being terminal
 * (unlike QueueAdjustmentService's reorder/transfer/defer/reinsert, and
 * unlike QueueGenerationService::discardAll()) — it exists specifically to
 * let an Admin remove a single presentation record, completed or not,
 * something nothing else in the app can do once an attempt reaches a
 * terminal status.
 */
class PresentationAttemptAdminActionService
{
    public function __construct(
        private readonly QueueAdjustmentService $queueAdjustmentService,
        private readonly EvaluationSubmissionService $evaluationSubmissionService,
    ) {
    }

    public function complete(AttemptSchedule $schedule, int $performedByUserId, ?int $outcomeId = null): array
    {
        $schedule->loadMissing('presentationAttempt.presentationStatus', 'presentationAttempt.presentationRun', 'presentationDateRoom');

        $attempt = $schedule->presentationAttempt;

        if ($attempt->presentationStatus?->is_terminal) {
            return $this->failure('This presentation already has a recorded outcome.');
        }

        if (! $schedule->queueEntry || $schedule->queueEntry->removed_at !== null) {
            return $this->failure('This group is not currently in an active queue.');
        }

        $now = now();

        DB::transaction(function () use ($attempt, $outcomeId, $performedByUserId, $now) {
            $attempt->update([
                'presentation_status_id' => PresentationStatus::where('code', 'COMPLETED')->firstOrFail()->id,
                'completed_at' => $now,
                // An explicit pick from the Admin's own Mark Complete
                // dropdown wins; with none, fall back to the Lead/Chair's
                // submitted verdict so this path classifies an attempt the
                // same way PresentationControlService::complete() does
                // (user-directed 2026-09-13).
                'final_outcome_id' => $outcomeId ?? $this->evaluationSubmissionService->leadOutcomeFor($attempt),
            ]);

            RoomSession::where('current_attempt_id', $attempt->id)->update([
                'current_attempt_id' => null,
                'room_session_status_id' => RoomSessionStatus::where('code', 'WAITING')->firstOrFail()->id,
            ]);

            AttemptPanelParticipation::where('presentation_attempt_id', $attempt->id)
                ->whereNull('participation_ended_at')
                ->update(['participation_ended_at' => $now]);

            $run = $attempt->presentationRun;
            if ($run && ! $run->completed_at) {
                $actualDuration = $run->started_at ? max(0, $run->started_at->diffInSeconds($now) - $run->total_paused_seconds) : null;

                $run->update([
                    'completed_at' => $now,
                    'actual_duration_seconds' => $actualDuration,
                    'extended_seconds' => $actualDuration !== null ? max(0, $actualDuration - $run->configured_duration_seconds) : $run->extended_seconds,
                    'timer_status_id' => TimerStatus::where('code', 'COMPLETED')->firstOrFail()->id,
                    'last_action_at' => $now,
                ]);
            }

            // Same "only logged when a live run exists" posture as
            // EventActivationService's EMERGENCY_OVERRIDE pause
            // (presentation_actions.presentation_run_id is a required FK) —
            // an Admin completing a group that never went through
            // Presentation Control at all has no run to attach the log to.
            if ($run) {
                PresentationAction::create([
                    'presentation_run_id' => $run->id,
                    'action_type_id' => PresentationActionType::where('code', 'COMPLETE')->firstOrFail()->id,
                    'performed_by' => $performedByUserId,
                    'terminal_connection_id' => null,
                    'reason_id' => null,
                    'remarks' => 'Completed by Admin (Live Monitoring override).',
                    'performed_at' => $now,
                ]);
            }
        });

        return ['ok' => true];
    }

    public function delete(AttemptSchedule $schedule, int $performedByUserId): array
    {
        $schedule->loadMissing('presentationAttempt', 'presentationDateRoom', 'queueEntry');

        $attempt = $schedule->presentationAttempt;
        $room = $schedule->presentationDateRoom;

        if (PresentationAttempt::where('previous_attempt_id', $attempt->id)->exists()) {
            return $this->failure('This presentation has a follow-up attempt recorded against it and cannot be deleted.');
        }

        // Only a genuinely SUBMITTED/FINALIZED/INVALIDATED sheet counts as
        // "real submitted evaluations" — a DRAFT row is left behind whenever
        // a presentation was started (and possibly deferred, e.g. via the
        // payment-concern referral) but the panel never actually submitted;
        // defer only skips the group, it never records an evaluation, so an
        // abandoned draft must not block deleting a deferred-then-reinserted
        // (or re-defense) group. See EvaluationSubmissionService::
        // hasRealSubmission()'s own "already submitted" boundary.
        if ($this->evaluationSubmissionService->hasRealSubmission($attempt)) {
            return $this->failure('This presentation has real submitted evaluations recorded against it and cannot be deleted.');
        }

        DB::transaction(function () use ($attempt, $room, $schedule, $performedByUserId) {
            // A group the panel has called and not yet started is skipped, with
            // a notice left for the room's tablets (user-directed 2026-09-24).
            $this->queueAdjustmentService->releaseCalledAttempt($schedule, 'DELETED', $performedByUserId, resetStatus: false);

            RoomSession::where('current_attempt_id', $attempt->id)->update(['current_attempt_id' => null]);

            $run = PresentationRun::where('presentation_attempt_id', $attempt->id)->first();
            if ($run) {
                PresentationAction::where('presentation_run_id', $run->id)->delete();
                PresentationPause::where('presentation_run_id', $run->id)->delete();
                $run->delete();
            }

            // Abandoned DRAFT evaluations (see the guard above) — deleted
            // before AttemptPanelParticipation, which evaluation_submissions
            // itself has a non-cascading FK against.
            $submissionIds = EvaluationSubmission::where('presentation_attempt_id', $attempt->id)->pluck('id');
            EvaluationScore::whereIn('evaluation_submission_id', $submissionIds)->delete();
            EvaluationSubmission::whereIn('id', $submissionIds)->delete();

            AttemptPanelParticipation::where('presentation_attempt_id', $attempt->id)->delete();
            AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)->delete();
            // Pending/closed substitution requests (e.g. the "needs replacement"
            // report a deleted panelist leaves) hold a hard FK onto the attempt.
            $substitutionRequestIds = PanelSubstitutionRequest::where('presentation_attempt_id', $attempt->id)->pluck('id');
            Notification::where('related_type', PanelSubstitutionRequest::class)->whereIn('related_id', $substitutionRequestIds)->delete();
            PanelSubstitutionRequest::whereIn('id', $substitutionRequestIds)->delete();
            ProposedTitle::where('presentation_attempt_id', $attempt->id)->update(['presentation_attempt_id' => null]);
            PaymentVerification::where('presentation_attempt_id', $attempt->id)->delete();
            AttemptDecision::where('presentation_attempt_id', $attempt->id)->delete();
            AttemptRequirement::where('presentation_attempt_id', $attempt->id)->delete();
            AttemptRatingSummary::where('presentation_attempt_id', $attempt->id)->delete();

            if ($entry = $schedule->queueEntry) {
                QueueAdjustment::where('queue_entry_id', $entry->id)->delete();
                $entry->delete();
            }

            $schedule->delete();
            $attempt->delete();

            $this->queueAdjustmentService->renumberRoom($room);
        });

        return ['ok' => true];
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
