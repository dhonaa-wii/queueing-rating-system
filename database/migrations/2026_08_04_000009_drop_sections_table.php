<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sections');
    }

    public function down(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->string('section_code', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['program_id', 'year_level', 'section_code']);
        });
    }
};
