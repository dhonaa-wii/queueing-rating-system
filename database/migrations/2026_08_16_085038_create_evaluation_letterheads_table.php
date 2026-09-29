<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_letterheads', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path', 255)->nullable();
            $table->string('secondary_logo_path', 255)->nullable();
            $table->string('line_1', 200)->nullable();
            $table->string('line_2', 200)->nullable();
            $table->string('line_3', 200)->nullable();
            $table->string('line_4', 200)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_letterheads');
    }
};
