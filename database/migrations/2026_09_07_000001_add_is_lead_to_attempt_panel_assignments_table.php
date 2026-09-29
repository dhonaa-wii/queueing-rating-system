<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_panel_assignments', function (Blueprint $table) {
            $table->boolean('is_lead')->default(false)->after('assignment_kind_id');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_panel_assignments', function (Blueprint $table) {
            $table->dropColumn('is_lead');
        });
    }
};
