<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesDemoImages;
use App\Models\AffiliateLink;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Any customer account can be an affiliate (AffiliateController::dashboard() auto-creates a platform-wide
 * link on first visit for whoever logs in via the affiliate portal - see docs/PHASE_7_AFFILIATE_ENGINE.md).
 * This creates that customer account directly, pre-seeded with a link and a couple of ReferralConversion
 * rows so the dashboard shows real approved/pending commission totals on first login instead of the
 * all-zeros a brand-new account would show.
 */
class CreateDemoAffiliate extends Command
{
    use GeneratesDemoImages;

    protected $signature = 'demo:create-affiliate
        {--username= : Display name for the account (default: Demo Affiliate)}
        {--mobile= : Login mobile number (must be unique). Prompted for if omitted and running interactively.}
        {--password= : Login password (min 8 characters). Prompted for if omitted and running interactively.}';

    protected $description = 'Create a demo affiliate account (a customer with a pre-seeded referral link and sample commission data) for browsing the affiliate portal.';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $username = $this->option('username') ?: ($interactive ? $this->ask('Username', 'Demo Affiliate') : 'Demo Affiliate');
        $mobile = $this->option('mobile') ?: ($interactive ? $this->ask('Mobile number (used to log in)') : null);
        $password = $this->option('password') ?: ($interactive ? $this->secret('Password (min 8 characters)') : null);

        if (!$mobile || !$password) {
            $this->error('--mobile and --password are required when running non-interactively (e.g. a Cloud Run Job).');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['username' => $username, 'mobile' => $mobile, 'password' => $password],
            ['username' => 'required|string', 'mobile' => 'required|string|unique:users,mobile', 'password' => 'required|string|min:8']
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Generating placeholder avatar image...');
        $avatarUrl = $this->uploadDemoImage($username, '#c026d3');

        [$user, $link] = DB::transaction(function () use ($username, $mobile, $password, $avatarUrl) {
            $user = User::forceCreate([
                'username' => $username,
                'mobile' => $mobile,
                'password' => Hash::make($password),
                'image' => $avatarUrl,
                'disk' => 'public',
                'serviceable_cities' => '',
                'type' => 'phone',
                'role_id' => Role::CUSTOMER,
                'active' => 1,
                'status' => 1,
                'balance' => 150.00, // demo commission already available to withdraw
            ]);

            $link = AffiliateLink::forceCreate([
                'user_id' => $user->id,
                'target_type' => AffiliateLink::TARGET_PLATFORM,
                'target_id' => null,
                'code' => Str::lower(Str::random(8)),
                'status' => AffiliateLink::STATUS_ACTIVE,
                'clicks_count' => 47,
                'conversions_count' => 3,
            ]);

            // Fabricated order ids (999001/999002/999003) - not real orders, purely so the dashboard's
            // approved/pending commission totals show non-zero numbers. Matches the fixture pattern
            // tests/Feature/AffiliatePortalTest.php already uses for the same purpose.
            ReferralConversion::forceCreate([
                'affiliate_link_id' => $link->id, 'order_id' => 999001, 'buyer_user_id' => null,
                'order_total' => 120.00, 'commission_rate_type' => 'percentage', 'commission_rate_value' => 5,
                'commission_amount' => 6.00, 'status' => ReferralConversion::STATUS_APPROVED,
            ]);
            ReferralConversion::forceCreate([
                'affiliate_link_id' => $link->id, 'order_id' => 999002, 'buyer_user_id' => null,
                'order_total' => 80.00, 'commission_rate_type' => 'percentage', 'commission_rate_value' => 5,
                'commission_amount' => 4.00, 'status' => ReferralConversion::STATUS_APPROVED,
            ]);
            ReferralConversion::forceCreate([
                'affiliate_link_id' => $link->id, 'order_id' => 999003, 'buyer_user_id' => null,
                'order_total' => 60.00, 'commission_rate_type' => 'percentage', 'commission_rate_value' => 5,
                'commission_amount' => 3.00, 'status' => ReferralConversion::STATUS_PENDING,
            ]);

            return [$user, $link];
        });

        $this->info("Demo affiliate created: {$user->username} (id {$user->id}), link code '{$link->code}'.");
        $this->info("Log in with mobile {$mobile} at /affiliate/login.");

        return self::SUCCESS;
    }
}
