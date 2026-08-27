<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTransaction extends Model
{
    const TYPE_CONTRIBUTION = 'contribution';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_PROFIT_SHARE = 'profit_share';
    const TYPE_LOSS_SHARE = 'loss_share';

    /** Types that increase a partner's capital balance. */
    const INCREASING_TYPES = [self::TYPE_CONTRIBUTION, self::TYPE_PROFIT_SHARE];

    protected $fillable = [
        'partner_id', 'type', 'amount', 'description', 'journal_entry_id',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
