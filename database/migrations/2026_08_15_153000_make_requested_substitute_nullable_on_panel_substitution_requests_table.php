<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * requested_substitute_user_id was originally required, but a panelist
 * self-reporting unavailability (functional-spec §5.2/§9.7's substitution
 * workflow) doesn't know who else is available — user-directed 2026-08-15:
 * "panel will not suggest sub." Admin picks the actual replacement when
 * reviewing the request, same as the rest of Panel Assignment's approval
 * flow. No doctrine/dbal in this project (confirmed via composer.json),
 * so this uses a raw MODIFY rather than Blueprint::change().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE panel_substitution_requests MODIFY requested_substitute_user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE panel_substitution_requests MODIFY requested_substitute_user_id BIGINT UNSIGNED NOT NULL');
    }
};
