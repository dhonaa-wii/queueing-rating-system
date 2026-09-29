<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_session_accounts', function (Blueprint $table) {
            $table->foreignId('credentials_reset_by')->nullable()->after('credentials_last_reset_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('room_session_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credentials_reset_by');
        });
    }
};
