<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton: exactly one row matters (MaintenanceSetting::current()).
        Schema::create('maintenance_settings', function (Blueprint $table) {
            $table->id();
            // NULL keeps audit entries forever.
            $table->unsignedSmallInteger('audit_retention_days')->nullable()->default(365);
            $table->unsignedSmallInteger('log_max_mb')->default(10);
            $table->unsignedSmallInteger('log_keep_days')->default(14);
            $table->timestamp('last_run_at')->nullable();
            $table->json('last_run')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_settings');
    }
};
