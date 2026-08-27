<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): net-new schema, confirmed absent in the real dump
 * (docs/DATABASE_GAP_ANALYSIS.md §5 - "Warehouses / Branches" and "Employees" both listed as "None").
 * `branches` are physical locations owned by a seller_data tenant (the tenant unit Phase 1 resolved -
 * PHASE_1_ARCHITECTURE.md Task G) - Phase 5's warehouses/stock_items will reference branches, not the other
 * way around. `employees` are real login-capable staff (their own `users` row) distinct from the seller
 * owner, scoped to a seller and optionally one branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        // seller_data.id/users.id are plain `int(11)` (see baseline_vendors.php / baseline_identity_rbac.php)
        // - not Laravel's default unsigned bigint - so seller_id/branch_id/user_id below are declared as
        // plain signed integer() columns instead of foreignId()->constrained(), which would create a
        // bigint-vs-int type mismatch. Matches this codebase's existing convention (Phase 1-3 migrations)
        // of relationships enforced in the application layer, not DB-level FK constraints.
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->string('name', 256);
                $table->string('address', 512)->nullable();
                $table->integer('city')->nullable();
                $table->integer('zipcode')->nullable();
                $table->string('latitude', 256)->nullable();
                $table->string('longitude', 256)->nullable();
                $table->string('phone', 32)->nullable();
                $table->boolean('is_default')->default(false);
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->integer('user_id')->unique();
                $table->string('position', 256)->nullable();
                $table->text('permissions')->nullable();
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->string('disk', 256)->default('public');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('branches');
    }
};
