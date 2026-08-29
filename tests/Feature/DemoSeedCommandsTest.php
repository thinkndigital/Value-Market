<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\StorageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the demo:create-seller/-affiliate/-delivery-boy commands (used to spin up working demo accounts
 * for trying out each dashboard) and the demo:seed-all orchestrator on top of them. Confirms each command
 * produces a real, immediately-usable account with a real uploaded image (not a broken/placeholder path) -
 * these commands are meant to be run against a live environment (including production, via a one-off Cloud
 * Run Job) so a silent schema mismatch here would only surface as a confusing failure at run time.
 */
class DemoSeedCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        StorageType::forceCreate(['name' => 'public', 'is_default' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
    }

    public function test_demo_create_seller_creates_a_working_seller_with_products_and_images(): void
    {
        $this->artisan('demo:create-seller', [
            '--username' => 'Test Demo Seller',
            '--mobile' => '9998880001',
            '--password' => 'Demo@12345',
            '--store-name' => 'Test Demo Store',
            '--email' => '',
        ])->assertExitCode(0);

        $user = User::where('mobile', '9998880001')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::SELLER, $user->role_id);
        $this->assertNotEmpty($user->image);
        $this->assertTrue(str_starts_with($user->image, 'http'), 'Avatar must be a real URL, not a bare filename.');

        $sellerStore = SellerStore::where('user_id', $user->id)->first();
        $this->assertNotNull($sellerStore);
        $this->assertSame(1, (int) $sellerStore->status);
        $this->assertNotEmpty($sellerStore->logo);
        $this->assertNotEmpty($sellerStore->store_thumbnail);

        $this->assertSame(1, Category::where('store_id', $sellerStore->store_id)->count());

        $products = Product::where('store_id', $sellerStore->store_id)->get();
        $this->assertCount(3, $products);
        foreach ($products as $product) {
            $this->assertNotEmpty($product->image);
            $this->assertSame(1, (int) $product->status);
            $this->assertSame(1, \App\Models\Product_variants::where('product_id', $product->id)->count(), 'Each demo product needs a variant so a real price/stock shows in the UI.');
            $this->assertSame(1, \App\Models\Product_attributes::where('product_id', $product->id)->count());
        }
    }

    public function test_demo_create_seller_rejects_a_duplicate_mobile(): void
    {
        $this->artisan('demo:create-seller', [
            '--username' => 'First',
            '--mobile' => '9998880002',
            '--password' => 'Demo@12345',
            '--email' => '',
        ])->assertExitCode(0);

        $this->artisan('demo:create-seller', [
            '--username' => 'Second',
            '--mobile' => '9998880002',
            '--password' => 'Demo@12345',
            '--email' => '',
        ])->assertExitCode(1);

        $this->assertSame(1, User::where('mobile', '9998880002')->count());
    }

    public function test_demo_create_affiliate_creates_a_customer_with_commission_data(): void
    {
        $this->artisan('demo:create-affiliate', [
            '--username' => 'Test Demo Affiliate',
            '--mobile' => '9998880003',
            '--password' => 'Demo@12345',
        ])->assertExitCode(0);

        $user = User::where('mobile', '9998880003')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::CUSTOMER, $user->role_id);
        $this->assertNotEmpty($user->image);

        $link = AffiliateLink::where('user_id', $user->id)->first();
        $this->assertNotNull($link);
        $this->assertSame(AffiliateLink::TARGET_PLATFORM, $link->target_type);

        $conversions = ReferralConversion::where('affiliate_link_id', $link->id)->get();
        $this->assertCount(3, $conversions);
        $this->assertSame(2, $conversions->where('status', ReferralConversion::STATUS_APPROVED)->count());
        $this->assertSame(1, $conversions->where('status', ReferralConversion::STATUS_PENDING)->count());
    }

    public function test_demo_create_seller_self_heals_when_no_default_storage_type_row_exists(): void
    {
        // Reproduces the real production failure: database/migrations/2025_02_02_000000_seed_default_storage_type.php
        // never ran there, so storage_types was empty and every upload crashed with "Call to a member
        // function addMedia() on null". GeneratesDemoImages::resolveDefaultStorageType() must recreate the
        // row itself rather than assume it's always present.
        StorageType::query()->delete();

        $this->artisan('demo:create-seller', [
            '--username' => 'Healed Seller',
            '--mobile' => '9998880005',
            '--password' => 'Demo@12345',
            '--email' => '',
            '--no-products' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, StorageType::where('is_default', 1)->count());

        $user = User::where('mobile', '9998880005')->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->image);
        $this->assertTrue(str_starts_with($user->image, 'http'));
    }

    public function test_demo_create_delivery_boy_creates_an_immediately_active_account(): void
    {
        $this->artisan('demo:create-delivery-boy', [
            '--username' => 'Test Demo Delivery Boy',
            '--mobile' => '9998880004',
            '--password' => 'Demo@12345',
        ])->assertExitCode(0);

        $user = User::where('mobile', '9998880004')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::DELIVERY_BOY, $user->role_id);
        $this->assertSame(1, (int) $user->status, 'status=1 is required for Admin\UserController::authenticate() to allow login.');
        $this->assertSame(1, (int) $user->is_available);
        $this->assertNotEmpty($user->image);
    }

    public function test_seed_all_creates_all_three_accounts_and_reports_login_urls(): void
    {
        $this->artisan('demo:seed-all', ['--password' => 'Demo@12345'])
            ->expectsTable(
                ['Panel', 'URL', 'Mobile', 'Password', 'Status'],
                [
                    ['Seller', url('/') . '/seller/login', '9990000001', 'Demo@12345', 'created'],
                    ['Affiliate', url('/') . '/affiliate/login', '9990000002', 'Demo@12345', 'created'],
                    ['Delivery Boy', url('/') . '/delivery_boy/login', '9990000003', 'Demo@12345', 'created'],
                ]
            )
            ->assertExitCode(0);

        $this->assertSame(3, User::whereIn('mobile', ['9990000001', '9990000002', '9990000003'])->count());
    }
}
