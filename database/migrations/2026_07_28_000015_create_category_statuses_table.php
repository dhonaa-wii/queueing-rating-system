<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('allows_registration');
            $table->boolean('allows_queue_generation');
            $table->boolean('is_terminal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_statuses');
    }
};
