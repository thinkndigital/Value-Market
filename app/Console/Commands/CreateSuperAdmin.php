<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * There's no signup flow for the very first admin account - the admin panel is only reachable by someone
 * who's already logged in as super_admin/admin/editor - so a fresh install with an empty `users` table has
 * no way in at all. This is that way in: creates a super_admin user directly, prompting for anything not
 * passed as an option so it works both interactively and non-interactively (a one-off Cloud Run job/exec,
 * a deploy script).
 *
 * Also handles the second real-world case this bootstrap needs to cover: promoting an existing account
 * (already registered as a regular customer/seller through the storefront, e.g. by mobile number) to
 * super_admin, rather than only ever creating a brand-new one - `--mobile` matching an existing user
 * promotes that user in place (optionally resetting their password if `--password` is also given) instead
 * of failing on the mobile-uniqueness check a create would hit.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super-admin
        {--username= : Display name for the account (only used when creating a new account)}
        {--mobile= : Login mobile number. If an account with this mobile already exists, it is promoted to super_admin instead of creating a new one.}
        {--email= : Optional email address (only used when creating a new account)}
        {--password= : Login password (min 8 characters). For an existing account, resets its password; omit to leave the existing password untouched. Prompted for if omitted when creating a new account.}';

    protected $description = 'Create a super_admin user, or promote an existing account (by mobile) to super_admin - the first-run bootstrap for a fresh install, and the way to grant super_admin to an already-registered account.';

    public function handle(): int
    {
        $mobile = $this->option('mobile') ?: $this->ask('Mobile number (used to log in)');

        $existing = User::where('mobile', $mobile)->first();

        if ($existing) {
            return $this->promote($existing);
        }

        return $this->create($mobile);
    }

    private function promote(User $user): int
    {
        $password = $this->option('password');

        if ($password !== null) {
            $validator = Validator::make(['password' => $password], ['password' => 'required|string|min:8']);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $user->password = Hash::make($password);
        }

        $user->role_id = Role::SUPER_ADMIN;
        $user->active = 1;
        $user->status = 1;
        $user->save();

        $this->info("Existing account promoted to super admin: {$user->username} (id {$user->id}). Log in with mobile {$user->mobile} at /admin/login." . ($password !== null ? ' Password was reset.' : ' Existing password unchanged.'));

        return self::SUCCESS;
    }

    private function create(string $mobile): int
    {
        $username = $this->option('username') ?: $this->ask('Username');
        $email = $this->option('email') ?: $this->ask('Email (optional)', '');
        $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');

        $validator = Validator::make(
            [
                'username' => $username,
                'mobile' => $mobile,
                'email' => $email,
                'password' => $password,
            ],
            [
                'username' => 'required|string',
                'mobile' => 'required|string|unique:users,mobile',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::forceCreate([
            'username' => $username,
            'mobile' => $mobile,
            'email' => $email ?: null,
            'password' => Hash::make($password),
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SUPER_ADMIN,
            'active' => 1,
            'status' => 1,
        ]);

        $this->info("Super admin created: {$user->username} (id {$user->id}). Log in with mobile {$mobile} at /admin/login.");

        return self::SUCCESS;
    }
}
