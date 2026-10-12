<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMES = [
        'PASS_NO_REVISION' => ['Pass with No Revision', 'Passed with No Revision'],
        'PASS_MINOR_REVISION' => ['Pass with Minor Revision', 'Passed with Minor Revision'],
        'PASS_MAJOR_REVISION' => ['Pass with Major Revision', 'Passed with Major Revision'],
    ];

    /**
     * "Pass" → "Passed" (user-directed 2026-10-13). Renamed in place by code,
     * so every evaluation_submissions / attempt / form-version reference keeps
     * pointing at the same row — no score is touched. Also puts each form's
     * Remarks list back in the confirmed order (they had been saved in the
     * builder's alphabetical order, Failed first).
     */
    public function up(): void
    {
        foreach (self::NAMES as $code => [, $new]) {
            DB::table('presentation_outcomes')->where('code', $code)->update(['name' => $new]);
        }

        $this->resequence();
    }

    public function down(): void
    {
        foreach (self::NAMES as $code => [$old]) {
            DB::table('presentation_outcomes')->where('code', $code)->update(['name' => $old]);
        }
    }

    private function resequence(): void
    {
        $rank = DB::table('presentation_outcomes')->pluck('code', 'id')
            ->map(fn ($code) => array_search($code, \App\Models\PresentationOutcome::ORDER, true));

        $rows = DB::table('evaluation_form_version_outcomes')->get()->groupBy('evaluation_form_version_id');

        foreach ($rows as $versionRows) {
            $sorted = $versionRows->sortBy(fn ($row) => [
                $rank[$row->presentation_outcome_id] === false ? PHP_INT_MAX : $rank[$row->presentation_outcome_id],
                $row->sort_order,
            ])->values();

            foreach ($sorted as $index => $row) {
                DB::table('evaluation_form_version_outcomes')->where('id', $row->id)->update(['sort_order' => $index + 1]);
            }
        }
    }
};
