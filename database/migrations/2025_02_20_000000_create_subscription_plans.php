<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 32-phase SaaS brief, Phase 11 (Platform Commission & Monetization): the product owner's decision was
 * both subscription AND commission together (docs/IMPLEMENTATION_PROGRESS.md's "Next-step decision
 * needed" section), with 2-3 tiers (Basic/Pro/Premium). Exact tier names/prices/limits were an open
 * blocker - resolved by the product owner as "seed real defaults, let the admin control them" rather than
 * this pass guessing real business pricing. `subscription_plans` is a plain admin-managed table (no
 * billing/payment collection wired to it yet - see docs/PHASE_11_SUBSCRIPTIONS.md for what's deferred);
 * `seller_data` gains the three columns that track which plan a seller is on.
 *
 * `seller_data.id` is a plain `int(11)` (see 2025_02_05_000000_create_branches_and_employees.php's
 * docblock) - `subscription_plan_id`/`seller_id`-shaped columns follow the same no-DB-FK convention used
 * throughout this repo's post-baseline migrations.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('slug', 128)->unique();
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->decimal('price', 10, 2)->default(0);
            // Null = use the platform-wide commission_rules default; set = this plan overrides it.
            $table->decimal('commission_rate', 5, 2)->nullable();
            // Null = unlimited.
            $table->unsignedInteger('max_products')->nullable();
            $table->text('description')->nullable();
            // JSON array of short feature-line strings shown on the plan card - display only, not
            // individually enforced anywhere (see docs/PHASE_11_SUBSCRIPTIONS.md's deferred list).
            $table->text('features')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('seller_data', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_plan_id')->nullable()->after('status');
            $table->timestamp('subscription_started_at')->nullable()->after('subscription_plan_id');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_started_at');
        });

        // Idempotent, same pattern as 2025_02_02_000000_seed_default_storage_type.php - only seed when the
        // table is genuinely empty, so this never overwrites an admin's own edits on a re-run.
        if (DB::table('subscription_plans')->count() === 0) {
            $now = now();
            DB::table('subscription_plans')->insert([
                [
                    'name' => 'Basic', 'slug' => 'basic', 'billing_cycle' => 'monthly',
                    'price' => 0, 'commission_rate' => null, 'max_products' => 50,
                    'description' => 'Placeholder starter tier - the admin should review and set real pricing before launch.',
                    'features' => json_encode(['Up to 50 products', 'Standard platform commission', 'Email support']),
                    'status' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Pro', 'slug' => 'pro', 'billing_cycle' => 'monthly',
                    'price' => 29.99, 'commission_rate' => null, 'max_products' => 500,
                    'description' => 'Placeholder mid tier - the admin should review and set real pricing before launch.',
                    'features' => json_encode(['Up to 500 products', 'Reduced platform commission', 'Priority email support']),
                    'status' => 1, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'name' => 'Premium', 'slug' => 'premium', 'billing_cycle' => 'monthly',
                    'price' => 79.99, 'commission_rate' => null, 'max_products' => null,
                    'description' => 'Placeholder top tier - the admin should review and set real pricing before launch.',
                    'features' => json_encode(['Unlimited products', 'Lowest platform commission', 'Priority support']),
                    'status' => 1, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('seller_data', function (Blueprint $table) {
            $table->dropColumn(['subscription_plan_id', 'subscription_started_at', 'subscription_expires_at']);
        });
        Schema::dropIfExists('subscription_plans');
    }
};
