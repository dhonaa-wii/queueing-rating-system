<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_form_versions', function (Blueprint $table) {
            $table->unsignedTinyInteger('scale_min')->default(0)->after('maximum_total_score');
            $table->unsignedTinyInteger('scale_max')->default(5)->after('scale_min');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_form_versions', function (Blueprint $table) {
            $table->dropColumn(['scale_min', 'scale_max']);
        });
    }
};
