<?php

use App\Models\PaymentVerification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-directed 2026-09-21: a payment reference/receipt number must never
 * be accepted twice anywhere in the system — not on the same attempt, not
 * a different payment type, not a different category. receipt_code_
 * encrypted uses Laravel's non-deterministic 'encrypted' cast (a fresh IV
 * every save), so it can never be queried or uniquely constrained directly.
 * This adds a deterministic SHA-256 hash of the normalized (trimmed,
 * whitespace-collapsed, uppercased) reference number alongside it, purely
 * for duplicate lookups — PaymentVerificationService is the only
 * reader/writer of it, the plaintext itself is never derivable from the
 * hash, and the real value stays exclusively in the encrypted column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->string('receipt_code_hash', 64)->nullable()->after('receipt_code_encrypted');
        });

        // Backfill through Eloquent so the existing 'encrypted' cast decrypts
        // each value before it's hashed — a raw DB read would see ciphertext.
        PaymentVerification::query()->whereNotNull('receipt_code_encrypted')->each(function (PaymentVerification $verification) {
            $normalized = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $verification->receipt_code_encrypted)));
            $verification->receipt_code_hash = hash('sha256', $normalized);
            $verification->saveQuietly();
        });

        // A plain unique index — MySQL allows any number of NULLs through it,
        // so rows with no reference number recorded yet never collide. This
        // is a defense-in-depth backstop; PaymentVerificationService checks
        // for a duplicate before ever reaching this constraint.
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->unique('receipt_code_hash', 'payment_verifications_receipt_code_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->dropUnique('payment_verifications_receipt_code_hash_unique');
            $table->dropColumn('receipt_code_hash');
        });
    }
};
