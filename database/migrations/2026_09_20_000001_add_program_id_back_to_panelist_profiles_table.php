<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('user_id')->constrained('programs')->nullOnDelete();
        });

        // Backfill: a panelist's program is the registering Admin's own
        // Program — the same rule the app now enforces on every new
        // registration (see App\Http\Controllers\Admin\PanelistController::
        // store()). department_or_program is left exactly as-is; it still
        // carries real, more granular affiliation data (e.g. "College of
        // Engineering" for a panelist pulled in from outside the registering
        // Admin's own college) that this FK does not replace.
        DB::table('panelist_profiles as pp')
            ->join('administrator_profiles as ap', 'ap.user_id', '=', 'pp.registered_by')
            ->update(['pp.program_id' => DB::raw('ap.program_id')]);
    }

    public function down(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
