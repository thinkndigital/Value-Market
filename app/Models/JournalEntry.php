<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 9 (docs/PHASE_9_ACCOUNTING_LEDGER.md): immutable once posted via LedgerService::postEntry() - a
 * correction is a new offsetting entry, never an update to this row or its lines.
 */
class JournalEntry extends Model
{
    protected $fillable = [
        'entry_date', 'description', 'reference_type', 'reference_id', 'created_by',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
