<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 - Money Precision conversion (docs/PHASE_1_FINANCIAL_PRECISION.md, Task C).
 *
 * Converts every monetary column identified in docs/PHASE_1_FINANCIAL_PRECISION.md from double/float/
 * varchar to DECIMAL, so monetary values stop being represented in binary floating point. Column-by-column
 * rationale, exact target precision, and the columns deliberately EXCLUDED (ratings, physical dimensions,
 * ambiguous int columns pending a business-rule decision) are documented in that file - this migration
 * intentionally does not touch anything not listed there.
 *
 * Three precision tiers, chosen to avoid a second migration later:
 *  - DECIMAL(15,4) for currency amounts (prices, totals, balances, charges, commissions-as-amount):
 *    4 decimal places absorbs repeated rounding across tax/commission splits and covers every currency's
 *    minor-unit count (JPY's 0 through 3-decimal currencies like KWD) without loss; 15 total digits covers
 *    any realistic order/balance magnitude.
 *  - DECIMAL(8,4) for percentage/rate columns (e.g. order_items.tax_percent): percentages are 0-100-ish,
 *    so far fewer integer digits are needed, but the same 4 fractional digits avoid rounding drift when a
 *    rate is applied to a large amount.
 *  - DECIMAL(20,10) for currency exchange rates: these are multiplicative factors applied to potentially
 *    large totals, so they need materially more precision than a stored amount - and currencies.exchange_rate
 *    is not even numeric today (varchar(256)), which is the more urgent half of this fix.
 *
 * CRITICAL - READ docs/PHASE_1_FINANCIAL_PRECISION.md BEFORE RUNNING THIS AGAINST PRODUCTION DATA.
 * This repository has no production data available to validate against (see that file's "Data validation"
 * section for exactly what was and wasn't possible to verify in this session). Before running this
 * migration against a real database, run the accompanying validation report
 * (`php artisan money:precision-report`, see app/Console/Commands/MoneyPrecisionReport.php) and resolve
 * every flagged row - do not run this migration blind against production.
 *
 * Idempotent: skips any column already converted to a DECIMAL type, so safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('areas', 'minimum_free_delivery_order_amount') && !self::alreadyDecimal('areas', 'minimum_free_delivery_order_amount')) {
            DB::statement("ALTER TABLE `areas` MODIFY `minimum_free_delivery_order_amount` DECIMAL(15,4) NOT NULL DEFAULT 100");
        }

        if (Schema::hasColumn('areas', 'delivery_charges') && !self::alreadyDecimal('areas', 'delivery_charges')) {
            DB::statement("ALTER TABLE `areas` MODIFY `delivery_charges` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('cities', 'minimum_free_delivery_order_amount') && !self::alreadyDecimal('cities', 'minimum_free_delivery_order_amount')) {
            DB::statement("ALTER TABLE `cities` MODIFY `minimum_free_delivery_order_amount` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('cities', 'delivery_charges') && !self::alreadyDecimal('cities', 'delivery_charges')) {
            DB::statement("ALTER TABLE `cities` MODIFY `delivery_charges` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('combo_products', 'price') && !self::alreadyDecimal('combo_products', 'price')) {
            DB::statement("ALTER TABLE `combo_products` MODIFY `price` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('combo_products', 'special_price') && !self::alreadyDecimal('combo_products', 'special_price')) {
            DB::statement("ALTER TABLE `combo_products` MODIFY `special_price` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('combo_products', 'delivery_charges') && !self::alreadyDecimal('combo_products', 'delivery_charges')) {
            DB::statement("ALTER TABLE `combo_products` MODIFY `delivery_charges` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('fund_transfers', 'opening_balance') && !self::alreadyDecimal('fund_transfers', 'opening_balance')) {
            DB::statement("ALTER TABLE `fund_transfers` MODIFY `opening_balance` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('fund_transfers', 'closing_balance') && !self::alreadyDecimal('fund_transfers', 'closing_balance')) {
            DB::statement("ALTER TABLE `fund_transfers` MODIFY `closing_balance` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('fund_transfers', 'amount') && !self::alreadyDecimal('fund_transfers', 'amount')) {
            DB::statement("ALTER TABLE `fund_transfers` MODIFY `amount` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('orders', 'total') && !self::alreadyDecimal('orders', 'total')) {
            DB::statement("ALTER TABLE `orders` MODIFY `total` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('orders', 'delivery_charge') && !self::alreadyDecimal('orders', 'delivery_charge')) {
            DB::statement("ALTER TABLE `orders` MODIFY `delivery_charge` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('orders', 'wallet_balance') && !self::alreadyDecimal('orders', 'wallet_balance')) {
            DB::statement("ALTER TABLE `orders` MODIFY `wallet_balance` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('orders', 'promo_discount') && !self::alreadyDecimal('orders', 'promo_discount')) {
            DB::statement("ALTER TABLE `orders` MODIFY `promo_discount` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('orders', 'discount') && !self::alreadyDecimal('orders', 'discount')) {
            DB::statement("ALTER TABLE `orders` MODIFY `discount` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('orders', 'total_payable') && !self::alreadyDecimal('orders', 'total_payable')) {
            DB::statement("ALTER TABLE `orders` MODIFY `total_payable` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('orders', 'final_total') && !self::alreadyDecimal('orders', 'final_total')) {
            DB::statement("ALTER TABLE `orders` MODIFY `final_total` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_charges', 'delivery_charge') && !self::alreadyDecimal('order_charges', 'delivery_charge')) {
            DB::statement("ALTER TABLE `order_charges` MODIFY `delivery_charge` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_charges', 'promo_discount') && !self::alreadyDecimal('order_charges', 'promo_discount')) {
            DB::statement("ALTER TABLE `order_charges` MODIFY `promo_discount` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_charges', 'sub_total') && !self::alreadyDecimal('order_charges', 'sub_total')) {
            DB::statement("ALTER TABLE `order_charges` MODIFY `sub_total` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_charges', 'total') && !self::alreadyDecimal('order_charges', 'total')) {
            DB::statement("ALTER TABLE `order_charges` MODIFY `total` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_items', 'price') && !self::alreadyDecimal('order_items', 'price')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `price` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('order_items', 'discounted_price') && !self::alreadyDecimal('order_items', 'discounted_price')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `discounted_price` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_items', 'tax_amount') && !self::alreadyDecimal('order_items', 'tax_amount')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `tax_amount` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('order_items', 'discount') && !self::alreadyDecimal('order_items', 'discount')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `discount` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('order_items', 'sub_total') && !self::alreadyDecimal('order_items', 'sub_total')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `sub_total` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('order_items', 'admin_commission_amount') && !self::alreadyDecimal('order_items', 'admin_commission_amount')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `admin_commission_amount` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('order_items', 'seller_commission_amount') && !self::alreadyDecimal('order_items', 'seller_commission_amount')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `seller_commission_amount` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('parcels', 'delivery_charge') && !self::alreadyDecimal('parcels', 'delivery_charge')) {
            DB::statement("ALTER TABLE `parcels` MODIFY `delivery_charge` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('parcel_items', 'unit_price') && !self::alreadyDecimal('parcel_items', 'unit_price')) {
            DB::statement("ALTER TABLE `parcel_items` MODIFY `unit_price` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('products', 'delivery_charges') && !self::alreadyDecimal('products', 'delivery_charges')) {
            DB::statement("ALTER TABLE `products` MODIFY `delivery_charges` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('product_variants', 'price') && !self::alreadyDecimal('product_variants', 'price')) {
            DB::statement("ALTER TABLE `product_variants` MODIFY `price` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('product_variants', 'special_price') && !self::alreadyDecimal('product_variants', 'special_price')) {
            DB::statement("ALTER TABLE `product_variants` MODIFY `special_price` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('promo_codes', 'minimum_order_amount') && !self::alreadyDecimal('promo_codes', 'minimum_order_amount')) {
            DB::statement("ALTER TABLE `promo_codes` MODIFY `minimum_order_amount` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('promo_codes', 'discount') && !self::alreadyDecimal('promo_codes', 'discount')) {
            DB::statement("ALTER TABLE `promo_codes` MODIFY `discount` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('promo_codes', 'max_discount_amount') && !self::alreadyDecimal('promo_codes', 'max_discount_amount')) {
            DB::statement("ALTER TABLE `promo_codes` MODIFY `max_discount_amount` DECIMAL(15,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('seller_commissions', 'commission') && !self::alreadyDecimal('seller_commissions', 'commission')) {
            DB::statement("ALTER TABLE `seller_commissions` MODIFY `commission` DECIMAL(15,4) NOT NULL DEFAULT 0.00");
        }

        if (Schema::hasColumn('seller_store', 'commission') && !self::alreadyDecimal('seller_store', 'commission')) {
            DB::statement("ALTER TABLE `seller_store` MODIFY `commission` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('transactions', 'amount') && !self::alreadyDecimal('transactions', 'amount')) {
            DB::statement("ALTER TABLE `transactions` MODIFY `amount` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('users', 'balance') && !self::alreadyDecimal('users', 'balance')) {
            DB::statement("ALTER TABLE `users` MODIFY `balance` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('users', 'cash_received') && !self::alreadyDecimal('users', 'cash_received')) {
            DB::statement("ALTER TABLE `users` MODIFY `cash_received` DECIMAL(15,4) NOT NULL DEFAULT 0.00");
        }

        if (Schema::hasColumn('wallet_transactions', 'amount') && !self::alreadyDecimal('wallet_transactions', 'amount')) {
            DB::statement("ALTER TABLE `wallet_transactions` MODIFY `amount` DECIMAL(15,4) NOT NULL");
        }

        if (Schema::hasColumn('zipcodes', 'minimum_free_delivery_order_amount') && !self::alreadyDecimal('zipcodes', 'minimum_free_delivery_order_amount')) {
            DB::statement("ALTER TABLE `zipcodes` MODIFY `minimum_free_delivery_order_amount` DECIMAL(15,4) NOT NULL DEFAULT 0");
        }

        if (Schema::hasColumn('zipcodes', 'delivery_charges') && !self::alreadyDecimal('zipcodes', 'delivery_charges')) {
            DB::statement("ALTER TABLE `zipcodes` MODIFY `delivery_charges` DECIMAL(15,4) NULL DEFAULT 0");
        }

        if (Schema::hasColumn('order_items', 'tax_percent') && !self::alreadyDecimal('order_items', 'tax_percent')) {
            DB::statement("ALTER TABLE `order_items` MODIFY `tax_percent` DECIMAL(8,4) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('currencies', 'exchange_rate') && !self::alreadyDecimal('currencies', 'exchange_rate')) {
            DB::statement("ALTER TABLE `currencies` MODIFY `exchange_rate` DECIMAL(20,10) NULL DEFAULT NULL");
        }

        if (Schema::hasColumn('orders', 'order_payment_currency_conversion_rate') && !self::alreadyDecimal('orders', 'order_payment_currency_conversion_rate')) {
            DB::statement("ALTER TABLE `orders` MODIFY `order_payment_currency_conversion_rate` DECIMAL(20,10) NOT NULL");
        }
    }

    public function down(): void
    {
        // Intentionally no-op. Reverting DECIMAL -> double/float/varchar would re-introduce float rounding
        // error and, for currencies.exchange_rate, silently truncate any value that no longer fits varchar
        // formatting assumptions. If this migration needs to be undone, restore from a pre-migration backup
        // instead of running php artisan migrate:rollback on it.
    }

    /**
     * True if the column has already been converted to a DECIMAL/NUMERIC type - makes this migration safe
     * to re-run without re-issuing (harmless but noisy) ALTER TABLE statements.
     */
    private static function alreadyDecimal(string $table, string $column): bool
    {
        $row = DB::selectOne(
            "SELECT DATA_TYPE as data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        return $row !== null && in_array(strtolower($row->data_type), ['decimal', 'numeric'], true);
    }
};
