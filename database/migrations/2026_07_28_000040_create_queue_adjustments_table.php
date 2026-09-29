<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_entry_id')->constrained('queue_entries');
            $table->foreignId('adjustment_type_id')->constrained('queue_adjustment_types');
            $table->integer('old_position')->unsigned()->nullable();
            $table->integer('new_position')->unsigned()->nullable();
            $table->foreignId('reason_id')->constrained('adjustment_reasons');
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->constrained('users');
            $table->dateTime('adjusted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_adjustments');
    }
};
