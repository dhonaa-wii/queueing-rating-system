<?php

namespace Database\Seeders;

use App\Models\PresentationOutcome;
use Illuminate\Database\Seeder;

class PresentationOutcomeSeeder extends Seeder
{
    /**
     * Confirmed final set (user-directed 2026-09-13) — these five are the
     * only presentation outcomes for Standard mode, replacing the earlier
     * TENTATIVE placeholder list. Existing installs are migrated by
     * 2026_09_13_000001_confirm_final_presentation_outcomes.
     */
    public function run(): void
    {
        $outcomes = [
            ['code' => 'PASS_NO_REVISION', 'name' => 'Pass with No Revision', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => true, 'is_active' => true],
            ['code' => 'PASS_MINOR_REVISION', 'name' => 'Pass with Minor Revision', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => true, 'is_active' => true],
            ['code' => 'PASS_MAJOR_REVISION', 'name' => 'Pass with Major Revision', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => true, 'is_active' => true],
            ['code' => 'RE_DEFENSE', 'name' => 'Re-Defense', 'requires_new_attempt' => true, 'allows_project_title_change' => false, 'is_successful' => null, 'is_active' => true],
            ['code' => 'FAILED', 'name' => 'Failed', 'requires_new_attempt' => false, 'allows_project_title_change' => false, 'is_successful' => false, 'is_active' => true],
        ];

        foreach ($outcomes as $outcome) {
            PresentationOutcome::updateOrCreate(['code' => $outcome['code']], $outcome);
        }
    }
}
