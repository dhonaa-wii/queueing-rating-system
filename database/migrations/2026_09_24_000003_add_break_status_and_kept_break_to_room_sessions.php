<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('room_session_statuses')->updateOrInsert(['code' => 'BREAK'], ['name' => 'Break']);

        Schema::table('room_sessions', function (Blueprint $table) {
            $table->foreignId('kept_break_id')->nullable()->after('current_attempt_id')
                ->constrained('schedule_breaks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kept_break_id');
        });

        DB::table('room_sessions')
            ->whereIn('room_session_status_id', DB::table('room_session_statuses')->where('code', 'BREAK')->pluck('id'))
            ->update(['room_session_status_id' => DB::table('room_session_statuses')->where('code', 'WAITING')->value('id')]);

        DB::table('room_session_statuses')->where('code', 'BREAK')->delete();
    }
};
