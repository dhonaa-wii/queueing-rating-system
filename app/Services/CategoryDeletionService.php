<?php

namespace App\Services;

use App\Models\AttemptDecision;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptPanelParticipation;
use App\Models\AttemptRatingSummary;
use App\Models\AttemptRequirement;
use App\Models\AttemptSchedule;
use App\Models\CapacityAnalysisSnapshot;
use App\Models\EndOfDayProcessingLog;
use App\Models\EvaluationSubmission;
use App\Models\PanelSubstitutionRequest;
use App\Models\PaymentVerification;
use App\Models\PresentationAction;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\PresentationEvent;
use App\Models\PresentationPause;
use App\Models\PresentationRun;
use App\Models\ProposedTitle;
use App\Models\QueueAdjustment;
use App\Models\QueueEntry;
use App\Models\ResearchGroup;
use App\Models\RoomSession;
use App\Models\RoomSessionAccount;
use App\Models\RoomTerminal;
use App\Models\ScheduleBreak;
use App\Models\Student;
use App\Models\TerminalAccessToken;
use App\Models\TerminalConnection;
use Illuminate\Support\Facades\DB;

/**
 * Full purge of a category once it's done for good — user-directed
 * 2026-08-17: "after ending a category, category should be able to
 * deleted." CategoryController::destroy() already exists but only ever
 * removes *setup* data and explicitly refuses (FK violation caught and
 * turned into "Archive it instead") the moment any research group has
 * registered — the normal, expected state for a category that has actually
 * run a live event. That refusal is intentionally left alone for the
 * general case; this is a separate, much more permissive path gated on the
 * category already being COMPLETED (see EventActivationService::
 * completeCategory()) — reaching COMPLETED already proves every group has
 * a final, terminal outcome, so nothing being deleted here can still be
 * "in progress."
 *
 * Walks every table QueueGenerationService::discardAll(),
 * PresentationAttemptAdminActionService::delete(), and
 * PresentationDate::deleteWithChildren() each cover a slice of, plus the
 * terminal/room-session infrastructure Event Activation and Room Session
 * Accounts write (RoomTerminal/TerminalConnection/TerminalAccessToken/
 * PresentationEvent/RoomSessionAccount) — none of those three existing
 * helpers reach that infrastructure, since none of them expected to ever
 * run on a category/date that had actually gone live.
 */
class CategoryDeletionService
{
    public function deleteCompletedCategory(PresentationCategory $category): array
    {
        $category->loadMissing('categoryStatus');

        // ARCHIVED counts too — it's the other category_statuses row with
        // is_terminal = true, and predates End Category (2026-08-17) as the
        // only way a category used to get "finished". Both mean the same
        // thing here: nothing about this category is still in progress.
        if (! in_array($category->categoryStatus?->code, ['COMPLETED', 'ARCHIVED'], true)) {
            return $this->failure('Only a category marked complete (End Category) or archived can be deleted.');
        }

        $attemptIds = PresentationAttempt::whereHas('researchGroup', fn ($query) => $query->where('category_id', $category->id))->pluck('id');

        if (EvaluationSubmission::whereIn('presentation_attempt_id', $attemptIds)->exists()) {
            return $this->failure('This category has real submitted evaluations recorded against it and cannot be deleted.');
        }

        DB::transaction(function () use ($category, $attemptIds) {
            $runIds = PresentationRun::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
            PresentationAction::whereIn('presentation_run_id', $runIds)->delete();
            PresentationPause::whereIn('presentation_run_id', $runIds)->delete();
            PresentationRun::whereIn('id', $runIds)->delete();

            AttemptPanelParticipation::whereIn('presentation_attempt_id', $attemptIds)->delete();
            AttemptPanelAssignment::whereIn('presentation_attempt_id', $attemptIds)->delete();
            PanelSubstitutionRequest::whereIn('presentation_attempt_id', $attemptIds)->delete();
            PaymentVerification::whereIn('presentation_attempt_id', $attemptIds)->delete();
            AttemptDecision::whereIn('presentation_attempt_id', $attemptIds)->delete();
            AttemptRequirement::whereIn('presentation_attempt_id', $attemptIds)->delete();
            AttemptRatingSummary::whereIn('presentation_attempt_id', $attemptIds)->delete();

            $scheduleIds = AttemptSchedule::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
            $queueEntryIds = QueueEntry::whereIn('attempt_schedule_id', $scheduleIds)->pluck('id');
            QueueAdjustment::whereIn('queue_entry_id', $queueEntryIds)->delete();
            QueueEntry::whereIn('id', $queueEntryIds)->delete();
            AttemptSchedule::whereIn('id', $scheduleIds)->delete();

            ProposedTitle::whereIn('presentation_attempt_id', $attemptIds)->update(['presentation_attempt_id' => null]);
            PresentationAttempt::whereIn('id', $attemptIds)->delete();

            $groupIds = ResearchGroup::where('category_id', $category->id)->pluck('id');
            ProposedTitle::whereIn('research_group_id', $groupIds)->delete();
            Student::whereIn('research_group_id', $groupIds)->delete();
            ResearchGroup::whereIn('id', $groupIds)->delete();

            $dateIds = PresentationDate::where('category_id', $category->id)->pluck('id');
            $roomIds = PresentationDateRoom::whereIn('presentation_date_id', $dateIds)->pluck('id');
            $eventIds = PresentationEvent::whereIn('presentation_date_id', $dateIds)->pluck('id');
            $sessionIds = RoomSession::whereIn('presentation_event_id', $eventIds)->pluck('id');
            $terminalIds = RoomTerminal::whereIn('room_session_id', $sessionIds)->pluck('id');

            TerminalConnection::whereIn('room_terminal_id', $terminalIds)->delete();
            TerminalAccessToken::whereIn('room_terminal_id', $terminalIds)->delete();
            RoomTerminal::whereIn('id', $terminalIds)->delete();
            RoomSession::whereIn('id', $sessionIds)->delete();
            PresentationEvent::whereIn('id', $eventIds)->delete();

            ScheduleBreak::whereIn('presentation_date_room_id', $roomIds)->delete();
            CapacityAnalysisSnapshot::whereIn('presentation_date_room_id', $roomIds)->delete();
            PresentationDateRoom::whereIn('id', $roomIds)->delete();
            EndOfDayProcessingLog::whereIn('presentation_date_id', $dateIds)->delete();
            PresentationDate::whereIn('id', $dateIds)->delete();

            RoomSessionAccount::where('presentation_category_id', $category->id)->delete();

            $category->categoryAnnouncements()->delete();
            $category->categoryEvaluationForms()->delete();
            $category->categoryQueueSetting()->delete();
            $category->categoryScheduleSetting()->delete();
            $category->categoryPaymentSetting()->delete();
            $category->categoryPaymentTypes()->delete();

            $category->delete();
        });

        return ['ok' => true];
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
