<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('presentation_categories');
            $table->date('presentation_date');
            $table->time('event_start_time');
            $table->time('event_end_time');
            $table->foreignId('event_date_status_id')->constrained('event_date_statuses');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'presentation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_dates');
    }
};
