<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eShop Plus v1.0.6 + v1.0.11 (docs/CHANGELOG_FEATURE_AUDIT.md): "Sellers can request custom
 * categories/brands" + "Admin can approve/reject seller category/brand requests" + "Seller App can
 * view/delete pending Categories/Brands".
 *
 * `categories`/`brands` already had a half-built hook for this: their `status` column has always
 * supported a third value (2 = "Not Approved" / pending) - the admin list() rendering in both
 * Admin\CategoryController and Admin\BrandController already had dead markup for a "Not Approved" /
 * "Approve" status dropdown for status==2 rows, and Seller\CategoryController::store()/
 * Seller\BrandController::store() already create seller-submitted rows with status=2 and a "Wait for
 * approval of admin" success message - this is the exact same convention already live for
 * `products.status` (2 = pending admin approval when `require_products_approval` is on, see
 * Admin\ProductController::update_status()). None of it was reachable end-to-end: nothing tracked
 * *which* seller requested a row (so a seller could never see or manage their own pending/rejected
 * requests), and Admin\CategoryController::update_status()/BrandController::update_status() did a blind
 * status==1 ? 0 : 1 toggle that ignored the dropdown's selected value entirely - selecting "Approve" on a
 * pending (2) row actually flipped it to 0 (deactivated), never to 1 (approved).
 *
 * This migration adds the two columns needed to close that gap without touching how `products`/
 * `combo_products` already reference `category_id`/`brand_id`: a seller-submitted category/brand is a
 * real row from the moment it's created (so the FK "just works" the instant it's approved - no id
 * migration needed later), just with `status = 2` until an admin approves it (status -> 1) or rejects it
 * (status -> 0, row kept, not deleted, so the seller can still see the rejection).
 *
 * - `requested_by_seller_id`: nullable `sellers.id` (not `users.id` - matches how every other
 *   seller-scoped query in this codebase resolves the acting seller via
 *   Seller::where('user_id', Auth::id())->value('id')). NULL for every admin-created row (the normal
 *   case) - only rows actually submitted through the seller "request a category/brand" form carry this.
 * - `approval_status`: tracks the seller-request lifecycle distinctly from `status`, because `status`
 *   alone can't tell a still-live "seller request was rejected" apart from "admin manually deactivated an
 *   already-approved row" - both end up with status=0. Defaults to 'approved' at the DB level, which
 *   both backfills every existing row (nothing currently live gets hidden or reinterpreted as a pending
 *   request) and covers every future admin-direct create (Admin\CategoryController::store() etc. need no
 *   changes) without needing a default in application code.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'brands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'requested_by_seller_id')) {
                    $blueprint->integer('requested_by_seller_id')->nullable()->after('store_id');
                }
                if (!Schema::hasColumn($table, 'approval_status')) {
                    $blueprint->string('approval_status', 20)->default('approved')->after('requested_by_seller_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'brands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'approval_status')) {
                    $blueprint->dropColumn('approval_status');
                }
                if (Schema::hasColumn($table, 'requested_by_seller_id')) {
                    $blueprint->dropColumn('requested_by_seller_id');
                }
            });
        }
    }
};
