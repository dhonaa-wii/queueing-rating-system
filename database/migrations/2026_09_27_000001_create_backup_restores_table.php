<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Restoring a backup can bring back a `migrations` table that predates
        // this one while the table itself is kept (it is never restored), so the
        // migration has to tolerate the table already being there.
        if (Schema::hasTable('backup_restores')) {
            return;
        }

        // Deliberately no foreign keys: a restore drops and recreates `users`
        // and `backups`, and this row is the record that survives it.
        Schema::create('backup_restores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('backup_id')->nullable();
            $table->string('source_file_name')->nullable();
            $table->string('status', 20)->default('RUNNING'); // RUNNING / COMPLETED / FAILED
            $table->char('token_hash', 64);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->string('triggered_by_username', 100)->nullable();
            $table->unsignedBigInteger('safety_backup_id')->nullable();
            // Resume position for a run split across several requests.
            $table->json('state')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restores');
    }
};
