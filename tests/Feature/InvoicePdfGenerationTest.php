<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\Parcel;
use App\Models\Parcelitem;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while investigating the 3 remaining major-version dependency upgrades docs/PHASE_17_FULL_QA_
 * PRODUCTION_READINESS.md §3 deferred (dompdf v2->v3, which laraveldaily/laravel-invoices' dompdf dependency
 * chain requires): before touching any dependency version, tried actually rendering an invoice PDF end to
 * end to have a real "before" baseline to compare against - and found invoice generation was already
 * completely broken on the CURRENT dependency versions, unrelated to any version bump. Two independent bugs
 * in resources/views/vendor/invoices/templates/invoice.blade.php (the app's own published override of the
 * package's default template):
 *
 * 1. Six calls to bare global functions that don't exist anywhere in this codebase - getSettings(),
 *    getMediaImageUrl() (x2), getVariantsValuesById(), formateCurrency() (x3) - only their *service-method*
 *    equivalents exist (app(SettingService::class)->getSettings(), etc.). Every one of these threw a fatal
 *    "Call to undefined function" the instant the template tried to render.
 * 2. fetchDetails() was called with plain table-name strings ('users', 'seller_data', 'seller_store',
 *    'order_charges') instead of Eloquent model classes, which fetchDetails() requires
 *    (app/function_helper.php:184's own validation immediately throws InvalidArgumentException otherwise).
 * 3. (invoice.blade.php only, discovered once the above two were fixed and the template's real per-seller
 *    content block actually executed for the first time in this investigation) - the per-item price ternary
 *    read $row['product_price']/$row['product_special_price'], fields ONLY Admin\OrderController::
 *    generatInvoicePDF() ever populates. Seller\OrderController::generatInvoicePDF() (a different real
 *    caller of the same template) only ever set 'price'/'discounted_price' - so every seller-panel invoice
 *    download threw "Undefined array key product_price". Fixed to read 'price'/'discounted_price', which
 *    both callers populate.
 *
 * Separately, Seller\OrderController::generatParcelInvoicePDF() called ->template('parcel_invoice'), and
 * no such view (resources/views/vendor/invoices/templates/parcel_invoice.blade.php) existed at all - every
 * call threw "View [templates.parcel_invoice] not found". Created it as an adaptation of the now-fixed
 * invoice.blade.php (same fixes applied, since it's a copy), reading the field names
 * generatParcelInvoicePDF() actually populates on each parcel item.
 *
 * These tests prove each of the three real invoice-generating call sites now produces an actual PDF
 * (starts with the %PDF magic bytes), not just "does not throw" - a rendering pipeline that silently
 * produced empty/broken output would pass a bare no-exception check but fail this.
 */
class InvoicePdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrderScenario(): array
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        // AppServiceProvider::boot() shares currency_symbol/etc to every view on a real HTTP request, but
        // only when !runningInConsole() - PHPUnit itself runs in console, so this test (the only one here
        // that actually renders a view) provides the same minimal stand-in used elsewhere this phase.
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => [], 'web_settings' => [], 'version' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'address' => '123 Test St',
        ]);
        $order = Order::forceCreate([
            'user_id' => $customer->id, 'store_id' => 1, 'mobile' => '9999999999', 'total' => 100,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 100]);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id, 'store_id' => 1, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 2, 'price' => 50, 'discounted_price' => 0,
            'tax_percent' => 0, 'tax_amount' => 0, 'sub_total' => 100, 'status' => 'placed',
            'active_status' => 'placed', 'order_type' => 'regular_order',
        ]);
        OrderCharges::forceCreate([
            'seller_id' => $seller->id, 'product_variant_ids' => (string) $variant->id,
            'order_id' => $order->id, 'order_item_ids' => (string) $orderItem->id,
        ]);

        return [$sellerUser, $seller, $order, $orderItem, $variant];
    }

    public function test_seller_generat_invoice_pdf_produces_a_real_pdf(): void
    {
        [$sellerUser, , $order] = $this->seedOrderScenario();
        Auth::login($sellerUser);

        $response = app(SellerOrderController::class)->generatInvoicePDF($order->id);
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content, 'generatInvoicePDF() must produce a real PDF, not throw or render empty output.');
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_admin_generat_invoice_pdf_produces_a_real_pdf(): void
    {
        [, , $order] = $this->seedOrderScenario();
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);

        $response = app(AdminOrderController::class)->generatInvoicePDF($order->id);
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_seller_generat_parcel_invoice_pdf_produces_a_real_pdf(): void
    {
        [$sellerUser, , $order, $orderItem, $variant] = $this->seedOrderScenario();
        $parcel = Parcel::forceCreate([
            'order_id' => $order->id, 'name' => 'Parcel 1', 'status' => 'pending',
            'active_status' => 'pending', 'otp' => 1234,
        ]);
        Parcelitem::forceCreate([
            'parcel_id' => $parcel->id, 'order_item_id' => $orderItem->id,
            'product_variant_id' => $variant->id, 'unit_price' => 50, 'quantity' => 2,
        ]);
        Auth::login($sellerUser);

        $response = app(SellerOrderController::class)->generatParcelInvoicePDF($parcel->id);
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content, 'generatParcelInvoicePDF() must produce a real PDF - templates/parcel_invoice.blade.php did not exist at all before this fix.');
    }
}
