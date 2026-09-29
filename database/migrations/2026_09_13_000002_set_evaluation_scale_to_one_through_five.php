<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-directed 2026-09-13: the evaluation rating scale is 1-5, not the
     * 0-5 the sample printed sheet used for its lowest band (the sheet had
     * no value-1 row at all — 0 was "Very Insufficient" and 1 was unused).
     * Value 0 is retired: the legend's lowest band becomes 1, so every
     * rating column reads 1 2 3 4 5 with no gap.
     *
     * The value-0 legend row is moved to value 1 in place rather than
     * deleted and re-inserted, so a version's own customized wording
     * survives. Confirmed before writing this that no recorded
     * evaluation_scores row sits below the new floor (the lowest real score
     * anywhere is 4), so no already-submitted score is invalidated.
     *
     * Applied to every version including RETIRED ones: the recorded scores
     * stay valid either way, and leaving a retired version rendering a 0-5
     * legend in Reports would read as a bug rather than as history.
     */
    public function up(): void
    {
        Schema::table('evaluation_form_versions', function (Blueprint $table) {
            $table->unsignedTinyInteger('scale_min')->default(1)->change();
        });

        DB::table('evaluation_form_versions')->where('scale_min', 0)->update(['scale_min' => 1]);

        // Promote each version's value-0 band to value 1, unless that
        // version already has its own value-1 row (then 0 is redundant).
        $zeroRows = DB::table('evaluation_scale_labels')->where('value', 0)->get();

        foreach ($zeroRows as $row) {
            $hasOne = DB::table('evaluation_scale_labels')
                ->where('evaluation_form_version_id', $row->evaluation_form_version_id)
                ->where('value', 1)
                ->exists();

            if ($hasOne) {
                DB::table('evaluation_scale_labels')->where('id', $row->id)->delete();
            } else {
                DB::table('evaluation_scale_labels')->where('id', $row->id)->update(['value' => 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_form_versions', function (Blueprint $table) {
            $table->unsignedTinyInteger('scale_min')->default(0)->change();
        });

        DB::table('evaluation_form_versions')->where('scale_min', 1)->update(['scale_min' => 0]);
        DB::table('evaluation_scale_labels')->where('value', 1)->update(['value' => 0]);
    }
};
