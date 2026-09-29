<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panelist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('specialization', 200)->nullable();
            $table->foreignId('registered_by')->constrained('users');
            $table->timestamp('registered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panelist_profiles');
    }
};
