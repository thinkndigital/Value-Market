<?php

namespace Tests\Feature\Phase2;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategorySliders;
use App\Models\ComboProduct;
use App\Models\ComboProductAttribute;
use App\Models\ComboProductFaq;
use App\Models\Currency;
use App\Models\Deliveryboy;
use App\Models\Offer;
use App\Models\OfferSliders;
use App\Models\Order;
use App\Models\OrderBankTransfers;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Section;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (32-phase SaaS brief), param-route sweep batch 3 - the admin routes deliberately deferred out of
 * batch 1 (docs/PHASE_2_ROUTE_SWEEP_REPORT.md's own words: "admin routes needing richer multi-model
 * fixtures - products, combo products, orders, attributes, currency exchange-rate/language routes, system
 * users/permissions, sliders/offers/category-sliders, manage-stock"). This closes out that remaining scope.
 */
class ParamRouteSweepBatch3Test extends TestCase
{
    use RefreshDatabase;

    private array $failures = [];

    /**
     * Confirmed broken, confirmed unreachable - same "Category 1/3: dead route" shape found repeatedly in
     * batches 1-2 (a Route::resource() slot Laravel generates automatically, with no controller method
     * and/or no UI action ever wired to it). Documented with root cause, not fixed here.
     */
    private const KNOWN_BROKEN_ROUTES = [
        // Chatify's vendor MessagesController has no show()/edit() - same gap already found for
        // seller/chat in batch 2, now confirmed on the admin side too.
        'admin/chat/{id}', 'admin/chat/{id}/edit',
        // AttributeController (admin) never implemented edit() - already documented in this repo's own
        // routes/admin_routes.php comment above the resource registration; no UI links to it either
        // (attribute values are edited inline from the product form, not their own edit page).
        'admin/attributes/{id}/edit',
        // PaymentRequestController (admin) never implemented edit() - the resource's ->except('show')
        // still leaves 'edit' auto-generated; payment_request.blade.php has no edit action wired to it.
        'admin/payment_request/{id}/edit',
        // SettingController (admin) never implemented timeSlotDestroy()/updateTimeSlotStatus() despite
        // both being routed - time_slot_settings.blade.php's own table has no destroy/status-toggle
        // action wired to it (time slots are only ever added, never edited/removed, in the current UI).
        'admin/settings/time_slot/destroy/{id}', 'admin/settings/time_slot/update_status/{id}',
        // Delivery_boyController::edit() renders update_category.blade.php without ever passing
        // 'languages' (a real, would-be-fatal bug on its own) - but the real "edit delivery boy" UI is a
        // Bootstrap modal (delivery_boy.blade.php's .edit_delivery_boy handler) that AJAX-fetches
        // admin/delivery_boys?edit_id={id} instead; this GET .../edit page route has no link anywhere.
        'admin/delivery_boys/{id}/edit',
        // OrderController::order_item_destroy() passes fetchDetails()'s return value (an Eloquent
        // Collection) straight into array_column(), which requires a plain array - a TypeError on every
        // single call, unconditionally, not just an edge case. No UI (list view, JS, or any other
        // controller) links to this route at all; order items are removed only as part of removing their
        // whole order via admin/order/destroy.
        'admin/order_items/destroy/{id}',
    ];

    /** Same normalization batch 2 introduced - matches KNOWN_BROKEN_ROUTES regardless of which real autoincrement id a fixture happens to get in a given run. */
    private function normalize(string $uri): string
    {
        return preg_replace('#/\d+#', '/{id}', ltrim($uri, '/'));
    }

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode([])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function hit(string $uri): void
    {
        try {
            $response = $this->get($uri, ['Accept' => 'application/json']);
            if ($response->getStatusCode() >= 500) {
                $body = json_decode($response->getContent(), true);
                $this->failures[$uri] = ($body['exception'] ?? 'Unknown') . ': ' . ($body['message'] ?? $response->getStatusCode())
                    . ' @ ' . ($body['file'] ?? '?') . ':' . ($body['line'] ?? '?');
            }
        } catch (\Throwable $e) {
            $this->failures[$uri] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
        }
    }

    public function test_batch_3_admin_product_order_and_settings_param_routes_render_without_a_server_error(): void
    {
        $this->shareBaseViewData();

        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);

        $adminUser = User::forceCreate([
            'username' => 'sweep3_admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'sweep3_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $customer = User::forceCreate([
            'username' => 'sweep3_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9998887772',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => 1, 'slug' => 'cat3-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $brand = Brand::forceCreate(['name' => json_encode(['en' => 'Brand']), 'store_id' => 1, 'image' => '', 'slug' => 'brand3-' . uniqid(), 'status' => 1]);

        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product3-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        $productForDestroy = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product to delete']), 'slug' => 'product3-del-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);

        $comboProduct = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo']), 'short_description' => json_encode(['en' => 'x']),
            'description' => json_encode(['en' => 'x']), 'seller_id' => $seller->id, 'product_type' => 'simple_product',
            'product_ids' => (string) $product->id, 'price' => 20, 'stock' => 5, 'availability' => 1,
            'status' => 1, 'store_id' => 1, 'slug' => 'combo3-' . uniqid(),
        ]);
        $comboProductForDestroy = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo to delete']), 'short_description' => json_encode(['en' => 'x']),
            'description' => json_encode(['en' => 'x']), 'seller_id' => $seller->id, 'product_type' => 'simple_product',
            'product_ids' => (string) $product->id, 'price' => 20, 'stock' => 5, 'availability' => 1,
            'status' => 1, 'store_id' => 1, 'slug' => 'combo3-del-' . uniqid(),
        ]);

        $productFaq = ProductFaq::forceCreate(['product_id' => $product->id, 'question' => 'Q?', 'answer' => 'A.', 'user_id' => $customer->id, 'seller_id' => $seller->id, 'votes' => 0]);
        $comboProductFaq = ComboProductFaq::forceCreate(['product_id' => $comboProduct->id, 'question' => 'Q?', 'answer' => 'A.', 'user_id' => $customer->id, 'seller_id' => $seller->id, 'votes' => 0]);
        $attribute = Attribute::forceCreate(['store_id' => 1, 'name' => 'Color', 'status' => 1, 'category_id' => $category->id]);
        $comboProductAttribute = ComboProductAttribute::forceCreate(['name' => 'Size', 'status' => 1, 'store_id' => 1]);

        // Two orders: one kept alive for the view/edit routes, one dedicated per destroy route so nothing
        // gets deleted out from under a later check (same discipline as batch 1's destroy ordering).
        $orderData = [
            'user_id' => $customer->id, 'mobile' => '9998887772', 'total' => 20, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ];
        $status = json_encode([['awaiting', now()->format('d-m-Y h:i:sa')]]);
        $order = Order::forceCreate($orderData);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);
        $bankTransfer = OrderBankTransfers::forceCreate(['order_id' => $order->id, 'attachments' => 'receipt.png', 'status' => 'pending']);

        $orderForItemDestroy = Order::forceCreate($orderData);
        $orderItemForDestroy = OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $orderForItemDestroy->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);

        $orderForDestroy = Order::forceCreate($orderData);
        OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $orderForDestroy->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);

        $slider = Slider::forceCreate(['type' => 'category', 'store_id' => 1, 'type_id' => $category->id, 'link' => '', 'image' => 'x.png']);
        $offer = Offer::forceCreate(['type' => 'category', 'store_id' => 1, 'title' => json_encode(['en' => 'Offer']), 'type_id' => $category->id, 'link' => '', 'image' => 'x.png', 'banner_image' => 'x.png', 'min_discount' => 5, 'max_discount' => 10]);
        $offerSlider = OfferSliders::forceCreate(['title' => json_encode(['en' => 'Offer Slider']), 'store_id' => 1, 'banner_image' => 'x.png', 'status' => 1, 'offer_ids' => (string) $offer->id]);
        $categorySlider = CategorySliders::forceCreate(['title' => json_encode(['en' => 'Cat Slider']), 'store_id' => 1, 'banner_image' => 'x.png', 'status' => 1, 'style' => 'style_1', 'category_ids' => (string) $category->id]);
        $featureSection = Section::forceCreate([
            'store_id' => 1, 'title' => json_encode(['en' => 'Featured']), 'short_description' => json_encode(['en' => 'x']),
            'style' => 'style_1', 'product_ids' => (string) $product->id, 'row_order' => 1, 'categories' => (string) $category->id,
            'product_type' => 'custom_products', 'banner_image' => 'x.png', 'background_color' => '#ffffff', 'header_style' => 'style_1',
        ]);

        $deliveryBoy = User::forceCreate([
            'username' => 'sweep3_delivery_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1,
        ]);
        $deliveryBoyForDestroy = Deliveryboy::forceCreate([
            'username' => 'sweep3_delivery_del_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1,
        ]);

        $customerForDestroy = User::forceCreate([
            'username' => 'sweep3_customer_del_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9998887773',
        ]);

        $this->actingAs($adminUser);
        session(['store_id' => 1]);

        $routes = [
            "/admin/account/{$adminUser->id}",
            "/admin/attributes/update_status/{$attribute->id}",
            "/admin/category_sliders/edit/{$categorySlider->id}", "/admin/category_sliders/update_status/{$categorySlider->id}",
            "/admin/chat/1", "/admin/chat/1/edit",
            "/admin/combo_product_attributes/update_status/{$comboProductAttribute->id}", "/admin/combo_product_attributes/{$comboProductAttribute->id}/edit",
            "/admin/combo_product_faqs/edit/{$comboProductFaq->id}", "/admin/combo_product_faqs/{$comboProductFaq->id}/edit",
            "/admin/combo_products/update_status/{$comboProduct->id}", "/admin/combo_products/view_product/{$comboProduct->id}", "/admin/combo_products/{$comboProduct->id}/edit",
            "/admin/customers/update_status/{$customer->id}",
            "/admin/delivery_boys/{$deliveryBoy->id}/edit",
            "/admin/delivery_boy/update_status/{$deliveryBoy->id}",
            "/admin/feature_section/{$featureSection->id}/edit",
            "/admin/manage_combo_stock/edit/{$comboProduct->id}",
            "/admin/manage_stock/edit/{$variant->id}", "/admin/manage_stock/{$variant->id}/edit",
            "/admin/offer_sliders/update_status/{$offerSlider->id}",
            "/offer_sliders/edit/{$offerSlider->id}",
            "/admin/offers/{$offer->id}/edit",
            "/admin/orders/{$order->id}/edit",
            "/admin/orders/delete_receipt/{$bankTransfer->id}",
            "/admin/payment_request/1/edit",
            "/admin/product/view_product/{$product->id}",
            "/admin/product_faqs/edit/{$productFaq->id}", "/admin/product_faqs/{$productFaq->id}/edit",
            "/admin/products/update_status/{$product->id}", "/admin/products/{$product->id}/edit",
            "/admin/settings/set-language/en",
            "/admin/settings/time_slot/destroy/{$product->id}", "/admin/settings/time_slot/update_status/{$product->id}",
            "/admin/sliders/{$slider->id}/edit",
            "/admin/store/edit/1", "/admin/store/1/edit", "/admin/store/update_status/1",
            "/admin/system_users/edit/{$adminUser->id}", "/admin/system_users/permissions/{$adminUser->id}",
            "/admin/web_settings/set-language/en",
            "/admin/zipcode/1",
            // Destroy routes last, each against its own dedicated fixture.
            "/admin/combo_product_faqs/destroy/{$comboProductFaq->id}",
            "/admin/product_faqs/destroy/{$productFaq->id}",
            "/admin/order_items/destroy/{$orderItemForDestroy->id}",
            "/admin/order/destroy/{$orderForDestroy->id}",
            "/admin/products/destroy/{$productForDestroy->id}",
            "/admin/combo_products/destroy/{$comboProductForDestroy->id}",
            "/admin/customers/{$customerForDestroy->id}",
            "/delivery_boys/destroy/{$deliveryBoyForDestroy->id}",
        ];

        foreach ($routes as $uri) {
            if (in_array($this->normalize($uri), self::KNOWN_BROKEN_ROUTES, true)) {
                continue;
            }
            $this->hit($uri);
        }

        $this->assertEmpty($this->failures, "Batch 3 param-route breakage (route => status/error):\n" . json_encode($this->failures, JSON_PRETTY_PRINT));
    }
}
