<?php

namespace Tests\Feature\Phase2;

use App\Models\Area;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomMessage;
use App\Models\Currency;
use App\Models\Faq;
use App\Models\PickupLocation;
use App\Models\Promocode;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\StorageType;
use App\Models\Store;
use App\Models\Tax;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zipcode;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (32-phase SaaS brief), continuing docs/PHASE_2_ROUTE_SWEEP_REPORT.md's explicitly-deferred scope:
 * "routes requiring a URL parameter (107 total) are not swept — each needs a real, valid id for its
 * specific model, not a generic substitution." This is batch 1 of that sweep — the admin panel's simpler,
 * single-model CRUD resources (category/brand/blog/tax/zone/city/currency/promo_code/storage_type/faq/
 * zipcode/area/pickup_location/custom_message/ticket_type). Each gets one real seeded fixture and every
 * edit/destroy/update_status/view route for it is hit with that fixture's real id via the real HTTP kernel,
 * same "does it 500" methodology as the existing no-param RouteSweepTest.php - not a claim that the page's
 * form/business-logic is correct, just that it renders/executes without a server error. Products, combo
 * products, orders, sellers, attributes, and the seller/delivery_boy/affiliate param routes are a separate,
 * later batch - not attempted here (they need richer, multi-model fixtures - a product needs a category and
 * a seller, an order needs order items, etc.).
 */
class ParamRouteSweepBatch1Test extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
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

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    /** Routes already confirmed broken elsewhere and deferred - excluded so this batch stays a signal for *new* breakage in the routes it actually covers. */
    private const KNOWN_BROKEN_ROUTES = [];

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

    private array $failures = [];

    public function test_batch_1_admin_param_routes_render_without_a_server_error(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => 1, 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $brand = Brand::forceCreate(['name' => json_encode(['en' => 'Brand']), 'store_id' => 1, 'image' => '', 'slug' => 'brand-' . uniqid(), 'status' => 1]);
        $blogCategory = BlogCategory::forceCreate(['name' => json_encode(['en' => 'BlogCat']), 'store_id' => 1, 'slug' => 'blogcat-' . uniqid(), 'image' => '', 'status' => 1]);
        $blog = Blog::forceCreate(['title' => json_encode(['en' => 'Blog']), 'category_id' => $blogCategory->id, 'store_id' => 1, 'image' => '', 'description' => json_encode(['en' => 'x']), 'status' => 1, 'slug' => 'blog-' . uniqid()]);
        $tax = Tax::forceCreate(['title' => json_encode(['en' => 'VAT']), 'percentage' => 5]);
        $zone = Zone::forceCreate(['name' => json_encode(['en' => 'Zone']), 'status' => 1]);
        $city = City::forceCreate(['name' => json_encode(['en' => 'City']), 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);
        $zipcode = Zipcode::forceCreate(['zipcode' => '11937', 'city_id' => $city->id, 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);
        $area = Area::forceCreate(['name' => json_encode(['en' => 'Area']), 'city_id' => $city->id, 'zipcode_id' => $zipcode->id, 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);
        $currencyRow = Currency::forceCreate(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'exchange_rate' => 1, 'is_default' => 0, 'status' => 1]);
        $promo = Promocode::forceCreate(['title' => json_encode(['en' => 'Promo']), 'store_id' => 1, 'promo_code' => 'SAVE10', 'message' => json_encode(['en' => 'x']), 'start_date' => now(), 'end_date' => now()->addMonth(), 'discount' => 10, 'discount_type' => 'percentage', 'status' => 1]);
        $storageType = StorageType::forceCreate(['name' => 'local', 'is_default' => 0]);
        $faq = Faq::forceCreate(['question' => 'Q?', 'answer' => 'A.', 'status' => 1]);

        $sellerUser = User::forceCreate([
            'username' => 'sweep_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
        ]);

        $pickupLocation = PickupLocation::forceCreate([
            'seller_id' => $seller->id, 'pickup_location' => 'Main', 'name' => 'Main', 'email' => 'x@example.com',
            'phone' => '123', 'city' => 'City', 'country' => 'Country', 'state' => 'State', 'pincode' => '11937',
            'address' => 'Addr', 'status' => 1,
        ]);
        $customMessage = CustomMessage::forceCreate(['type' => 'x', 'title' => 'T', 'message' => 'M']);
        $ticketType = TicketType::forceCreate(['title' => 'General']);

        // Non-destructive routes (edit/view/update_status) for a resource always run before that
        // resource's own destroy route - hitting destroy first would delete the fixture out from under any
        // later route still expecting it to exist.
        $routes = [
            "/admin/categories/edit/{$category->id}", "/admin/categories/{$category->id}/edit",
            "/admin/admin/categories/update_status/{$category->id}",

            "/admin/brands/edit/{$brand->id}", "/admin/brand/update_status/{$brand->id}",

            "/admin/blogs/edit/{$blog->id}", "/admin/admin/blogs/update_status/{$blog->id}",

            "/admin/blog_category/edit/{$blogCategory->id}", "/admin/admin/blog_categories/update_status/{$blogCategory->id}",

            "/admin/tax/edit/{$tax->id}", "/admin/tax/update_status/{$tax->id}", "/admin/taxes/{$tax->id}/edit",

            "/admin/zones/edit/{$zone->id}", "/admin/zones/update_status/{$zone->id}", "/admin/zones/{$zone->id}/edit",

            "/admin/city/edit/{$city->id}",

            "/admin/currency/edit/{$currencyRow->id}", "/admin/currency/update_status/{$currencyRow->id}",

            "/admin/promo_code/update_status/{$promo->id}", "/admin/promo_codes/edit/{$promo->id}", "/admin/promo_codes/{$promo->id}/edit",

            "/admin/storage_type/edit/{$storageType->id}",

            "/admin/faq/edit/{$faq->id}", "/admin/faq/{$faq->id}/edit",

            "/admin/zipcodes/edit/{$zipcode->id}",

            "/admin/area/edit/{$area->id}",

            "/admin/pickup_location/edit/{$pickupLocation->id}", "/admin/pickup_location/update_status/{$pickupLocation->id}", "/admin/pickup_location/{$pickupLocation->id}/edit",

            "/admin/custom_message/edit/{$customMessage->id}", "/admin/custom_message/{$customMessage->id}",

            "/admin/ticket_types/edit/{$ticketType->id}",

            "/admin/sellers/edit/{$seller->id}", "/admin/sellers/{$seller->id}/edit",

            // Destroy routes last, one per resource. Blog destroyed before its category so the FK
            // dependency (blog.category_id) never dangles mid-sweep.
            "/admin/blogs/destroy/{$blog->id}",
            "/admin/blog_categories/destroy/{$blogCategory->id}",
            "/admin/categories/destroy/{$category->id}",
            "/admin/brands/destroy/{$brand->id}",
            "/admin/tax/destroy/{$tax->id}",
            "/admin/city/destroy/{$city->id}",
        ];

        foreach ($routes as $uri) {
            if (in_array($uri, self::KNOWN_BROKEN_ROUTES, true)) {
                continue;
            }
            $this->hit($uri);
        }

        $this->assertEmpty($this->failures, "Batch 1 admin param-route breakage (route => status/error):\n" . json_encode($this->failures, JSON_PRETTY_PRINT));
    }
}
