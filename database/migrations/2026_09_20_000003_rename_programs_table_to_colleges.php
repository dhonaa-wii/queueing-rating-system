<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-directed 2026-09-20: full rename of the "Program" concept to
     * "College" — the entity has only ever held college-level values in
     * real data (e.g. "College of Computing and Information Sciences"),
     * so the terminology is corrected system-wide: table, columns, model,
     * routes, and every visible label. A plain table rename — MySQL/
     * MariaDB keeps every existing FK constraint intact, repointed at the
     * new table name automatically, same as the 2026-08-04
     * departments -> programs rename this mirrors.
     */
    public function up(): void
    {
        Schema::rename('programs', 'colleges');
    }

    public function down(): void
    {
        Schema::rename('colleges', 'programs');
    }
};
