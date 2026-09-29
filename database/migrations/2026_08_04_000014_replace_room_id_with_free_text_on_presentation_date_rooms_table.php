<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_date_rooms', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
        });

        // The composite unique index also backs the presentation_date_id foreign key —
        // give that FK a plain index to stand on before the composite one is dropped.
        Schema::table('presentation_date_rooms', function (Blueprint $table) {
            $table->index('presentation_date_id');
        });

        Schema::table('presentation_date_rooms', function (Blueprint $table) {
            $table->dropUnique(['presentation_date_id', 'room_id']);
        });

        Schema::table('presentation_date_rooms', function (Blueprint $table) {
            $table->dropColumn('room_id');
            $table->string('room_name', 100)->after('presentation_date_id');
            $table->unsignedSmallInteger('panelist_count')->nullable()->after('room_name');
        });
    }

    public function down(): void
    {
        Schema::table('presentation_date_rooms', function (Blueprint $table) {
            $table->dropColumn(['room_name', 'panelist_count']);
            $table->foreignId('room_id')->after('presentation_date_id')->constrained('rooms');
            $table->unique(['presentation_date_id', 'room_id']);
        });
    }
};
