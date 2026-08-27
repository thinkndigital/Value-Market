<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md): an immutable ledger row - every stock quantity change,
 * whatever the source. Never updated or deleted after creation; a correction is a new offsetting movement.
 *
 * `movement_type` is direction only (IN/OUT); `reference_type` carries the reason (goods_received_note,
 * transfer, manual_adjustment, legacy_adjustment - see InventoryService).
 */
class StockMovement extends Model
{
    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';

    const REFERENCE_GOODS_RECEIVED_NOTE = 'goods_received_note';
    const REFERENCE_TRANSFER = 'transfer';
    const REFERENCE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    const REFERENCE_LEGACY_ADJUSTMENT = 'legacy_adjustment';

    public $timestamps = false;

    protected $fillable = [
        'seller_id', 'branch_id', 'product_variant_id', 'movement_type', 'quantity',
        'unit_cost', 'reference_type', 'reference_id', 'notes', 'created_at',
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
