<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_session_id')->constrained('room_sessions');
            $table->tinyInteger('terminal_number')->unsigned();
            $table->foreignId('terminal_type_id')->constrained('terminal_types');
            $table->string('device_identifier', 255)->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->unique(['room_session_id', 'terminal_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_terminals');
    }
};
