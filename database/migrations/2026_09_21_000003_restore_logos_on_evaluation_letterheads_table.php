<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-directed 2026-09-21: put the two letterhead logos back. Reverses the
 * 2026_09_14_000001 removal — the columns return, but the image files that
 * migration deleted are gone for good, so both start out empty and are filled
 * again by uploading on the Letterhead page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('id');
            $table->string('secondary_logo_path', 255)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'secondary_logo_path']);
        });
    }
};
