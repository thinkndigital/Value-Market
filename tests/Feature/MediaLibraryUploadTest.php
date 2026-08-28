<?php

namespace Tests\Feature;

use App\Http\Controllers\Seller\MediaController;
use App\Models\Media;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\StorageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies the real spatie/laravel-medialibrary upload pipeline (addMedia()->toMediaCollection(), the
 * CustomPathGenerator/CustomFileRemover bindings in config/media-library.php) still works end to end after
 * the v10.15->v11.23 upgrade (docs/PHASE_17_FULL_QA_PRODUCTION_READINESS.md §3 - resolves CVE-2026-48557/
 * CVE-2026-48555). Exercises Seller\MediaController::upload(), the one real controller-driven upload flow
 * with the simplest fixture needs, and asserts the file actually lands on disk (not just "no exception").
 */
class MediaLibraryUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_media_upload_stores_a_real_file_via_medialibrary(): void
    {
        Storage::fake('public');

        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        StorageType::forceCreate(['name' => 'public', 'is_default' => 1]);
        Auth::login($sellerUser);

        $file = UploadedFile::fake()->image('product.jpg', 200, 200);
        $request = \Illuminate\Http\Request::create('/seller/media/upload', 'POST', ['store_id' => 1], [], ['documents' => [$file]]);
        $request->setUserResolver(fn () => $sellerUser);

        $response = app(MediaController::class)->upload($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'Upload must succeed: ' . ($payload['message'] ?? ''));
        $this->assertNotEmpty($payload['media_paths']);

        $media = Media::where('seller_id', $seller->id)->where('store_id', 1)->first();
        $this->assertNotNull($media, 'A Media row must be created by the medialibrary addMedia()->toMediaCollection() call.');
        $this->assertNotEmpty($media->file_name);

        // The real, on-disk file medialibrary wrote - proves CustomPathGenerator + the storage disk pipeline
        // still produce a real, readable file under the new package version, not just a DB row.
        $diskMedia = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($media->id);
        $this->assertTrue(
            Storage::disk('public')->exists($diskMedia->getPathRelativeToRoot()),
            'The uploaded file must actually exist on the public disk at the path medialibrary generated.'
        );
    }
}
