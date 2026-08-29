<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.11, "JSON language upload"): LanguageController::savelabel() and
 * FrontLanguageController::savelabel() used to do `include($uploadedFile->getRealPath())` directly on
 * whatever file an admin uploaded - i.e. execute it as PHP. Any authenticated admin could upload a `.php`
 * file containing arbitrary code and have it run server-side on the very next request. Fixed by replacing
 * the include() with LanguageJsonImportService::parse(), which only ever json_decode()s the upload and
 * validates the result is a flat string=>scalar map - the file's bytes are never executed under any
 * circumstance, regardless of what extension or content an attacker supplies.
 *
 * These tests prove both the positive path (a real JSON label file imports correctly) and, more
 * importantly, that a file crafted to exploit the old include() path is rejected without any side effect -
 * confirmed via a marker file the payload would have created if it ever executed.
 */
class LanguageJsonUploadSecurityTest extends TestCase
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

    private function tearDownLangDir(string $code): void
    {
        $dir = base_path("resources/lang/{$code}");
        foreach (['admin_labels.php', 'front_messages.php'] as $file) {
            $path = $dir . '/' . $file;
            if (file_exists($path)) {
                unlink($path);
            }
        }
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
        }
    }

    public function test_valid_json_upload_imports_admin_labels(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        session(['locale' => 'zz_test_json']);

        $json = json_encode(['welcome' => 'Welcome', 'logout' => 'Logout']);
        $file = UploadedFile::fake()->createWithContent('labels.json', $json);

        $response = $this->actingAs($admin)->putJson(route('admin.savelabel'), [
            'translation_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);

        $written = include base_path('resources/lang/zz_test_json/admin_labels.php');
        $this->assertSame(['welcome' => 'Welcome', 'logout' => 'Logout'], $written);

        $this->tearDownLangDir('zz_test_json');
    }

    public function test_malicious_php_payload_disguised_as_json_is_never_executed(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        session(['locale' => 'zz_test_rce']);

        $markerPath = storage_path('framework/testing/rce_marker_' . uniqid() . '.txt');
        // This is exactly the shape of payload the old include() would have executed: real PHP code that
        // writes a marker file, disguised with a .json filename to pass any naive extension-only gate.
        $maliciousPhp = "<?php file_put_contents(" . var_export($markerPath, true) . ", 'pwned'); return ['x' => 'y'];";
        $file = UploadedFile::fake()->createWithContent('labels.json', $maliciousPhp);

        $response = $this->actingAs($admin)->putJson(route('admin.savelabel'), [
            'translation_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertFileDoesNotExist($markerPath, 'The uploaded file must never be executed - if this file exists, the RCE is still present.');
        $this->assertFileDoesNotExist(base_path('resources/lang/zz_test_rce/admin_labels.php'));
    }

    public function test_malformed_json_is_rejected_without_corrupting_anything(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        session(['locale' => 'zz_test_malformed']);

        $file = UploadedFile::fake()->createWithContent('labels.json', '{"broken": "json",}');

        $response = $this->actingAs($admin)->putJson(route('admin.savelabel'), [
            'translation_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertFileDoesNotExist(base_path('resources/lang/zz_test_malformed/admin_labels.php'));
    }

    public function test_nested_json_object_values_are_rejected_not_silently_stringified(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        session(['locale' => 'zz_test_nested']);

        $json = json_encode(['welcome' => ['nested' => 'value']]);
        $file = UploadedFile::fake()->createWithContent('labels.json', $json);

        $response = $this->actingAs($admin)->putJson(route('admin.savelabel'), [
            'translation_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertFileDoesNotExist(base_path('resources/lang/zz_test_nested/admin_labels.php'));
    }

    public function test_front_language_json_upload_also_rejects_the_rce_payload(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        session(['locale' => 'zz_test_front_rce']);

        $markerPath = storage_path('framework/testing/rce_marker_front_' . uniqid() . '.txt');
        $maliciousPhp = "<?php file_put_contents(" . var_export($markerPath, true) . ", 'pwned'); return ['x' => 'y'];";
        $file = UploadedFile::fake()->createWithContent('labels.json', $maliciousPhp);

        $response = $this->actingAs($admin)->putJson(route('front.savelabel'), [
            'translation_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertFileDoesNotExist($markerPath);
        $this->assertFileDoesNotExist(base_path('resources/lang/zz_test_front_rce/front_messages.php'));
    }
}
