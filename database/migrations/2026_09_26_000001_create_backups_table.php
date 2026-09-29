<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('RUNNING'); // RUNNING / COMPLETED / FAILED
            $table->string('trigger', 20)->default('MANUAL'); // MANUAL / SCHEDULED
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->unsignedSmallInteger('table_count')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            // Resume position for a run split across several requests.
            $table->json('state')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
