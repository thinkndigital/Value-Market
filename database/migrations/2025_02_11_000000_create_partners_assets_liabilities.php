<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (docs/PHASE_10_PARTNERS_ASSETS_LIABILITIES.md): net-new, confirmed absent
 * (docs/DATABASE_GAP_ANALYSIS.md §5), built on Phase 9's ledger. Also extends the chart of accounts with the
 * 3 accounts this phase's own ledger postings need (fixed assets, accumulated depreciation, loans payable) -
 * an additive append to Phase 9's seed, not a change to that migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->string('name', 256);
                $table->string('email', 256)->nullable();
                $table->string('phone', 32)->nullable();
                $table->decimal('ownership_percentage', 5, 2)->nullable();
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->timestamps();
            });
        }

        // Immutable ledger of capital movements - a partner's capital balance is always computed by summing
        // these (PartnerService::capitalBalance()), never stored as a separately-maintained running total,
        // so there is no separate row that can drift out of sync with the transaction history.
        if (!Schema::hasTable('partner_transactions')) {
            Schema::create('partner_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('partner_id')->index();
                $table->string('type', 16)->comment('contribution | withdrawal | profit_share | loss_share');
                $table->decimal('amount', 15, 4);
                $table->string('description', 512)->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->string('name', 256);
                $table->string('category', 64)->nullable();
                $table->date('acquisition_date');
                $table->decimal('acquisition_cost', 15, 4);
                $table->unsignedInteger('useful_life_months')->nullable();
                $table->decimal('salvage_value', 15, 4)->default(0);
                $table->string('status', 16)->default('active')->comment('active | disposed');
                $table->date('disposed_at')->nullable();
                $table->timestamps();
            });
        }

        // One row per depreciation run (usually monthly) - immutable, same ledger discipline as everything
        // else added since Phase 5.
        if (!Schema::hasTable('depreciation_schedules')) {
            Schema::create('depreciation_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id')->index();
                $table->date('period_date');
                $table->decimal('depreciation_amount', 15, 4);
                $table->decimal('accumulated_depreciation', 15, 4);
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->timestamps();
                $table->unique(['asset_id', 'period_date']);
            });
        }

        if (!Schema::hasTable('liabilities')) {
            Schema::create('liabilities', function (Blueprint $table) {
                $table->id();
                $table->string('name', 256);
                $table->string('category', 32)->comment('loan | accrued_expense | other');
                $table->decimal('principal_amount', 15, 4);
                $table->decimal('outstanding_balance', 15, 4);
                $table->date('due_date')->nullable();
                $table->string('status', 16)->default('active')->comment('active | paid');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('chart_of_accounts')) {
            $now = now();
            $additions = [
                ['code' => '1200', 'name' => 'Fixed Assets', 'type' => 'asset'],
                // Modeling simplification (documented in PHASE_10_PARTNERS_ASSETS_LIABILITIES.md §3): a
                // real contra-asset is credit-normal despite relating to assets. Rather than adding a 6th
                // account-type kind to LedgerService's two-bucket normal-balance logic, this is classified
                // under the existing 'liability' bucket purely so accountBalance() computes its (credit -
                // debit) sign correctly - not a claim that accumulated depreciation is a liability.
                ['code' => '1210', 'name' => 'Accumulated Depreciation (contra-asset)', 'type' => 'liability'],
                ['code' => '2200', 'name' => 'Loans Payable', 'type' => 'liability'],
                ['code' => '5200', 'name' => 'Depreciation Expense', 'type' => 'expense'],
            ];
            foreach ($additions as $account) {
                if (!DB::table('chart_of_accounts')->where('code', $account['code'])->exists()) {
                    DB::table('chart_of_accounts')->insert(array_merge($account, [
                        'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liabilities');
        Schema::dropIfExists('depreciation_schedules');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('partner_transactions');
        Schema::dropIfExists('partners');
    }
};
