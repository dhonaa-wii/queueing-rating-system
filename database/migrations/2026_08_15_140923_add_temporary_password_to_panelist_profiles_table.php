<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->string('temporary_password', 50)->nullable()->after('specialization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->dropColumn('temporary_password');
        });
    }
};
