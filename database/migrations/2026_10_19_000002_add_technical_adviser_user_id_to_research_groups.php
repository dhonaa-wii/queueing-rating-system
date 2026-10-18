<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links a group to its adviser's panelist account (2026-10-19). The adviser
 * was stored by name only, so My Advisees found a group by matching names —
 * which broke as soon as a panelist's name was edited. The name column stays
 * (it is what every sheet prints); the id is what My Advisees matches on.
 *
 * Backfill: a group whose adviser name matches exactly one panelist of the
 * category's college (first + last, either order, deleted accounts
 * ignored) gets that panelist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_groups', function (Blueprint $table) {
            $table->foreignId('technical_adviser_user_id')->nullable()->after('technical_adviser_name')
                ->constrained('users')->nullOnDelete();
        });

        $normalize = fn (?string $name) => strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));

        $panelists = DB::table('panelist_profiles')
            ->join('user_profiles', 'user_profiles.user_id', '=', 'panelist_profiles.user_id')
            ->join('users', 'users.id', '=', 'panelist_profiles.user_id')
            ->whereNull('users.deleted_at')
            ->select('panelist_profiles.user_id', 'panelist_profiles.college_id', 'user_profiles.first_name', 'user_profiles.last_name')
            ->get();

        $groups = DB::table('research_groups')
            ->join('presentation_categories', 'presentation_categories.id', '=', 'research_groups.category_id')
            ->whereNotNull('research_groups.technical_adviser_name')
            ->select('research_groups.id', 'research_groups.technical_adviser_name', 'presentation_categories.college_id')
            ->get();

        foreach ($groups as $group) {
            $name = $normalize($group->technical_adviser_name);
            if ($name === '') {
                continue;
            }

            $matches = $panelists->filter(fn ($p) => $p->college_id === $group->college_id
                && in_array($name, [
                    $normalize("{$p->first_name} {$p->last_name}"),
                    $normalize("{$p->last_name} {$p->first_name}"),
                ], true));

            if ($matches->count() === 1) {
                DB::table('research_groups')->where('id', $group->id)
                    ->update(['technical_adviser_user_id' => $matches->first()->user_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('research_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technical_adviser_user_id');
        });
    }
};
