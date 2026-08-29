<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\StoreController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.1.1): "Stores without products are automatically hidden" and
 * "Categories without products are automatically hidden" were both confirmed genuinely missing - neither
 * Admin\StoreController::getStores() nor Admin\CategoryController::get_categories() had any product-count
 * filter.
 *
 * Both methods are shared by the CUSTOMER-facing app API (App\v1\ApiController) and the SELLER-facing app
 * API (Seller\v1\ApiController - a seller uses these same endpoints to see their own store, and to pick a
 * category when adding a product). Filtering unconditionally would have broken a seller's ability to see a
 * brand-new store or category before it has any products yet - a real regression, not a hypothetical, since
 * a seller must be able to add their FIRST product to an empty category/store. So the fix is opt-in: a new
 * trailing $onlyWithProducts parameter, defaulting to false (every existing caller's behavior is completely
 * unchanged), passed true only by the two customer-facing call sites in App\v1\ApiController.
 *
 * These tests exercise the shared controller methods directly (rather than the full HTTP route, which would
 * require replicating routes/api.php vs routes/seller_api.php's differing prefixes/middleware) since that's
 * precisely where the behavior actually lives and where the regression risk was.
 */
class HideEmptyStoresAndCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(int $storeId, bool $withProduct): Category
    {
        $category = Category::forceCreate([
            'store_id' => $storeId, 'name' => json_encode(['en' => 'Cat ' . uniqid()]),
            'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1, 'parent_id' => 0,
        ]);

        if ($withProduct) {
            Product::forceCreate([
                'category_id' => $category->id, 'seller_id' => 1, 'store_id' => $storeId,
                'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
                'image' => '', 'deliverable_cities' => '', 'status' => 1,
            ]);
        }

        return $category;
    }

    public function test_only_with_products_true_hides_an_empty_store_but_keeps_one_with_products(): void
    {
        $emptyStore = Store::forceCreate([
            'name' => json_encode(['en' => 'Empty Store']), 'slug' => 'empty-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $stockedStore = Store::forceCreate([
            'name' => json_encode(['en' => 'Stocked Store']), 'slug' => 'stocked-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $category = $this->makeCategory($stockedStore->id, false);
        Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => 1, 'store_id' => $stockedStore->id,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);

        $withFilter = app(StoreController::class)->getStores(null, null, 'id', 'DESC', null, "", "", true);
        $ids = collect($withFilter['data'])->pluck('id')->all();

        $this->assertNotContains($emptyStore->id, $ids, 'A store with zero active products must be hidden when onlyWithProducts=true.');
        $this->assertContains($stockedStore->id, $ids, 'A store with an active product must still be shown.');
    }

    public function test_only_with_products_false_still_shows_the_empty_store_for_the_seller_app(): void
    {
        $emptyStore = Store::forceCreate([
            'name' => json_encode(['en' => 'Empty Store']), 'slug' => 'empty-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);

        // Default (no 8th arg) - the shape every existing caller (including the seller app) already uses.
        $withoutFilter = app(StoreController::class)->getStores(null, null, 'id', 'DESC');
        $ids = collect($withoutFilter['data'])->pluck('id')->all();

        $this->assertContains($emptyStore->id, $ids, 'Without onlyWithProducts, a seller must still be able to see their own empty store.');
    }

    public function test_only_with_products_true_hides_an_empty_category_but_keeps_one_with_products(): void
    {
        $store = Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $emptyCategory = $this->makeCategory(1, false);
        $stockedCategory = $this->makeCategory(1, true);

        $result = app(CategoryController::class)->get_categories(null, '', '', 'row_order', 'ASC', 'true', '', '', '', 1, '', '', '', true);
        $ids = $result->original['categories']->pluck('id')->all();

        $this->assertNotContains($emptyCategory->id, $ids, 'A category with zero active products (and no populated subcategory) must be hidden when onlyWithProducts=true.');
        $this->assertContains($stockedCategory->id, $ids, 'A category with an active product must still be shown.');
    }

    public function test_only_with_products_true_keeps_a_parent_category_whose_child_has_products(): void
    {
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $parent = $this->makeCategory(1, false);
        $child = Category::forceCreate([
            'store_id' => 1, 'name' => json_encode(['en' => 'Child']), 'slug' => 'child-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1, 'parent_id' => $parent->id,
        ]);
        Product::forceCreate([
            'category_id' => $child->id, 'seller_id' => 1, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);

        $result = app(CategoryController::class)->get_categories(null, '', '', 'row_order', 'ASC', 'true', '', '', '', 1, '', '', '', true);
        $ids = $result->original['categories']->pluck('id')->all();

        $this->assertContains($parent->id, $ids, 'A parent category must stay visible when only its subcategory has products.');
    }

    public function test_only_with_products_false_still_shows_an_empty_category_for_the_seller_app(): void
    {
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $emptyCategory = $this->makeCategory(1, false);

        // Default (no trailing arg) - the shape every existing caller, including the seller's own product-
        // form category picker, already uses.
        $result = app(CategoryController::class)->get_categories(null, '', '', 'row_order', 'ASC', 'true', '', '', '', 1);
        $ids = $result->original['categories']->pluck('id')->all();

        $this->assertContains($emptyCategory->id, $ids, 'Without onlyWithProducts, a seller must still be able to pick a brand-new, empty category for their first product.');
    }
}
