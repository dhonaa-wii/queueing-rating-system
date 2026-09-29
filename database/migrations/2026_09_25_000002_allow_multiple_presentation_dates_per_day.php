<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_dates', function (Blueprint $table) {
            // The FK on category_id leans on the old unique index, so a plain
            // index has to exist before that one can be dropped.
            $table->index(['category_id', 'presentation_date'], 'presentation_dates_category_date_index');
        });

        Schema::table('presentation_dates', function (Blueprint $table) {
            $table->dropUnique(['category_id', 'presentation_date']);
        });
    }

    public function down(): void
    {
        Schema::table('presentation_dates', function (Blueprint $table) {
            $table->unique(['category_id', 'presentation_date']);
        });

        Schema::table('presentation_dates', function (Blueprint $table) {
            $table->dropIndex('presentation_dates_category_date_index');
        });
    }
};
