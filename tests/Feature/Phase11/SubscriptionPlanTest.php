<?php

namespace Tests\Feature\Phase11;

use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 11 (32-phase SaaS brief, docs/PHASE_11_SUBSCRIPTIONS.md): the product owner's decision was
 * subscription + commission together, with the exact tier names/prices/limits left as an open blocker -
 * resolved as "seed real placeholder defaults (Basic/Pro/Premium), let the admin control them" rather than
 * this pass guessing real business pricing. Mirrors this app's established admin-CRUD test pattern
 * (tests/Feature/Phase15/CommissionRuleRateCapTest.php) - calling the controller directly.
 */
class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);
    }

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
    }

    public function test_the_migration_seeds_three_placeholder_default_plans(): void
    {
        $this->assertSame(3, SubscriptionPlan::count());
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'basic']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'pro']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'premium']);
    }

    public function test_an_admin_can_create_a_new_plan(): void
    {
        $this->loginAdmin();

        $response = app(SubscriptionPlanController::class)->store(new Request([
            'name' => 'Enterprise', 'billing_cycle' => 'yearly', 'price' => 999,
            'commission_rate' => 2.5, 'max_products' => null,
            'features' => ['Dedicated support', 'Custom limits'],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertDatabaseHas('subscription_plans', ['name' => 'Enterprise', 'slug' => 'enterprise']);
        $plan = SubscriptionPlan::where('slug', 'enterprise')->first();
        $this->assertSame(['Dedicated support', 'Custom limits'], $plan->features);
    }

    public function test_a_commission_rate_over_100_is_rejected(): void
    {
        $this->loginAdmin();

        $response = app(SubscriptionPlanController::class)->store(new Request([
            'name' => 'Broken', 'billing_cycle' => 'monthly', 'price' => 10, 'commission_rate' => 250,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('subscription_plans', ['name' => 'Broken']);
    }

    public function test_an_admin_can_update_a_plans_price_and_status(): void
    {
        $this->loginAdmin();
        $plan = SubscriptionPlan::where('slug', 'basic')->first();

        $response = app(SubscriptionPlanController::class)->update(new Request(['price' => 19.99, 'status' => 0]), $plan->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame('19.99', $plan->fresh()->price);
        $this->assertFalse((bool) $plan->fresh()->status);
    }

    public function test_a_plan_with_no_sellers_can_be_deleted(): void
    {
        $this->loginAdmin();
        $plan = SubscriptionPlan::forceCreate(['name' => 'Temp', 'slug' => 'temp', 'billing_cycle' => 'monthly', 'price' => 5, 'status' => 1]);

        $response = app(SubscriptionPlanController::class)->destroy($plan->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }

    public function test_a_plan_with_an_assigned_seller_cannot_be_deleted(): void
    {
        $this->loginAdmin();
        $plan = SubscriptionPlan::where('slug', 'pro')->first();
        $seller = $this->makeSeller();
        $seller->update(['subscription_plan_id' => $plan->id]);

        $response = app(SubscriptionPlanController::class)->destroy($plan->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
    }

    public function test_an_admin_can_assign_a_plan_to_a_seller_and_it_sets_an_expiry(): void
    {
        $this->loginAdmin();
        $plan = SubscriptionPlan::where('slug', 'pro')->first();
        $seller = $this->makeSeller();

        $response = app(SubscriptionPlanController::class)->assignToSeller(new Request([
            'seller_id' => $seller->id, 'plan_id' => $plan->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $fresh = $seller->fresh();
        $this->assertSame($plan->id, $fresh->subscription_plan_id);
        $this->assertNotNull($fresh->subscription_started_at);
        $this->assertNotNull($fresh->subscription_expires_at);
    }

    public function test_an_admin_can_clear_a_sellers_subscription(): void
    {
        $this->loginAdmin();
        $plan = SubscriptionPlan::where('slug', 'basic')->first();
        $seller = $this->makeSeller();
        $seller->update(['subscription_plan_id' => $plan->id, 'subscription_started_at' => now(), 'subscription_expires_at' => now()->addMonth()]);

        $response = app(SubscriptionPlanController::class)->assignToSeller(new Request([
            'seller_id' => $seller->id, 'plan_id' => null,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $fresh = $seller->fresh();
        $this->assertNull($fresh->subscription_plan_id);
        $this->assertNull($fresh->subscription_expires_at);
    }

    public function test_the_seller_relation_resolves_the_assigned_plan(): void
    {
        $plan = SubscriptionPlan::where('slug', 'premium')->first();
        $seller = $this->makeSeller();
        $seller->update(['subscription_plan_id' => $plan->id]);

        $this->assertSame('Premium', $seller->fresh()->subscriptionPlan->name);
    }
}
