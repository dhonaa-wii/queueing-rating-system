<?php

use App\Models\CategoryAnnouncement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_announcements', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('message');
            $table->unique(['category_id', 'source'], 'category_announcements_category_source_unique');
        });

        // Categories that already have payment instructions get them posted
        // as their announcement, attributed to the category's own creator.
        $rows = DB::table('category_payment_settings as s')
            ->join('presentation_categories as c', 'c.id', '=', 's.category_id')
            ->where('s.payment_required', true)
            ->whereNotNull('s.verification_instructions')
            ->where('s.verification_instructions', '!=', '')
            ->select('s.category_id', 's.verification_instructions', 'c.created_by')
            ->get();

        foreach ($rows as $row) {
            CategoryAnnouncement::create([
                'category_id' => $row->category_id,
                'title' => CategoryAnnouncement::PAYMENT_INSTRUCTIONS_TITLE,
                'message' => $row->verification_instructions,
                'source' => CategoryAnnouncement::SOURCE_PAYMENT,
                'is_active' => true,
                'created_by' => $row->created_by,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('category_announcements')->where('source', 'PAYMENT_INSTRUCTIONS')->delete();

        Schema::table('category_announcements', function (Blueprint $table) {
            $table->dropUnique('category_announcements_category_source_unique');
            $table->dropColumn('source');
        });
    }
};
