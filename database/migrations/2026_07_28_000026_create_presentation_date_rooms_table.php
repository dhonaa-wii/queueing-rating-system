<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_date_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_date_id')->constrained('presentation_dates');
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('room_use_status_id')->constrained('room_use_statuses');
            $table->time('room_start_time')->nullable();
            $table->time('room_end_time')->nullable();
            $table->foreignId('added_by')->constrained('users');
            $table->dateTime('added_at');
            $table->foreignId('closure_requested_by')->nullable()->constrained('users');
            $table->dateTime('closure_requested_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->dateTime('closed_at')->nullable();
            $table->text('removal_reason')->nullable();
            $table->timestamps();

            $table->unique(['presentation_date_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_date_rooms');
    }
};
