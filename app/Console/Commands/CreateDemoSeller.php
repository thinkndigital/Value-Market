<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * The web Add-Store form (Admin\StoreController::store()) and seller self-registration both require real
 * uploaded images and a full set of theme-color/delivery fields before a store exists at all, which makes
 * them the wrong tool for just wanting a working seller panel to browse. This creates a User (role seller),
 * an already-approved seller_data row, a Store, and the seller_store link between them directly - the same
 * end state a completed registration would reach, without needing the web forms.
 */
class CreateDemoSeller extends Command
{
    protected $signature = 'demo:create-seller
        {--username= : Display name for the account (default: Demo Seller)}
        {--mobile= : Login mobile number (must be unique). Prompted for if omitted and running interactively.}
        {--email= : Optional email address}
        {--password= : Login password (min 8 characters). Prompted for if omitted and running interactively.}
        {--store-name= : Store display name (default: Demo Store)}';

    protected $description = 'Create a demo seller account with an already-approved store, so the seller panel can be logged into and browsed without the web registration forms.';

    public function handle(): int
    {
        // Cloud Run Jobs execute with no attached stdin, so this only prompts when actually running
        // interactively (a local/exec shell) - falling back to $this->ask() unconditionally would hang (or
        // fail on EOF) a non-interactive job run instead of surfacing a clear "missing option" error.
        $interactive = $this->input->isInteractive();

        $username = $this->option('username') ?: ($interactive ? $this->ask('Username', 'Demo Seller') : 'Demo Seller');
        $mobile = $this->option('mobile') ?: ($interactive ? $this->ask('Mobile number (used to log in)') : null);
        $email = $this->option('email') ?: ($interactive ? $this->ask('Email (optional)', '') : '');
        $password = $this->option('password') ?: ($interactive ? $this->secret('Password (min 8 characters)') : null);
        $storeName = $this->option('store-name') ?: 'Demo Store';

        if (!$mobile || !$password) {
            $this->error('--mobile and --password are required when running non-interactively (e.g. a Cloud Run Job).');

            return self::FAILURE;
        }

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

        [$user, $store] = DB::transaction(function () use ($username, $mobile, $email, $password, $storeName) {
            $user = User::forceCreate([
                'username' => $username,
                'mobile' => $mobile,
                'email' => $email ?: null,
                'password' => Hash::make($password),
                'disk' => 'public',
                'serviceable_cities' => '',
                'type' => 'phone',
                'role_id' => Role::SELLER,
                'active' => 1,
                'status' => 1,
            ]);

            // seller_data (the Seller model's table) only has user_id/disk/status plus a few document
            // fields - 'store_name' and friends in Seller::$fillable refer to seller_store columns, not
            // this table (a pre-existing mismatch in the model, not something this command should paper
            // over by writing to a column that doesn't exist).
            $seller = Seller::forceCreate([
                'user_id' => $user->id,
                'disk' => 'public',
                'status' => 1, // seller_data.status: approved
            ]);

            $slug = 'demo-store-' . $user->id;

            $store = Store::forceCreate([
                'name' => json_encode(['en' => $storeName]),
                'slug' => $slug,
                'description' => json_encode(['en' => 'Demo store for browsing the platform.']),
                'disk' => 'public',
                'background_color' => '#ffffff',
                'delivery_charge_type' => 'fixed',
                'product_deliverability_type' => 'all',
                'status' => 1,
            ]);

            SellerStore::forceCreate([
                'seller_id' => $seller->id,
                'user_id' => $user->id,
                'store_id' => $store->id,
                'slug' => $slug,
                'store_name' => $storeName,
                'store_description' => 'Demo store for browsing the platform.',
                'logo' => '',
                'store_thumbnail' => '',
                'disk' => 'public',
                'store_url' => $slug,
                'bank_name' => '',
                'bank_code' => '',
                'account_name' => '',
                'account_number' => '',
                'address_proof' => '',
                'tax_name' => '',
                'tax_number' => '',
                'status' => 1, // seller_store.status: active
            ]);

            return [$user, $store];
        });

        $this->info("Demo seller created: {$user->username} (id {$user->id}), store '{$storeName}' (id {$store->id}).");
        $this->info("Log in with mobile {$mobile} at /seller/login.");

        return self::SUCCESS;
    }
}
