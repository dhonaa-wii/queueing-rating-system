<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * payment_verifications used to be one row per attempt (a category only
 * ever had a single payment_required flag). User-directed 2026-09-20: a
 * category can now configure several named payment types, each verified
 * independently, so this becomes one row per (attempt, payment type)
 * instead — dropping the old unique(presentation_attempt_id) for
 * unique(presentation_attempt_id, category_payment_type_id).
 *
 * Every category already marked payment_required gets one default "Payment"
 * type so any already-recorded verification row keeps a home once the
 * column below becomes required — same posture as the READY→STANDBY /
 * APPROVED→PASS_NO_REVISION in-place renames elsewhere in this app: real
 * rows are preserved, not discarded, even though this is still early/dev
 * data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = DB::table('category_payment_settings')->where('payment_required', true)->get();

        $typeIdByCategory = [];

        foreach ($settings as $setting) {
            $typeIdByCategory[$setting->category_id] = DB::table('category_payment_types')->insertGetId([
                'category_id' => $setting->category_id,
                'name' => 'Payment',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // MySQL error 1553: the unique index on presentation_attempt_id is
        // also the one backing its own FK constraint, so it can't be dropped
        // directly — add a plain index to keep the FK covered first, same
        // fix as the 2026-09-07 day-scoped room_session_accounts migration.
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->index('presentation_attempt_id');
        });

        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->dropUnique(['presentation_attempt_id']);
            $table->foreignId('category_payment_type_id')->nullable()->after('presentation_attempt_id')
                ->constrained('category_payment_types')->nullOnDelete();
        });

        foreach ($typeIdByCategory as $categoryId => $typeId) {
            DB::table('payment_verifications')
                ->whereIn('presentation_attempt_id', function ($query) use ($categoryId) {
                    $query->select('presentation_attempts.id')
                        ->from('presentation_attempts')
                        ->join('research_groups', 'research_groups.id', '=', 'presentation_attempts.research_group_id')
                        ->where('research_groups.category_id', $categoryId);
                })
                ->update(['category_payment_type_id' => $typeId]);
        }

        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->unique(['presentation_attempt_id', 'category_payment_type_id'], 'payment_verifications_attempt_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->dropUnique('payment_verifications_attempt_type_unique');
            $table->dropConstrainedForeignId('category_payment_type_id');
            $table->dropIndex(['presentation_attempt_id']);
            $table->unique('presentation_attempt_id');
        });
    }
};
