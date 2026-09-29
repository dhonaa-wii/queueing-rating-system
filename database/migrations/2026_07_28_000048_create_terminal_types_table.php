<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('can_control_flow');
            $table->boolean('can_verify_payment');
            $table->boolean('can_evaluate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_types');
    }
};
