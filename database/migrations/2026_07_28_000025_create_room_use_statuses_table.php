<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_use_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_accepting_queue');
            $table->boolean('is_terminal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_use_statuses');
    }
};
