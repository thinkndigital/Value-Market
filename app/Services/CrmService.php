<?php

namespace App\Services;

use App\Models\CustomerNote;
use App\Models\CustomerSegment;
use App\Models\CustomerTag;
use App\Models\CustomerTagAssignment;
use App\Models\OrderItems;

/**
 * Phase 11 (docs/PHASE_11_CRM.md): notes/tags/segments are seller-scoped (nullable seller_id =
 * platform/admin-level) - the same customer can be shared across multiple sellers in this multi-vendor
 * marketplace, and each vendor's CRM view of them should be private to that vendor. Callers (controllers)
 * are responsible for passing the ACTING seller_id (via TenantContext, same as every controller since
 * Phase 4) - this service does not resolve identity itself.
 */
class CrmService
{
    public function addNote(int $customerUserId, int $authorUserId, string $note, ?int $sellerId = null): CustomerNote
    {
        if (trim($note) === '') {
            throw new \InvalidArgumentException('Note cannot be empty.');
        }

        return CustomerNote::forceCreate([
            'customer_user_id' => $customerUserId,
            'seller_id' => $sellerId,
            'author_user_id' => $authorUserId,
            'note' => $note,
        ]);
    }

    public function listNotes(int $customerUserId, ?int $sellerId = null)
    {
        return CustomerNote::where('customer_user_id', $customerUserId)
            ->when($sellerId === null, fn ($q) => $q->whereNull('seller_id'), fn ($q) => $q->where('seller_id', $sellerId))
            ->orderByDesc('id')
            ->get();
    }

    public function createTag(string $name, ?int $sellerId = null, ?string $color = null): CustomerTag
    {
        return CustomerTag::firstOrCreate(
            ['seller_id' => $sellerId, 'name' => $name],
            ['color' => $color]
        );
    }

    /** Idempotent - tagging a customer with a tag they already have is a no-op, not a duplicate row. */
    public function tagCustomer(int $customerUserId, int $tagId, ?int $assignedBy = null): CustomerTagAssignment
    {
        return CustomerTagAssignment::firstOrCreate(
            ['customer_user_id' => $customerUserId, 'customer_tag_id' => $tagId],
            ['assigned_by' => $assignedBy, 'created_at' => now()]
        );
    }

    public function untagCustomer(int $customerUserId, int $tagId): void
    {
        CustomerTagAssignment::where('customer_user_id', $customerUserId)
            ->where('customer_tag_id', $tagId)
            ->delete();
    }

    public function customerTags(int $customerUserId, ?int $sellerId = null)
    {
        return CustomerTag::whereHas('assignments', fn ($q) => $q->where('customer_user_id', $customerUserId))
            ->when($sellerId === null, fn ($q) => $q->whereNull('seller_id'), fn ($q) => $q->where('seller_id', $sellerId))
            ->get();
    }

    /** @param array<string, mixed> $criteria min_orders, min_total_spent, max_total_spent */
    public function createSegment(string $name, array $criteria, ?int $sellerId = null): CustomerSegment
    {
        return CustomerSegment::forceCreate([
            'seller_id' => $sellerId,
            'name' => $name,
            'criteria' => json_encode($criteria),
        ]);
    }

    /**
     * Membership is evaluated fresh every call against delivered order_items - never materialized into a
     * members table, so it's always current and can never go stale relative to new orders.
     *
     * @return array<int> customer user_ids matching the segment's criteria
     */
    public function evaluateSegment(CustomerSegment $segment): array
    {
        $criteria = json_decode($segment->criteria, true) ?? [];

        $query = OrderItems::where('active_status', 'delivered')
            ->when($segment->seller_id !== null, fn ($q) => $q->where('seller_id', $segment->seller_id))
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(DISTINCT order_id) as order_count, SUM(sub_total) as total_spent');

        if (isset($criteria['min_orders'])) {
            $query->havingRaw('COUNT(DISTINCT order_id) >= ?', [(int) $criteria['min_orders']]);
        }
        if (isset($criteria['min_total_spent'])) {
            $query->havingRaw('SUM(sub_total) >= ?', [(float) $criteria['min_total_spent']]);
        }
        if (isset($criteria['max_total_spent'])) {
            $query->havingRaw('SUM(sub_total) <= ?', [(float) $criteria['max_total_spent']]);
        }

        return $query->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Deliberately NOT stored anywhere (docs/DATABASE_GAP_ANALYSIS.md's own explicit guidance: "CLV can be
     * computed, not stored") - always the live sum of delivered order_items, optionally scoped to one
     * seller's view of this customer.
     */
    public function customerLifetimeValue(int $customerUserId, ?int $sellerId = null): float
    {
        return (float) OrderItems::where('user_id', $customerUserId)
            ->where('active_status', 'delivered')
            ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))
            ->sum('sub_total');
    }
}
