<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_date_id')->unique()->constrained('presentation_dates');
            $table->foreignId('event_status_id')->constrained('event_statuses');
            $table->foreignId('started_by')->nullable()->constrained('users');
            $table->dateTime('started_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users');
            $table->dateTime('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_events');
    }
};
