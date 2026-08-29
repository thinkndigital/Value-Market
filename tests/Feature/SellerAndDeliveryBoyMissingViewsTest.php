<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Same audit as AdminMissingViewsTest, run against the Seller and Delivery_boy controllers: 9 more seller
 * pages and 2 delivery-boy pages whose sidebar links, routes, and controller methods all exist but whose
 * Blade view was never created (the delivery_boy.pages.tables directory was entirely empty - these were the
 * only two table-shaped pages that panel ever had). Each modeled on the closest existing working page of the
 * same shape in its own panel (manage_stock.blade.php for the two stock/deliverability pages,
 * combo_product_faqs.blade.php's admin counterpart for the seller FAQ page, etc.) - never the admin panel's
 * markup copied verbatim, since seller/delivery_boy use a different layout and breadcrumb component.
 */
class SellerAndDeliveryBoyMissingViewsTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);

        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeSeller(): User
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1, 'status' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        return $sellerUser;
    }

    private function makeDeliveryBoy(): User
    {
        return User::forceCreate([
            'username' => 'delivery_boy_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1,
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function previouslyMissingSellerRoutesProvider(): array
    {
        return [
            'product bulk upload form' => ['seller.product_bulk_upload'],
            'combo product bulk upload form' => ['seller.combo.product.bulk_upload'],
            'translation bulk upload form' => ['seller.translation_bulk_upload.index'],
            'combo product faqs table' => ['seller.combo_product_faqs.index'],
            'manage combo product deliverability table' => ['seller.manage_combo_product_deliverability.index'],
            'manage combo products table' => ['seller.combo_products.manage_product'],
            'manage combo stock table' => ['seller.manage_combo_stock.index'],
            'manage product deliverability table' => ['seller.manage_product_deliverability.index'],
            'wallet transactions table' => ['seller.transaction.wallet_transactions'],
        ];
    }

    #[DataProvider('previouslyMissingSellerRoutesProvider')]
    public function test_seller_page_renders_instead_of_500(string $routeName): void
    {
        $this->shareBaseViewData();
        $seller = $this->makeSeller();

        $response = $this->actingAs($seller)->get(route($routeName));

        $response->assertOk();
    }

    /** @return array<string, array{0: string}> */
    public static function previouslyMissingDeliveryBoyRoutesProvider(): array
    {
        return [
            'fund transfer table' => ['delivery_boy.fund.transfer'],
            'returned orders table' => ['delivery_boy.cash.returned_order'],
        ];
    }

    #[DataProvider('previouslyMissingDeliveryBoyRoutesProvider')]
    public function test_delivery_boy_page_renders_instead_of_500(string $routeName): void
    {
        $this->shareBaseViewData();
        $deliveryBoy = $this->makeDeliveryBoy();

        $response = $this->actingAs($deliveryBoy)->get(route($routeName));

        $response->assertOk();
    }
}
