<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS re-architecture brief: the "Wholesaler" role (a platform-level entity that lists products for
 * wholesale, distinct from the existing seller-scoped `Supplier` model - App\Models\Supplier /
 * `suppliers` table - which is where a SELLER buys their own stock from, not a marketplace-facing role;
 * see docs/ADMIN_SIDEBAR_REGROUP.md's naming note). A wholesaler maintains its own catalog
 * (`wholesaler_products`); a seller "imports" one into their own store, which - by design - creates a
 * normal row in the existing `products` table (reusing all of this app's existing product/stock/order/
 * storefront machinery instead of duplicating it) with `wholesaler_product_id` set for traceability and
 * the seller setting their own retail price/stock at import time.
 *
 * No real DB-level FOREIGN KEY constraints here, matching this app's existing schema style (verified via
 * `SHOW CREATE TABLE products` - category_id/seller_id are plain indexed columns, not FK-constrained).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wholesalers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('business_name');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->tinyInteger('status')->default(1); // 1 active, 0 inactive/suspended
            $table->string('disk')->default('public');
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('wholesaler_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wholesaler_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->longText('name'); // json, multilingual - matches products.name
            $table->mediumText('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('wholesale_price', 15, 4)->default(0);
            $table->integer('min_order_qty')->default(1);
            $table->integer('stock')->default(0);
            // 0 pending admin approval, 1 active/visible to sellers, 2 rejected/inactive - a lighter-touch
            // trust model than seller onboarding: a wholesaler account can log in and manage its catalog
            // immediately, but each individual listing needs admin approval before sellers can browse/
            // import it (admin moderation happens at the listing level, not the account level).
            $table->tinyInteger('status')->default(0);
            $table->boolean('affiliate_enabled')->default(0);
            $table->decimal('affiliate_commission_rate', 5, 2)->nullable();
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('wholesaler_id');
            $table->index('category_id');
            $table->index('status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('wholesaler_product_id')->nullable()->after('seller_id');
            $table->index('wholesaler_product_id');
        });

        // roles.id 7 => 'wholesaler', continuing the existing legacy-role-table pattern (see
        // App\Models\Role's own doc comment for the other 6 rows) rather than introducing a second
        // role mechanism for one new entity.
        if (!DB::table('roles')->where('id', 7)->exists()) {
            DB::table('roles')->insert([
                'id' => 7,
                'name' => 'wholesaler',
                'description' => 'Wholesalers',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['wholesaler_product_id']);
            $table->dropColumn('wholesaler_product_id');
        });
        Schema::dropIfExists('wholesaler_products');
        Schema::dropIfExists('wholesalers');
        DB::table('roles')->where('id', 7)->where('name', 'wholesaler')->delete();
    }
};
