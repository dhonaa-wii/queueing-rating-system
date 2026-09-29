<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->unique()->constrained('presentation_categories');
            $table->boolean('payment_required')->default(false);
            $table->text('verification_instructions')->nullable();
            $table->boolean('allow_admin_referral')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_payment_settings');
    }
};
