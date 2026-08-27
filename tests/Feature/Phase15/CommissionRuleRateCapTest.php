<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Admin\CommissionRuleController;
use App\Models\CommissionRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 10): a percentage commission rate had no
 * upper bound - a fat-fingered or malicious admin setting e.g. 1000 would auto-pay 10x order value on every
 * affiliate conversion platform-wide the moment the rule went live. A flat rate has no equivalent natural
 * cap (it's a fixed currency amount, not a fraction of the order) and is left unbounded, same as before.
 */
class CommissionRuleRateCapTest extends TestCase
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

    public function test_a_percentage_rate_over_100_is_rejected_on_create(): void
    {
        $this->loginAdmin();

        $response = app(CommissionRuleController::class)->store(new Request([
            'scope' => CommissionRule::SCOPE_PLATFORM,
            'rate_type' => 'percentage',
            'rate_value' => 1000,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, CommissionRule::count());
    }

    public function test_a_percentage_rate_of_exactly_100_is_allowed_on_create(): void
    {
        $this->loginAdmin();

        $response = app(CommissionRuleController::class)->store(new Request([
            'scope' => CommissionRule::SCOPE_PLATFORM,
            'rate_type' => 'percentage',
            'rate_value' => 100,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame(1, CommissionRule::count());
    }

    public function test_a_flat_rate_over_100_is_still_allowed_on_create(): void
    {
        $this->loginAdmin();

        $response = app(CommissionRuleController::class)->store(new Request([
            'scope' => CommissionRule::SCOPE_PLATFORM,
            'rate_type' => 'flat',
            'rate_value' => 500,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
    }

    public function test_updating_rate_value_past_100_on_an_existing_percentage_rule_is_rejected(): void
    {
        $this->loginAdmin();
        $rule = CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 5, 'status' => 1]);

        $response = app(CommissionRuleController::class)->update(new Request(['rate_value' => 250]), $rule->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(5.0, (float) $rule->fresh()->rate_value);
    }

    public function test_switching_an_existing_rule_to_percentage_with_an_over_100_value_is_rejected(): void
    {
        $this->loginAdmin();
        $rule = CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 500, 'status' => 1]);

        $response = app(CommissionRuleController::class)->update(new Request(['rate_type' => 'percentage']), $rule->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('flat', $rule->fresh()->rate_type);
    }
}
