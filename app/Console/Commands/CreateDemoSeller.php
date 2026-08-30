<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesDemoImages;
use App\Models\Category;
use App\Models\Product;
use App\Models\Product_attributes;
use App\Models\Product_variants;
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
 *
 * Also creates 3 demo products (real placeholder images, a variant each so a price/stock actually shows -
 * matching Admin\ProductController::process_bulk_upload()'s own minimal simple_product shape: one
 * Product_attributes row + one Product_variants row per product) so the seller's Manage Products page isn't
 * empty on first login.
 */
class CreateDemoSeller extends Command
{
    use GeneratesDemoImages;

    protected $signature = 'demo:create-seller
        {--username= : Display name for the account (default: Demo Seller)}
        {--mobile= : Login mobile number (must be unique). Prompted for if omitted and running interactively.}
        {--email= : Optional email address}
        {--password= : Login password (min 8 characters). Prompted for if omitted and running interactively.}
        {--store-name= : Store display name (default: Demo Store)}
        {--no-products : Skip creating demo products}';

    protected $description = 'Create a demo seller account with an already-approved store and a few demo products (with images), so the seller panel can be logged into and browsed without the web registration forms.';

    public function handle(): int
    {
        // Cloud Run Jobs execute with no attached stdin, so this only prompts when actually running
        // interactively (a local/exec shell) - falling back to $this->ask() unconditionally would hang (or
        // fail on EOF) a non-interactive job run instead of surfacing a clear "missing option" error.
        $interactive = $this->input->isInteractive();

        $username = $this->option('username') ?: ($interactive ? $this->ask('Username', 'Demo Seller') : 'Demo Seller');
        $mobile = $this->option('mobile') ?: ($interactive ? $this->ask('Mobile number (used to log in)') : null);
        // Bug fix: --email is genuinely optional - an explicitly-passed empty string ('' from
        // demo:seed-all, or --email= with no value) is a real, final answer, not "not yet provided". The
        // previous `?:` treated '' as falsy and always fell through to $this->ask() here, which throws
        // under Laravel's Artisan test helper (no Mockery expectation for askQuestion()) and would also
        // print an unwanted interactive prompt on every real non-interactive run that already answered "no
        // email" via --email=.
        $email = $this->option('email') !== null ? $this->option('email') : ($interactive ? $this->ask('Email (optional)', '') : '');
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

        $this->info('Generating placeholder images (avatar, store logo, product photos)...');
        $avatarUrl = $this->uploadDemoImage($username, '#2563eb');
        $logoUrl = $this->uploadDemoImage($storeName, '#0f766e');
        $thumbnailUrl = $this->uploadDemoImage($storeName, '#0f766e');

        [$user, $store, $productCount] = DB::transaction(function () use ($username, $mobile, $email, $password, $storeName, $avatarUrl, $logoUrl, $thumbnailUrl) {
            $user = User::forceCreate([
                'username' => $username,
                'mobile' => $mobile,
                'email' => $email ?: null,
                'password' => Hash::make($password),
                'image' => $avatarUrl,
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

            $category = Category::where('store_id', $store->id)->where('status', 1)->first();
            if (!$category) {
                $category = Category::forceCreate([
                    'name' => json_encode(['en' => 'Demo Category']),
                    'slug' => 'demo-category-' . $store->id,
                    'image' => $this->uploadDemoImage('Demo Category', '#7c3aed'),
                    'banner' => $this->uploadDemoImage('Demo Category', '#7c3aed'),
                    'store_id' => $store->id,
                    'parent_id' => 0,
                    'status' => 1,
                ]);
            }

            SellerStore::forceCreate([
                'seller_id' => $seller->id,
                'user_id' => $user->id,
                'store_id' => $store->id,
                'slug' => $slug,
                'category_ids' => (string) $category->id,
                'store_name' => $storeName,
                'store_description' => 'Demo store for browsing the platform.',
                'logo' => $logoUrl,
                'store_thumbnail' => $thumbnailUrl,
                'disk' => 'public',
                'store_url' => $slug,
                'bank_name' => '',
                'bank_code' => '',
                'account_name' => '',
                'account_number' => '',
                'address_proof' => '',
                'tax_name' => '',
                'tax_number' => '',
                'permissions' => json_encode(['require_products_approval' => 0]),
                'status' => 1, // seller_store.status: active
            ]);

            $productCount = 0;
            if (!$this->option('no-products')) {
                $demoProducts = [
                    ['name' => 'Demo Product - Wireless Mouse', 'price' => 29.99, 'color' => '#dc2626'],
                    ['name' => 'Demo Product - Desk Lamp', 'price' => 49.50, 'color' => '#ea580c'],
                    ['name' => 'Demo Product - Coffee Mug', 'price' => 12.00, 'color' => '#16a34a'],
                ];

                foreach ($demoProducts as $i => $demo) {
                    $imageUrl = $this->uploadDemoImage($demo['name'], $demo['color']);

                    $product = Product::forceCreate([
                        'category_id' => $category->id,
                        'store_id' => $store->id,
                        'seller_id' => $seller->id,
                        'type' => 'simple_product',
                        'stock_type' => '0',
                        'name' => json_encode(['en' => $demo['name']]),
                        'short_description' => json_encode(['en' => 'A simple demo product for browsing the seller panel.']),
                        'slug' => 'demo-product-' . $user->id . '-' . ($i + 1),
                        'cod_allowed' => 1,
                        'minimum_order_quantity' => 1,
                        'quantity_step_size' => 1,
                        'image' => $imageUrl,
                        'deliverable_type' => 1,
                        'city_deliverable_type' => 1,
                        'deliverable_cities' => '',
                        'status' => 1,
                        // stock_type 0 keeps its real stock on products.stock, not
                        // product_variants.stock (see ProductService::getStock()/updateStock()) - without
                        // this, availability checks everywhere (POS, storefront) read stock as NULL and
                        // treat the product as unavailable/out of stock despite the variant row below.
                        'stock' => 50,
                        'availability' => 1,
                    ]);

                    Product_attributes::forceCreate([
                        'product_id' => $product->id,
                        'attribute_value_ids' => '',
                    ]);

                    Product_variants::forceCreate([
                        'product_id' => $product->id,
                        'attribute_value_ids' => null,
                        'price' => $demo['price'],
                        'special_price' => 0,
                        'sku' => 'DEMO-' . $user->id . '-' . ($i + 1),
                        'stock' => 50,
                        'availability' => 1,
                        'status' => 1,
                        'images' => json_encode([$imageUrl]),
                    ]);

                    $productCount++;
                }
            }

            return [$user, $store, $productCount];
        });

        $this->info("Demo seller created: {$user->username} (id {$user->id}), store '{$storeName}' (id {$store->id}), {$productCount} demo product(s).");
        $this->info("Log in with mobile {$mobile} at /seller/login.");

        return self::SUCCESS;
    }
}
