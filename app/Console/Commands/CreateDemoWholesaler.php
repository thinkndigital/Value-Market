<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesDemoImages;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Wholesaler\AuthController::authenticate() requires the linked wholesalers.status = 1 to log in - creates
 * that row directly with status already set, active from the moment this command finishes (see
 * docs/WHOLESALER_MODULE.md). Also seeds two already-approved wholesaler_products rows (status = 1) so the
 * account is immediately useful to demo both halves of the module: the wholesaler's own catalog, and a
 * seller browsing/importing from it - not just an empty dashboard.
 */
class CreateDemoWholesaler extends Command
{
    use GeneratesDemoImages;

    protected $signature = 'demo:create-wholesaler
        {--username= : Display name for the account (default: Demo Wholesaler)}
        {--business-name= : Business name shown on the wholesaler profile and product listings (default: Demo Wholesale Co)}
        {--mobile= : Login mobile number (must be unique). Prompted for if omitted and running interactively.}
        {--password= : Login password (min 8 characters). Prompted for if omitted and running interactively.}';

    protected $description = 'Create a demo wholesaler account (active, with two already-approved sample products) for browsing the wholesaler panel and the seller-side marketplace.';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $username = $this->option('username') ?: ($interactive ? $this->ask('Username', 'Demo Wholesaler') : 'Demo Wholesaler');
        $businessName = $this->option('business-name') ?: ($interactive ? $this->ask('Business name', 'Demo Wholesale Co') : 'Demo Wholesale Co');
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

        $this->info('Generating placeholder images...');
        $avatarUrl = $this->uploadDemoImage($username, '#8a5a00');

        [$user, $wholesaler] = DB::transaction(function () use ($username, $mobile, $password, $businessName, $avatarUrl) {
            $user = User::forceCreate([
                'username' => $username,
                'mobile' => $mobile,
                'password' => Hash::make($password),
                'image' => $avatarUrl,
                'disk' => 'public',
                'serviceable_cities' => '',
                'type' => 'phone',
                'role_id' => Role::WHOLESALER,
                'active' => 1,
            ]);

            $wholesaler = Wholesaler::create([
                'user_id' => $user->id,
                'business_name' => $businessName,
                'description' => 'Demo wholesale account for the Wholesaler Marketplace module.',
                'status' => 1,
                'disk' => 'public',
            ]);

            return [$user, $wholesaler];
        });

        $category = Category::where('status', 1)->first();
        $sampleProducts = [
            ['name' => 'Bulk Cotton T-Shirts (Pack of 50)', 'price' => 6.50, 'min_qty' => 10, 'stock' => 2000, 'color' => '#8a5a00'],
            ['name' => 'Wireless Earbuds (Pack of 20)', 'price' => 9.00, 'min_qty' => 5, 'stock' => 500, 'color' => '#20262c'],
        ];

        foreach ($sampleProducts as $sample) {
            $imageUrl = $this->uploadDemoImage($sample['name'], $sample['color'], 'wholesaler_products');

            WholesalerProduct::create([
                'wholesaler_id' => $wholesaler->id,
                'category_id' => $category?->id,
                'name' => json_encode(['en' => $sample['name']], JSON_UNESCAPED_UNICODE),
                'description' => 'Sample wholesale listing seeded by demo:create-wholesaler.',
                'image' => $imageUrl,
                'wholesale_price' => $sample['price'],
                'min_order_qty' => $sample['min_qty'],
                'stock' => $sample['stock'],
                'status' => 1, // already admin-approved, so it's immediately visible in the seller marketplace
                'slug' => generateSlug($sample['name'], 'wholesaler_products'),
            ]);
        }

        $this->info("Demo wholesaler created: {$user->username} / {$businessName} (id {$wholesaler->id}).");
        $this->info('Seeded ' . count($sampleProducts) . ' already-approved sample products.');
        $this->info("Log in with mobile {$mobile} at /wholesaler/login.");

        return self::SUCCESS;
    }
}
