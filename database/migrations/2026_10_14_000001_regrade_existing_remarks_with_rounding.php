<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regrades every existing Standard-mode remark under the rounded rubric
 * (user-directed 2026-10-14: "round off should be applied to all records,
 * including the existing re-defense groups"). Same rule as
 * PresentationOutcome::codeForGrade(): the 2-dp grade is rounded to a whole
 * percent, then 91 / 81 / 71 / 61 bands, below 61 Failed.
 *
 * - Each SUBMITTED/FINALIZED sheet's remark follows its own weighted total.
 * - Each COMPLETED attempt's outcome follows its Group Grade (average of those
 *   sheets), except an attempt that already has a follow-up attempt: the
 *   re-defense it created would no longer match, so it is left as it is (the
 *   same refusal adminEdit() makes).
 *
 * Title Proposal forms are skipped (their remark is still picked). Scores are
 * not touched. Not reversible: the old hand-picked remarks are not kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $outcomeIds = DB::table('presentation_outcomes')->pluck('id', 'code');
        $bands = [91 => 'PASS_NO_REVISION', 81 => 'PASS_MINOR_REVISION', 71 => 'PASS_MAJOR_REVISION', 61 => 'RE_DEFENSE'];

        $idFor = function (float $grade) use ($bands, $outcomeIds) {
            $grade = round(round($grade, 2));

            foreach ($bands as $floor => $code) {
                if ($grade >= $floor) {
                    return $outcomeIds[$code] ?? null;
                }
            }

            return $outcomeIds['FAILED'] ?? null;
        };

        $titleProposalVersions = DB::table('evaluation_form_version_presentation_modes as m')
            ->join('presentation_modes as pm', 'pm.id', '=', 'm.presentation_mode_id')
            ->where('pm.code', 'TITLE_PROPOSAL')
            ->pluck('m.evaluation_form_version_id');

        $sheets = DB::table('evaluation_submissions as s')
            ->join('submission_statuses as st', 'st.id', '=', 's.submission_status_id')
            ->whereIn('st.code', ['SUBMITTED', 'FINALIZED'])
            ->whereNotNull('s.weighted_total_score')
            ->whereNotIn('s.evaluation_form_version_id', $titleProposalVersions)
            ->get(['s.id', 's.presentation_attempt_id', 's.weighted_total_score', 's.presentation_outcome_id']);

        foreach ($sheets as $sheet) {
            $new = $idFor((float) $sheet->weighted_total_score);

            if ($new !== null && (int) $new !== (int) $sheet->presentation_outcome_id) {
                DB::table('evaluation_submissions')->where('id', $sheet->id)->update(['presentation_outcome_id' => $new]);
            }
        }

        $completedId = DB::table('presentation_statuses')->where('code', 'COMPLETED')->value('id');
        $withFollowUp = DB::table('presentation_attempts')->whereNotNull('previous_attempt_id')->pluck('previous_attempt_id')->flip();

        foreach ($sheets->groupBy('presentation_attempt_id') as $attemptId => $attemptSheets) {
            $attempt = DB::table('presentation_attempts')->where('id', $attemptId)->first(['id', 'presentation_status_id', 'final_outcome_id']);

            if (! $attempt || (int) $attempt->presentation_status_id !== (int) $completedId || $withFollowUp->has($attemptId)) {
                continue;
            }

            $new = $idFor(round($attemptSheets->avg('weighted_total_score'), 2));

            if ($new !== null && (int) $new !== (int) $attempt->final_outcome_id) {
                DB::table('presentation_attempts')->where('id', $attemptId)->update(['final_outcome_id' => $new]);
            }
        }
    }

    public function down(): void
    {
        // Data regrade; the previous remarks are not kept.
    }
};
