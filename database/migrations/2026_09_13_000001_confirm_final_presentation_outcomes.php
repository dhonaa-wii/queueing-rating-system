<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-09-13: the presentation outcome list is confirmed
     * final for Standard mode — Pass with No Revision, Pass with Minor
     * Revision, Pass with Major Revision, Re-Defense, Failed. This retires
     * the TENTATIVE placeholder set PresentationOutcomeSeeder shipped with
     * (see database-schema.md's old "terminology remains configurable until
     * stakeholder confirmation" rule).
     *
     * The four surviving rows are renamed in place rather than deleted and
     * re-created, so the evaluation_form_version_outcomes /
     * evaluation_submissions rows already pointing at them stay intact —
     * same approach as the READY -> STANDBY event_date_statuses rename.
     * The four dropped rows (FOR_TITLE_REVISION, FOR_PROJECT_REVISION,
     * FOR_PROJECT_CHANGE, WITHDRAWN) were confirmed unreferenced by any
     * form version, submission, attempt decision, or attempt final outcome
     * before writing this.
     */
    public function up(): void
    {
        $renames = [
            'APPROVED' => ['code' => 'PASS_NO_REVISION', 'name' => 'Pass with No Revision', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => true, 'is_active' => true],
            'APPROVED_MINOR_REVISIONS' => ['code' => 'PASS_MINOR_REVISION', 'name' => 'Pass with Minor Revision', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => true, 'is_active' => true],
            'APPROVED_MAJOR_REVISIONS' => ['code' => 'PASS_MAJOR_REVISION', 'name' => 'Pass with Major Revision', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => true, 'is_active' => true],
            'FOR_RE_DEFENSE' => ['code' => 'RE_DEFENSE', 'name' => 'Re-Defense', 'requires_new_attempt' => true, 'allows_project_title_change' => false, 'is_successful' => null, 'is_active' => true],
        ];

        foreach ($renames as $oldCode => $attributes) {
            DB::table('presentation_outcomes')->where('code', $oldCode)->update($attributes);
        }

        DB::table('presentation_outcomes')
            ->where('code', 'FAILED')
            ->update(['name' => 'Failed', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => false, 'is_active' => true]);

        DB::table('presentation_outcomes')
            ->whereNotIn('code', ['PASS_NO_REVISION', 'PASS_MINOR_REVISION', 'PASS_MAJOR_REVISION', 'RE_DEFENSE', 'FAILED'])
            ->delete();
    }

    public function down(): void
    {
        $reverts = [
            'PASS_NO_REVISION' => ['code' => 'APPROVED', 'name' => 'Approved'],
            'PASS_MINOR_REVISION' => ['code' => 'APPROVED_MINOR_REVISIONS', 'name' => 'Approved with Minor Revisions'],
            'PASS_MAJOR_REVISION' => ['code' => 'APPROVED_MAJOR_REVISIONS', 'name' => 'Approved with Major Revisions'],
            'RE_DEFENSE' => ['code' => 'FOR_RE_DEFENSE', 'name' => 'For Re-Defense'],
        ];

        foreach ($reverts as $newCode => $attributes) {
            DB::table('presentation_outcomes')->where('code', $newCode)->update($attributes);
        }

        // The deleted placeholder rows are re-inserted by re-running
        // PresentationOutcomeSeeder if they are ever needed again.
    }
};
