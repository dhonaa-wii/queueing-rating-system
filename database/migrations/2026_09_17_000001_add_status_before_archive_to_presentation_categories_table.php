<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Captures the status a category held right before Archive was pressed,
     * so Unarchive can restore it exactly rather than guessing — deriveStatus()
     * treats ARCHIVED (and COMPLETED) as sticky, so once archived nothing ever
     * re-derives what it used to be on its own.
     */
    public function up(): void
    {
        Schema::table('presentation_categories', function (Blueprint $table) {
            $table->foreignId('status_before_archive_id')->nullable()
                ->after('archived_at')
                ->constrained('category_statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presentation_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_before_archive_id');
        });
    }
};
