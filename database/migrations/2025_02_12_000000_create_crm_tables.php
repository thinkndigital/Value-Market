<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 (docs/PHASE_11_CRM.md): net-new, confirmed absent (docs/DATABASE_GAP_ANALYSIS.md §5: "CRM
 * (segments, tags, notes, CLV): Only implicit via orders/users history"). Notes/tags/segments are all
 * seller-scoped (nullable seller_id = platform/admin-level) since the same customer can be shared across
 * multiple sellers in this multi-vendor marketplace, matching how a real vendor's private CRM view of a
 * shared customer should work. CLV is deliberately NOT a stored column anywhere - see
 * CrmService::customerLifetimeValue(), computed on demand from orders/order_items, per
 * DATABASE_GAP_ANALYSIS.md's own explicit guidance ("CLV can be computed, not stored").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_notes')) {
            Schema::create('customer_notes', function (Blueprint $table) {
                $table->id();
                $table->integer('customer_user_id')->index();
                $table->integer('seller_id')->nullable()->index();
                $table->integer('author_user_id');
                $table->text('note');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_tags')) {
            Schema::create('customer_tags', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->nullable()->index();
                $table->string('name', 100);
                $table->string('color', 16)->nullable();
                $table->timestamps();
                $table->unique(['seller_id', 'name']);
            });
        }

        if (!Schema::hasTable('customer_tag_assignments')) {
            Schema::create('customer_tag_assignments', function (Blueprint $table) {
                $table->id();
                $table->integer('customer_user_id')->index();
                $table->unsignedBigInteger('customer_tag_id')->index();
                $table->integer('assigned_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['customer_user_id', 'customer_tag_id']);
            });
        }

        // A saved filter definition - membership is evaluated dynamically against orders/users
        // (CrmService::evaluateSegment()), never materialized into a members table that could go stale.
        if (!Schema::hasTable('customer_segments')) {
            Schema::create('customer_segments', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->nullable()->index();
                $table->string('name', 256);
                $table->text('criteria')->comment('JSON: min_orders, min_total_spent, etc - see CrmService::evaluateSegment()');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('customer_tag_assignments');
        Schema::dropIfExists('customer_tags');
        Schema::dropIfExists('customer_notes');
    }
};
