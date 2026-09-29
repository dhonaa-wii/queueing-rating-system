<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_run_id')->constrained('presentation_runs');
            $table->foreignId('action_type_id')->constrained('presentation_action_types');
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('terminal_connection_id')->nullable()->constrained('terminal_connections');
            $table->foreignId('reason_id')->nullable()->constrained('adjustment_reasons');
            $table->text('remarks')->nullable();
            $table->dateTime('performed_at');
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_actions');
    }
};
