<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposed_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_group_id')->constrained('research_groups');
            $table->foreignId('presentation_attempt_id')->nullable()->constrained('presentation_attempts');
            $table->string('title_text', 300);
            $table->tinyInteger('sort_order')->unsigned();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_titles');
    }
};
