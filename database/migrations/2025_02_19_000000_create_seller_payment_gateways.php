<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 32-phase SaaS brief, Phase 6 (Merchant-Specific Payment Gateways): payments today are entirely
 * platform-global (`payment_method` row in `settings`, read directly by every class in app/Libraries/
 * with zero seller context - confirmed by reading Razorpay.php/Stripe.php/Paypal.php/Paystack.php/
 * Phonepe.php). This table lets a seller store their own gateway credentials, which take priority over
 * the platform default when present and enabled; the platform default remains the fallback for sellers
 * who don't configure their own (docs/IMPLEMENTATION_PROGRESS.md Phase 6 decision).
 *
 * `credentials` is stored via the model's `encrypted:array` cast (Laravel encrypts/decrypts using
 * APP_KEY transparently) so gateway secret keys are never at rest in plaintext - the brief's explicit
 * "secret credentials must be encrypted" requirement.
 */
return new class extends Migration {
    public function up(): void
    {
        // seller_data.id is a plain `int(11)`, not Laravel's default unsigned bigint (see
        // 2025_02_05_000000_create_branches_and_employees.php's docblock) - seller_id is declared as a
        // plain signed integer() with no DB-level FK, matching that established convention (relationships
        // enforced in the application layer here, same as branches/employees/inventory).
        Schema::create('seller_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->integer('seller_id')->index();
            $table->string('gateway', 32);
            $table->text('credentials');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['seller_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payment_gateways');
    }
};
