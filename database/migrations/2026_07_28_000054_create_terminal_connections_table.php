<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_terminal_id')->constrained('room_terminals');
            $table->foreignId('panelist_user_id')->constrained('users');
            $table->dateTime('connected_at');
            $table->dateTime('disconnected_at')->nullable();
            $table->foreignId('connection_status_id')->constrained('connection_statuses');
            $table->string('authenticated_via', 30);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_connections');
    }
};
