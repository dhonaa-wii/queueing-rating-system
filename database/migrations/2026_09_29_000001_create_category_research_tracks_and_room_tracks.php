<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track rooms (user-directed 2026-09-29): a category keeps its own list of
 * research tracks, students pick theirs from it at registration, and each
 * registered room can be limited to some of those tracks. A room with tracks
 * only ever queues groups whose leader is on one of them.
 *
 * Each category's list is seeded from the track names its students already
 * typed, so existing registrations keep matching.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_research_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('presentation_categories')->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::create('category_room_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_room_id')->constrained('category_rooms')->cascadeOnDelete();
            $table->foreignId('category_research_track_id')->constrained('category_research_tracks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_room_id', 'category_research_track_id'], 'category_room_tracks_room_track_unique');
        });

        $existing = DB::table('students')
            ->join('research_groups', 'research_groups.id', '=', 'students.research_group_id')
            ->whereNotNull('students.research_track_name')
            ->where('students.research_track_name', '!=', '')
            ->select('research_groups.category_id', 'students.research_track_name')
            ->get()
            ->groupBy('category_id');

        foreach ($existing as $categoryId => $rows) {
            $names = $rows->map(fn ($row) => trim(preg_replace('/\s+/', ' ', $row->research_track_name)))
                ->filter()
                ->unique(fn ($name) => mb_strtolower($name));

            foreach ($names as $name) {
                DB::table('category_research_tracks')->insert([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('adjustment_reasons')->updateOrInsert(
            ['code' => 'MOVED_TO_EARLIER_DATE'],
            ['name' => 'Moved to an Earlier Date', 'is_active' => true]
        );
    }

    public function down(): void
    {
        DB::table('adjustment_reasons')->where('code', 'MOVED_TO_EARLIER_DATE')->delete();
        Schema::dropIfExists('category_room_tracks');
        Schema::dropIfExists('category_research_tracks');
    }
};
