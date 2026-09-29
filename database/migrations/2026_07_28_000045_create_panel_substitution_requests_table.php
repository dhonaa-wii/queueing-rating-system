<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_substitution_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->constrained('presentation_attempts');
            $table->foreignId('original_panelist_user_id')->nullable()->constrained('users');
            $table->foreignId('requested_substitute_user_id')->constrained('users');
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason');
            $table->foreignId('status_id')->constrained('substitution_statuses');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_substitution_requests');
    }
};
