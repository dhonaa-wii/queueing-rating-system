<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_run_id')->constrained('presentation_runs');
            $table->dateTime('paused_at');
            $table->dateTime('resumed_at')->nullable();
            $table->foreignId('reason_id')->nullable()->constrained('adjustment_reasons');
            $table->text('remarks')->nullable();
            $table->foreignId('paused_by')->constrained('users');
            $table->foreignId('resumed_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_pauses');
    }
};
