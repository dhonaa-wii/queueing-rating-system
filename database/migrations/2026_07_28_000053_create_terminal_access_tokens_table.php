<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_terminal_id')->constrained('room_terminals');
            $table->string('token_hash', 255);
            $table->string('manual_code_hash', 255)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_access_tokens');
    }
};
