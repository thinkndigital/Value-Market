<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\AffiliateStore;
use App\Models\AffiliateStoreProduct;
use App\Models\Product;
use App\Models\SellerStore;
use App\Models\StorageType;
use App\Services\MediaService;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Master architecture prompt Phase 7 (Affiliate architecture, section 26 "Affiliate Store"): a
 * mini-store/landing page the affiliate publishes, featuring a curated set of products from their own
 * "My Products" list (AffiliateController::myProductsList()) - every featured product is really just an
 * existing AffiliateLink the affiliate already generated, so the public page's product links reuse the
 * same tracked redirect (/r/{code}) as everywhere else in this engine, not a separate mechanism.
 */
class AffiliateStoreController extends Controller
{
    use HandlesValidation;

    private function currentStore(): ?AffiliateStore
    {
        return AffiliateStore::where('user_id', Auth::id())->first();
    }

    /** The management page: current settings + this affiliate's "My Products" links, each flagged as
     *  featured or not, so the page can double as the "add to store" picker. */
    public function manage()
    {
        $store = $this->currentStore();
        $featuredLinkIds = $store ? $store->storeProducts()->pluck('affiliate_link_id') : collect();

        $links = AffiliateLink::where('user_id', Auth::id())
            ->where('target_type', AffiliateLink::TARGET_PRODUCT)
            ->orderByDesc('id')
            ->get();

        $products = Product::whereIn('id', $links->pluck('target_id'))->get(['id', 'name', 'image'])->keyBy('id');

        $myProducts = $links->map(function ($link) use ($products, $featuredLinkIds) {
            $product = $products->get($link->target_id);

            return [
                'link_id' => $link->id,
                'name' => $product ? (json_decode($product->name, true)['en'] ?? '') : labels('admin_labels.product_unavailable', 'Product no longer available'),
                'image' => $product ? app(MediaService::class)->getMediaImageUrl($product->image) : '',
                'featured' => $featuredLinkIds->contains($link->id),
            ];
        })->values();

        return view('affiliate.my_store', compact('store', 'myProducts'));
    }

    public function update(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'logo' => 'nullable|image',
            'banner' => 'nullable|image',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $store = $this->currentStore() ?? new AffiliateStore(['user_id' => Auth::id()]);

        if (!$store->exists) {
            $store->slug = generateSlug($request->name, 'affiliate_stores');
        }
        $store->name = $request->name;
        $store->description = $request->description;

        foreach (['logo', 'banner'] as $field) {
            if ($request->hasFile($field)) {
                $store->{$field} = $this->uploadImage($request, $field, 'affiliate_store_' . $field . 's');
            }
        }

        $store->save();

        return response()->json(['error' => false, 'message' => labels('admin_labels.saved_successfully', 'Saved successfully.'), 'slug' => $store->slug]);
    }

    private function uploadImage(Request $request, string $field, string $collection): string
    {
        $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
        $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->id : 1;
        $disk = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';
        $media = StorageType::find($mediaStorageType);

        $uploaded = $media->addMedia($request->file($field))
            ->sanitizingFileName(function ($fileName) {
                $sanitized = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));
                $uniqueId = time() . '_' . mt_rand(1000, 9999);
                $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
                $baseName = pathinfo($sanitized, PATHINFO_FILENAME);
                return "{$baseName}-{$uniqueId}.{$extension}";
            })
            ->toMediaCollection($collection, $disk);

        // getFullUrl() (not a hand-built path) - App\Services\CustomPathGenerator nests every collection
        // under its own subfolder, and this is the disk-agnostic way to resolve it (public or s3) - the
        // same lesson from WHOLESALER_MODULE.md's image-path bug, applied here from the start.
        return $uploaded->getFullUrl();
    }

    public function togglePublish(Request $request)
    {
        $validator = Validator::make($request->all(), ['status' => 'required|in:0,1']);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $store = $this->currentStore();
        if (!$store) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.store_not_found', 'Store not found.')]);
        }

        $store->status = (int) $request->input('status');
        $store->save();

        return response()->json(['error' => false, 'message' => labels('admin_labels.saved_successfully', 'Saved successfully.')]);
    }

    public function addFeatured(Request $request)
    {
        $validator = Validator::make($request->all(), ['link_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $store = $this->currentStore();
        if (!$store) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.store_not_found', 'Store not found. Save your store details first.')]);
        }

        // Only a link this affiliate actually owns can be featured - prevents featuring another
        // affiliate's tracked link (which would misattribute clicks on this store's page).
        $link = AffiliateLink::where('id', $request->input('link_id'))
            ->where('user_id', Auth::id())
            ->where('target_type', AffiliateLink::TARGET_PRODUCT)
            ->first();
        if (!$link) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        AffiliateStoreProduct::firstOrCreate([
            'affiliate_store_id' => $store->id,
            'affiliate_link_id' => $link->id,
        ], [
            'sort_order' => $store->storeProducts()->count(),
        ]);

        return response()->json(['error' => false, 'message' => labels('admin_labels.saved_successfully', 'Saved successfully.')]);
    }

    public function removeFeatured(Request $request)
    {
        $store = $this->currentStore();
        if (!$store) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.store_not_found', 'Store not found.')]);
        }

        AffiliateStoreProduct::where('affiliate_store_id', $store->id)
            ->where('affiliate_link_id', $request->input('link_id'))
            ->delete();

        return response()->json(['error' => false, 'message' => labels('admin_labels.saved_successfully', 'Saved successfully.')]);
    }

    /** Public - not behind auth, matching AffiliateController::trackAndRedirect(). Only a published store
     *  is reachable; a draft or nonexistent slug both 404 identically (no leaking which slugs exist). */
    public function show(string $slug)
    {
        $store = AffiliateStore::where('slug', $slug)->where('status', AffiliateStore::STATUS_PUBLISHED)->firstOrFail();

        $storeProducts = $store->storeProducts()->with('affiliateLink')->get();
        $links = $storeProducts->pluck('affiliateLink')->filter();
        $products = Product::whereIn('id', $links->pluck('target_id'))
            ->where('status', 1)
            ->get(['id', 'name', 'image', 'store_id']);
        $storeNames = SellerStore::whereIn('store_id', $products->pluck('store_id')->unique())->pluck('store_name', 'store_id');

        $items = $links->map(function ($link) use ($products, $storeNames) {
            $product = $products->firstWhere('id', $link->target_id);
            if (!$product) {
                return null;
            }

            return [
                'name' => json_decode($product->name, true)['en'] ?? '',
                'image' => app(MediaService::class)->getMediaImageUrl($product->image),
                'seller_store_name' => $storeNames->get($product->store_id),
                'link_url' => route('affiliate.track', ['code' => $link->code]),
            ];
        })->filter()->values();

        return view('affiliate.store_public', compact('store', 'items'));
    }
}
