<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.9, "Improved bulk upload reliability and stability for large
 * imports"): confirmed genuinely missing - bulk-upload endpoints across Admin/Seller Category, Brand,
 * Product, ComboProduct, and translation/language CSV import wrapped none of their multi-row insert/update
 * loops in a database transaction. A CSV with 500 valid rows followed by one malformed row used to leave
 * those 500 rows permanently committed and then crash (or silently stop) on the bad row - "reliability"
 * only in the sense that whatever succeeded before the failure stayed, which is the opposite of what a
 * bulk-import user expects (either the whole file imports, or none of it does).
 *
 * Every insert/update loop in Admin/Seller CategoryController, BrandController, AreaController,
 * ProductController, ComboProductController, and (Admin/Seller) LanguageController::process_bulk_upload()
 * is now wrapped in DB::transaction()/DB::beginTransaction()+commit()/rollBack(), with every early-return
 * error path rolling back first. These tests exercise the two simplest, single-pass endpoints (Brand and
 * Category "upload" CSVs, both no-pre-validation-pass) end-to-end over real HTTP file uploads, proving a bad
 * row anywhere in the file leaves zero rows committed - not "the ones before the bad row."
 */
class BulkUploadAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
    }

    public function test_a_valid_brand_csv_imports_every_row(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $csv = "name,image,store_id\n"
            . json_encode(['en' => 'Brand One']) . ",image1.jpg,1\n"
            . json_encode(['en' => 'Brand Two']) . ",image2.jpg,1\n";
        $file = UploadedFile::fake()->createWithContent('brands.csv', $csv);

        $response = $this->actingAs($admin)->post(route('brands.bulk_upload'), [
            'upload_file' => $file,
            'type' => 'upload',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', 'false');
        $this->assertSame(2, Brand::count());
    }

    public function test_a_bad_row_anywhere_in_a_brand_csv_rolls_back_every_row_not_just_the_bad_one(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        // Row 1 is entirely valid and would succeed on its own. Row 2 is missing its "image" column
        // (brands.image is NOT NULL with no default) - a real DB-level failure, not a validation-layer
        // one, since Brand::create() is never given a chance to short-circuit before hitting the DB.
        $csv = "name,image,store_id\n"
            . json_encode(['en' => 'Good Brand']) . ",image1.jpg,1\n"
            . json_encode(['en' => 'Bad Brand']) . "\n";
        $file = UploadedFile::fake()->createWithContent('brands.csv', $csv);

        $response = $this->actingAs($admin)->post(route('brands.bulk_upload'), [
            'upload_file' => $file,
            'type' => 'upload',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', 'true');
        $this->assertSame(0, Brand::count(), 'The valid first row must not remain committed once a later row fails - the whole import must roll back atomically.');
    }

    public function test_a_valid_category_csv_imports_every_row(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $csv = "name,image,store_id,parent_id,banner\n"
            . json_encode(['en' => 'Category One']) . ",image1.jpg,1,,banner1.jpg\n"
            . json_encode(['en' => 'Category Two']) . ",image2.jpg,1,,banner2.jpg\n";
        $file = UploadedFile::fake()->createWithContent('categories.csv', $csv);

        $response = $this->actingAs($admin)->post(route('categories.bulk_upload'), [
            'upload_file' => $file,
            'type' => 'upload',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', 'false');
        $this->assertSame(2, Category::count());
    }

    public function test_a_bad_row_anywhere_in_a_category_csv_rolls_back_every_row_not_just_the_bad_one(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        // Row 2 names a parent_id that doesn't exist for the given store - process_bulk_upload() catches
        // this explicitly and returns an error mid-loop; the fix under test is that this early return now
        // rolls back the transaction first, so the already-inserted row 1 does not survive either.
        $csv = "name,image,store_id,parent_id,banner\n"
            . json_encode(['en' => 'Good Category']) . ",image1.jpg,1,,banner1.jpg\n"
            . json_encode(['en' => 'Bad Category']) . ",image2.jpg,1,999999,banner2.jpg\n";
        $file = UploadedFile::fake()->createWithContent('categories.csv', $csv);

        $response = $this->actingAs($admin)->post(route('categories.bulk_upload'), [
            'upload_file' => $file,
            'type' => 'upload',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', 'true');
        $this->assertSame(0, Category::count(), 'The valid first row must not remain committed once a later row fails validation - the whole import must roll back atomically.');
    }
}
