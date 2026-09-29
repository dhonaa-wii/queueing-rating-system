<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('presentation_categories');
            $table->string('room_name', 100);
            $table->unsignedSmallInteger('default_panelist_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['category_id', 'room_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rooms');
    }
};
