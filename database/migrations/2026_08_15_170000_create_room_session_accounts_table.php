<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_session_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_category_id')->constrained('presentation_categories');
            $table->string('room_name', 100);
            $table->string('username', 255)->unique();
            $table->string('password', 255);
            $table->smallInteger('max_concurrent_logins')->unsigned();
            $table->boolean('is_active')->default(true);
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('credentials_last_reset_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users');
            $table->dateTime('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['presentation_category_id', 'room_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_session_accounts');
    }
};
