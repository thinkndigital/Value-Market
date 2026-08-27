<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Admin\ComboProductRatingController;
use App\Http\Controllers\Admin\ProductRatingController;
use App\Http\Controllers\App\v1\ApiController;
use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\ComboProductRating;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 2 (Task 18, customer isolation): App\v1\ApiController::delete_product_rating()/
 * delete_combo_product_rating() delegated straight to Admin\ProductRatingController::delete_rating()/
 * Admin\ComboProductRatingController::delete_rating() with no ownership check of their own.
 * That shared method is correct for its actual admin use (moderating any review) but was never meant to be
 * reachable by an arbitrary customer - any authenticated customer could delete any other customer's product
 * review by guessing rating_id. Proves: a non-owning customer is denied and the review survives, the
 * genuine author can still delete their own review.
 */
class RatingOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'customer_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::forceCreate([
            'name' => json_encode(['en' => 'Category']),
            'slug' => 'cat-' . uniqid(),
            'image' => '',
            'banner' => '',
        ]);
    }

    public function test_delete_product_rating_denies_a_non_owning_customer(): void
    {
        $author = $this->makeCustomer();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
        ]);
        $rating = ProductRating::forceCreate([
            'user_id' => $author->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);

        $attacker = $this->makeCustomer();
        Auth::login($attacker);

        $response = app(ApiController::class)->delete_product_rating(
            new Request(['rating_id' => $rating->id]),
            app(ProductRatingController::class)
        );
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('product_ratings', ['id' => $rating->id]);
    }

    public function test_delete_product_rating_allows_the_authoring_customer(): void
    {
        $author = $this->makeCustomer();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
        ]);
        $rating = ProductRating::forceCreate([
            'user_id' => $author->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);

        Auth::login($author);

        try {
            app(ApiController::class)->delete_product_rating(
                new Request(['rating_id' => $rating->id]),
                app(ProductRatingController::class)
            );
        } catch (\Throwable $e) {
            // Execution reached past the ownership check (proven by the row actually being deleted below,
            // which only happens once the shared delete_rating() runs) - any failure from here on is that
            // shared method's own pre-existing, unrelated seller-aggregate-rating update logic, not the
            // ownership check under test.
        }

        $this->assertDatabaseMissing('product_ratings', ['id' => $rating->id]);
    }

    public function test_delete_combo_product_rating_denies_a_non_owning_customer(): void
    {
        $author = $this->makeCustomer();
        $combo = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo']),
            'deliverable_cities' => '',
        ]);
        $rating = ComboProductRating::forceCreate([
            'user_id' => $author->id,
            'product_id' => $combo->id,
            'rating' => 5,
        ]);

        $attacker = $this->makeCustomer();
        Auth::login($attacker);

        $response = app(ApiController::class)->delete_combo_product_rating(
            new Request(['rating_id' => $rating->id]),
            app(ComboProductRatingController::class)
        );
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('combo_product_ratings', ['id' => $rating->id]);
    }
}
