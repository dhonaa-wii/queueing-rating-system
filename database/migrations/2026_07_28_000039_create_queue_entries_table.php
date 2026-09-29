<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_schedule_id')->unique()->constrained('attempt_schedules');
            $table->integer('queue_number')->unsigned();
            $table->integer('priority_value')->nullable();
            $table->dateTime('inserted_at');
            $table->dateTime('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
