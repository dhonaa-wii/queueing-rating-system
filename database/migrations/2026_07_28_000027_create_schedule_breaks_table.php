<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_date_room_id')->constrained('presentation_date_rooms');
            $table->string('name', 100);
            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_breaks');
    }
};
