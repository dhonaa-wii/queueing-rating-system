<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * User-directed 2026-09-14: remove the university and college logos from the
 * system permanently. The letterhead keeps its text lines; the uploaded logo
 * files are deleted along with the two columns that pointed at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('evaluation_letterheads')->get(['logo_path', 'secondary_logo_path']) as $row) {
            foreach ([$row->logo_path, $row->secondary_logo_path] as $path) {
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        Storage::disk('public')->deleteDirectory('evaluation-letterhead');

        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'secondary_logo_path']);
        });
    }

    /**
     * Restores the columns only — the deleted image files cannot be brought back.
     */
    public function down(): void
    {
        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('id');
            $table->string('secondary_logo_path', 255)->nullable()->after('logo_path');
        });
    }
};
