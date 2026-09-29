<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->unique()->constrained('presentation_attempts');
            $table->foreignId('payment_status_id')->constrained('payment_statuses');
            $table->text('receipt_code_encrypted')->nullable();
            $table->foreignId('initially_checked_by')->nullable()->constrained('users');
            $table->foreignId('terminal_connection_id')->nullable()->constrained('terminal_connections');
            $table->dateTime('checked_at')->nullable();
            $table->dateTime('referred_to_admin_at')->nullable();
            $table->foreignId('referred_by')->nullable()->constrained('users');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->dateTime('resolved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verifications');
    }
};
