<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesDemoImages;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Admin\UserController::authenticate() requires a delivery boy account's users.status = 1 to log in at all
 * (a separate gate from the generic users.active column - see that method's role-branch for delivery_boy).
 * Creates that account directly with status/active/is_available all already set, so the account is usable
 * the moment this command finishes - no separate admin-approval step needed.
 *
 * Deliberately does NOT fabricate any Order/Parcel/delivery-earnings rows for this account: unlike the demo
 * seller's products (which have no revenue/reporting side effects on their own), synthetic orders would
 * show up in admin's real order counts, revenue reports, and seller payout calculations - a real risk on a
 * production database. The delivery boy's own dashboard (order counters, cash collection, wallet) will
 * correctly show zeros until it's actually assigned a real order, exactly as a newly-onboarded delivery boy
 * would see.
 */
class CreateDemoDeliveryBoy extends Command
{
    use GeneratesDemoImages;

    protected $signature = 'demo:create-delivery-boy
        {--username= : Display name for the account (default: Demo Delivery Boy)}
        {--mobile= : Login mobile number (must be unique). Prompted for if omitted and running interactively.}
        {--password= : Login password (min 8 characters). Prompted for if omitted and running interactively.}';

    protected $description = 'Create a demo delivery boy account (active and ready to log in) for browsing the delivery boy panel.';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $username = $this->option('username') ?: ($interactive ? $this->ask('Username', 'Demo Delivery Boy') : 'Demo Delivery Boy');
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
        $avatarUrl = $this->uploadDemoImage($username, '#059669');

        $user = DB::transaction(function () use ($username, $mobile, $password, $avatarUrl) {
            return User::forceCreate([
                'username' => $username,
                'mobile' => $mobile,
                'password' => Hash::make($password),
                'image' => $avatarUrl,
                'disk' => 'public',
                'serviceable_cities' => '',
                'serviceable_zones' => '',
                'type' => 'phone',
                'role_id' => Role::DELIVERY_BOY,
                'active' => 1,
                'status' => 1, // required for Admin\UserController::authenticate() to allow login
                'is_available' => 1,
                'cash_received' => 0,
                'balance' => 0,
            ]);
        });

        $this->info("Demo delivery boy created: {$user->username} (id {$user->id}).");
        $this->info("Log in with mobile {$mobile} at /delivery_boy/login.");

        return self::SUCCESS;
    }
}
