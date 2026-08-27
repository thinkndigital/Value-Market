<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md): running per-(seller, branch, variant) quantity - a
 * materialized view of StockMovement kept in sync at write time (see InventoryService::recordMovement()),
 * not recomputed from the ledger on every read. branch_id = null is the "unlocated" bucket every
 * pre-Phase-4 seller's existing stock falls into until they start using branches.
 */
class StockItem extends Model
{
    protected $fillable = [
        'seller_id', 'branch_id', 'product_variant_id', 'quantity',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(Product_variants::class, 'product_variant_id');
    }
}
