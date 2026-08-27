<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (docs/PHASE_9_ACCOUNTING_LEDGER.md): the platform's financial foundation - a real double-entry
 * ledger, confirmed completely absent from the original dump (docs/DATABASE_GAP_ANALYSIS.md §5: "Chart of
 * Accounts / GL / Journal Entries: None"). Also seeds a minimal default chart of accounts - see
 * PHASE_9_ACCOUNTING_LEDGER.md §2 for why this is intentionally a starting point, not a claim of a complete,
 * business-specific chart of accounts (that needs real accounting input this migration can't provide).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique();
                $table->string('name', 256);
                $table->string('type', 16)->comment('asset | liability | equity | revenue | expense');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->boolean('is_system')->default(false)->comment('seeded by this migration - protected from deletion');
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->timestamps();
            });
        }

        // Immutable once posted - a correction is a new offsetting journal entry, same principle this
        // codebase already applies to stock_movements (Phase 5) and money (PHASE_1_FINANCIAL_PRECISION.md).
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->date('entry_date');
                $table->string('description', 512);
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (!Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->decimal('debit', 15, 4)->default(0);
                $table->decimal('credit', 15, 4)->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('chart_of_accounts') && DB::table('chart_of_accounts')->count() === 0) {
            $now = now();
            DB::table('chart_of_accounts')->insert([
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '2100', 'name' => 'Customer & Vendor Wallet Liability', 'type' => 'liability', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '3000', 'name' => "Owner's Equity / Retained Earnings", 'type' => 'equity', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '5000', 'name' => 'Commission Expense', 'type' => 'expense', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '5100', 'name' => 'Delivery Expense', 'type' => 'expense', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '9000', 'name' => 'Suspense / Uncategorized', 'type' => 'asset', 'is_system' => true, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
