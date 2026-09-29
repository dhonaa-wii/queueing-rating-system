<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton: exactly one row matters (BackupSetting::current()).
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->string('frequency', 10)->default('OFF'); // OFF / DAILY / WEEKLY
            $table->string('run_time', 5)->default('02:00'); // HH:MM, app timezone
            $table->unsignedSmallInteger('keep_count')->default(5);
            $table->text('archive_password')->nullable(); // encrypted cast
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
