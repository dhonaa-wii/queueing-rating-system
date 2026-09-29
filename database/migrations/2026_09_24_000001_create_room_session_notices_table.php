<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_session_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_session_id')->constrained('room_sessions')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('group_reference', 50);
            $table->string('message', 255);
            $table->foreignId('performed_by')->constrained('users');
            $table->dateTime('created_at');
            $table->dateTime('acknowledged_at')->nullable();

            $table->index(['room_session_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_session_notices');
    }
};
