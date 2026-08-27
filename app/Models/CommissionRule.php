<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): a configurable commission rate. AffiliateService resolves
 * the applicable rule for a sale by scope specificity, most specific first:
 * product > category > vendor > affiliate > platform.
 */
class CommissionRule extends Model
{
    const SCOPE_PLATFORM = 'platform';
    const SCOPE_VENDOR = 'vendor';
    const SCOPE_AFFILIATE = 'affiliate';
    const SCOPE_CATEGORY = 'category';
    const SCOPE_PRODUCT = 'product';

    const SCOPE_PRECEDENCE = [
        self::SCOPE_PRODUCT, self::SCOPE_CATEGORY, self::SCOPE_VENDOR, self::SCOPE_AFFILIATE, self::SCOPE_PLATFORM,
    ];

    const RATE_PERCENTAGE = 'percentage';
    const RATE_FLAT = 'flat';

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'scope', 'scope_id', 'rate_type', 'rate_value', 'status',
    ];
}
