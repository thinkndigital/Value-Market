<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A seller's own internal list of "who I buy stock from" for the Phase 5 procurement/inventory
 * flow (purchase orders, goods received notes). Renamed from `Supplier` (32-phase SaaS brief,
 * Sidebar Engine pass) purely to disambiguate from the platform-level Wholesaler module, which the
 * new master architecture prompt's UI labels this app as "Supplier" - the two are unrelated
 * concepts that happened to share a name. The underlying `suppliers` table/columns are unchanged.
 */
class ProcurementVendor extends Model
{
    protected $table = 'suppliers';

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'seller_id', 'name', 'contact_person', 'phone', 'email', 'address', 'status',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }
}
