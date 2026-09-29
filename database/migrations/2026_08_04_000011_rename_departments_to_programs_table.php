<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-directed 2026-08-04: Department and Program are merged into a single
     * Super-Admin-owned concept, kept under the name "Program" (Campus > Program).
     * The old Department>Program two-level hierarchy (dropped in the prior
     * migrations in this batch) is replaced by this single level.
     */
    public function up(): void
    {
        Schema::rename('departments', 'programs');
    }

    public function down(): void
    {
        Schema::rename('programs', 'departments');
    }
};
