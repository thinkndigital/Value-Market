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
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super-admin
        {--username= : Display name for the account}
        {--mobile= : Login mobile number (must be unique)}
        {--email= : Optional email address}
        {--password= : Login password (min 8 characters). Prompted for if omitted.}';

    protected $description = 'Create a super_admin user - the first-run bootstrap for a fresh install with no admin account yet.';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Username');
        $mobile = $this->option('mobile') ?: $this->ask('Mobile number (used to log in)');
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
