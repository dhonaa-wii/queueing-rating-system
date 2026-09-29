<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->constrained('presentation_attempts');
            $table->foreignId('requirement_type_id')->constrained('requirement_types');
            $table->text('description');
            $table->dateTime('due_at')->nullable();
            $table->foreignId('status_id')->constrained('requirement_statuses');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_requirements');
    }
};
