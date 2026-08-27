<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Admin\CommissionRuleController;
use App\Models\CommissionRule;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase 15 (docs/SECURITY_AUDIT.md §6): extends Phase 2's auditLog() (app/function_helper.php) to two more
 * high-value events found in the Phase 4-14 work that had no built-in history of their own - a new
 * login-capable employee account, and a commission rate change that silently affects every future affiliate
 * payout platform-wide. Deliberately NOT extended to every money-moving call (WalletService, PartnerService,
 * LiabilityService, ...) - those already persist a full record in transactions/journal_entries/
 * partner_transactions, so a duplicate text-log entry would be redundant, not additive.
 */
class AuditLogExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_creation_is_logged(): void
    {
        $ownerUser = User::forceCreate([
            'username' => 'owner_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $ownerUser->id, 'disk' => 'public', 'status' => 1]);
        Auth::login($ownerUser);

        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('employee.created', \Mockery::on(fn ($context) => $context['seller_id'] === $seller->id));

        app(EmployeeService::class)->create($seller->id, [
            'name' => 'New Employee',
            'mobile' => (string) random_int(6000000000, 6999999999),
            'password' => 'password123',
        ]);
    }

    public function test_commission_rule_creation_is_logged(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);

        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('commission_rule.created', \Mockery::on(fn ($context) => $context['rate_value'] === 5.0));

        app(CommissionRuleController::class)->store(new Request([
            'scope' => CommissionRule::SCOPE_PLATFORM,
            'rate_type' => 'percentage',
            'rate_value' => 5,
        ]));
    }

    public function test_commission_rule_update_logs_before_and_after_values(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        $rule = CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 5, 'status' => 1]);
        Auth::login($admin);

        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('commission_rule.updated', \Mockery::on(function ($context) {
                return $context['before']['rate_value'] === 5.0 && $context['after']['rate_value'] === 8.0;
            }));

        app(CommissionRuleController::class)->update(new Request(['rate_value' => 8]), $rule->id);
    }
}
