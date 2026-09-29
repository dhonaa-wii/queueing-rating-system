<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_of_day_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_date_id')->constrained('presentation_dates');
            $table->dateTime('processed_at');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->integer('unresolved_group_count')->unsigned();
            $table->integer('absent_group_count')->unsigned();
            $table->integer('moved_to_category_end_count')->unsigned();
            $table->json('details_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_of_day_processing_logs');
    }
};
