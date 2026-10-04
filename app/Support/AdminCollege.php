<?php

namespace App\Support;

use App\Models\AttemptSchedule;
use App\Models\CategoryAnnouncement;
use App\Models\CategoryResearchTrack;
use App\Models\CategoryRoom;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationForm;
use App\Models\EvaluationFormVersion;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\QueueEntry;
use App\Models\ResearchGroup;
use App\Models\RoomSession;
use App\Models\RoomSessionAccount;
use App\Models\RoomTerminal;
use App\Models\ScheduleBreak;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Data separation between colleges (user-directed 2026-10-03). An Admin works
 * only inside their own college (administrator_profiles.college_id): its
 * categories and everything under them, its evaluation forms, its panelists.
 * Super Admins are not limited.
 *
 * Lists filter with id(); EnforceAdminCollege 404s any record in an admin URL
 * whose ownerOf() is another college, so a typed or bookmarked link can't
 * reach across either.
 */
class AdminCollege
{
    /** The signed-in Admin's college, or null (no college assigned, or not an Admin). */
    public static function id(): ?int
    {
        return auth()->user()?->administratorProfile?->college_id;
    }

    /**
     * The college a record belongs to. Returns false for a record that isn't
     * college-owned (shared catalogs such as presentation outcomes), which
     * is never blocked.
     */
    public static function ownerOf(Model $model): int|null|false
    {
        return match (true) {
            $model instanceof PresentationCategory => $model->college_id,
            $model instanceof EvaluationForm => $model->college_id,
            $model instanceof User => $model->hasRole('PANELIST') ? $model->panelistProfile?->college_id : false,

            $model instanceof CategoryAnnouncement,
            $model instanceof PresentationDate,
            $model instanceof CategoryRoom,
            $model instanceof CategoryResearchTrack,
            $model instanceof RoomSessionAccount,
            $model instanceof ResearchGroup => $model->category?->college_id,

            $model instanceof EvaluationFormVersion => $model->evaluationForm?->college_id,
            $model instanceof EvaluationCriterion => $model->evaluationFormVersion?->evaluationForm?->college_id,
            $model instanceof PresentationDateRoom => $model->presentationDate?->category?->college_id,
            $model instanceof ScheduleBreak => $model->presentationDateRoom?->presentationDate?->category?->college_id,
            $model instanceof RoomSession => $model->presentationDateRoom?->presentationDate?->category?->college_id,
            $model instanceof RoomTerminal => $model->roomSession?->presentationDateRoom?->presentationDate?->category?->college_id,
            $model instanceof PresentationAttempt => $model->researchGroup?->category?->college_id,
            $model instanceof AttemptSchedule => $model->presentationAttempt?->researchGroup?->category?->college_id,
            $model instanceof QueueEntry => $model->attemptSchedule?->presentationAttempt?->researchGroup?->category?->college_id,
            $model instanceof PanelSubstitutionRequest => $model->presentationAttempt?->researchGroup?->category?->college_id,

            default => false,
        };
    }

    /**
     * Of these panelist ids, the ones not registered to $collegeId — a panel
     * is drawn only from the category's own college. Returns their display
     * names, for the refusal message.
     */
    public static function panelistsOutside(array $userIds, ?int $collegeId): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::whereIn('id', array_map('intval', $userIds))
            ->where(fn ($q) => $q
                ->whereDoesntHave('panelistProfile')
                ->orWhereHas('panelistProfile', fn ($p) => $p->where('college_id', '!=', $collegeId ?? 0)->orWhereNull('college_id')))
            ->with('profile')
            ->get()
            ->map(fn (User $user) => trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')) ?: $user->username)
            ->all();
    }

    /** Whether the signed-in Admin may see this record. */
    public static function owns(Model $model): bool
    {
        $owner = self::ownerOf($model);

        if ($owner === false) {
            return true;
        }

        $college = self::id();

        return $college !== null && (int) $owner === $college;
    }
}
