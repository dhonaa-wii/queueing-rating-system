<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-directed 2026-09-20: a category can require more than one payment
 * (e.g. a defense fee and a documentation fee), each verified/resolved
 * independently against its own reference number — see the migration right
 * after this one, which points payment_verifications at a specific row here
 * instead of just an attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_payment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('presentation_categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_payment_types');
    }
};
