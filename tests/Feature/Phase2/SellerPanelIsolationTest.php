<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Seller\ComboProductController;
use App\Http\Controllers\Seller\MediaController;
use App\Http\Controllers\Seller\ProductController;
use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\Media;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\StorageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 2 (Task 16, seller isolation): docs/PHASE_2_MULTITENANCY.md §4 flagged several seller-panel tables
 * as "not independently audited" - auditing them this task surfaced confirmed IDORs beyond what
 * docs/PHASE_2_IDOR_AUDIT.md already covered (that pass fixed the *API* (v1\ApiController) versions of
 * delete/status-toggle for products and combo products; the *web-panel* controllers, Seller\ProductController
 * and Seller\ComboProductController, had the identical bug in their own destroy()/update_status() methods -
 * distinct methods, not yet fixed). Also found: Seller\MediaController::destroy() deleted any media row by
 * id with no check at all (already flagged, not fixed, in docs/PHASE_2_MULTITENANCY.md §4); and both
 * ComboProductController::edit()/show()-equivalents scoped only by store_id, not seller_id - a store can
 * host multiple sellers, so that let one seller view/edit another's listing.
 */
class SellerPanelIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);

        $seller = Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
        ]);

        return [$user, $seller];
    }

    private function makeProductOwnedBy(Seller $seller): Product
    {
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']),
            'slug' => 'cat-' . uniqid(),
            'image' => '',
            'banner' => '',
        ]);

        return Product::forceCreate([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
            'status' => 1,
        ]);
    }

    private function makeComboProductOwnedBy(Seller $seller): ComboProduct
    {
        return ComboProduct::forceCreate([
            'seller_id' => $seller->id,
            'title' => json_encode(['en' => 'Combo']),
            'deliverable_cities' => '',
            'status' => 1,
        ]);
    }

    // --- Seller\ProductController ---

    public function test_product_destroy_denies_a_non_owning_seller(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        app(ProductController::class)->destroy($product->id);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_destroy_allows_the_owning_seller(): void
    {
        [$ownerUser, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);
        Auth::login($ownerUser);

        app(ProductController::class)->destroy($product->id);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_update_status_does_not_change_another_sellers_product(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        app(ProductController::class)->update_status($product->id);

        $this->assertSame(1, $product->fresh()->status);
    }

    public function test_product_show_does_not_leak_another_sellers_product_in_the_same_store(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);
        $product->forceFill(['store_id' => 1])->save();

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);
        session(['store_id' => 1]);

        $view = app(ProductController::class)->show($product->id);

        $this->assertSame('admin.pages.views.no_data_found', $view->name());
    }

    public function test_product_update_denies_a_non_owning_seller_and_does_not_steal_ownership(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);

        [$attackerUser] = $this->makeSeller();
        Auth::login($attackerUser);

        $request = new Request(['pro_input_name' => 'Hijacked', 'short_description' => 'x', 'category_id' => $product->category_id]);
        $response = app(ProductController::class)->update($request, $product->id, true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame($owner->id, $product->fresh()->seller_id, 'Ownership must not be reassigned to the attacker.');
    }

    /**
     * Security fix (found while investigating docs/SECURITY_AUDIT.md §6.2's Model::unguard() deferral): the
     * ProductPolicy check in update() only gates WHICH product a seller may touch - it did not stop
     * seller_id (previously taken from request('seller_id')) from being written into that same,
     * already-owned product a few lines down, letting a seller who legitimately owns a product reassign it
     * to another seller's identity through the update form. Distinct from the test above (that attacker
     * never owned the product at all and is blocked by the Policy check before this ever mattered) - this
     * one owns the product and gets past the Policy check, so it's the update payload itself that must not
     * trust the request's seller_id.
     */
    public function test_product_update_does_not_let_the_owning_seller_reassign_seller_id(): void
    {
        [$ownerUser, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);
        [, $otherSeller] = $this->makeSeller();
        Auth::login($ownerUser);

        $request = new Request([
            'pro_input_name' => 'Updated', 'short_description' => 'x', 'category_id' => $product->category_id,
            'seller_id' => $otherSeller->id, // tries to claim a different seller's identity anyway
            'product_type' => 'simple_product', 'deliverable_type' => 1,
            'simple_price' => '10', 'simple_special_price' => '0', 'pro_input_image' => 'x.png',
        ]);
        $this->app->instance('request', $request);

        $response = app(ProductController::class)->update($request, $product->id, true);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame($owner->id, $product->fresh()->seller_id, 'A seller must not be able to reassign their own product to another seller.');
    }

    // --- Seller\ComboProductController ---

    public function test_combo_destroy_denies_a_non_owning_seller(): void
    {
        [, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        app(ComboProductController::class)->destroy($combo->id);

        $this->assertDatabaseHas('combo_products', ['id' => $combo->id]);
    }

    public function test_combo_destroy_allows_the_owning_seller(): void
    {
        [$ownerUser, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);
        Auth::login($ownerUser);

        app(ComboProductController::class)->destroy($combo->id);

        $this->assertDatabaseMissing('combo_products', ['id' => $combo->id]);
    }

    public function test_combo_update_status_does_not_change_another_sellers_combo(): void
    {
        [, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        try {
            app(ComboProductController::class)->update_status($combo->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // firstOrFail() throwing is also an acceptable denial - either way, status must be unchanged.
        }

        $this->assertSame(1, $combo->fresh()->status);
    }

    public function test_combo_edit_does_not_leak_another_sellers_combo_in_the_same_store(): void
    {
        [, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);
        $combo->forceFill(['store_id' => 1])->save();

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);
        session(['store_id' => 1]);

        $view = app(ComboProductController::class)->edit($combo->id);

        $this->assertSame('admin.pages.views.no_data_found', $view->name());
    }

    public function test_combo_update_denies_a_non_owning_seller_and_does_not_steal_ownership(): void
    {
        [, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);

        [$attackerUser, $attackerSeller] = $this->makeSeller();
        Auth::login($attackerUser);

        $request = new Request(['title' => 'Hijacked', 'short_description' => 'x', 'image' => 'x.jpg']);
        $response = app(ComboProductController::class)->update($request, $combo->id, true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame($owner->id, $combo->fresh()->seller_id, 'Ownership must not be reassigned to the attacker.');
    }

    // --- Seller\MediaController ---

    public function test_media_destroy_denies_a_non_owning_seller(): void
    {
        [, $owner] = $this->makeSeller();
        $storageType = StorageType::forceCreate(['name' => 'public', 'is_default' => 1]);
        $media = Media::forceCreate([
            'model_type' => StorageType::class,
            'model_id' => $storageType->id,
            'seller_id' => $owner->id,
            'collection_name' => 'media',
            'name' => 'file',
            'extension' => 'jpg',
            'type' => 'image',
            'sub_directory' => '/media',
            'file_name' => 'file.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => '1',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'manipulations' => '[]',
        ]);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        app(MediaController::class)->destroy($media->id);

        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
