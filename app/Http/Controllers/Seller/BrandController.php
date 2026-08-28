<?php

namespace App\Http\Controllers\Seller;

use App\Models\Brand;
use App\Models\Language;
use App\Models\Product;
use App\Models\SellerStore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\StoreService;
use App\Services\MediaService;
class BrandController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $languages = Language::all();
        return view('seller.pages.forms.brands', compact('languages'));
    }


    public function store(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();

        // Security fix (docs/SECURITY_AUDIT.md §6.2, same fix already made to Seller\v1\ApiController::
        // add_brands() for the mobile-API version of this same feature): StoreService::getStoreId() reads
        // session('store_id'), which SetDefaultStore middleware sets from an unauthenticated `?store=slug`
        // query parameter on any web request - a seller could navigate their own panel with that parameter
        // set to another seller's store slug and have it silently redirect their session there, then create
        // a Brand row under that other seller's store_id (brands has no seller_id column of its own -
        // store_id IS the tenant boundary here). Verifies the authenticated seller actually manages the
        // resolved store_id before proceeding.
        if (!SellerStore::where('user_id', Auth::id())->where('store_id', $storeId)->exists()) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $rules = [
            'brand_name' => 'required|string',
            'translated_brand_name' => 'sometimes|array',
            'translated_brand_name.*' => 'nullable|string',
            'image' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $brandData = $request->all();
        $existingBrand = Brand::where('store_id', app(StoreService::class)->getStoreId())
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) = ?", $brandData['brand_name'])
            ->first();

        if ($existingBrand) {
            return response()->json([
                'error' => true,
                'message' => 'Brand name already exists.',
                'language_message_key' => 'brand_name_exists',
            ], 422);
        }

        $translations = [
            'en' => $brandData['brand_name']
        ];

        // Merge other translations if available
        if (!empty($brandData['translated_brand_name'])) {
            $translations = array_merge($translations, $brandData['translated_brand_name']);
        }


        $brandData['name'] = json_encode($translations, JSON_UNESCAPED_UNICODE);


        unset($brandData['brand_name'], $brandData['translated_brand_name']);

        // Add additional fields
        $brandData['slug'] = generateSlug($translations['en'], 'brands');
        $brandData['status'] = 2;
        $brandData['store_id'] = $storeId;
        unset($brandData['_method']);
        unset($brandData['_token']);

        $brand = new Brand();
        $brand->fill($brandData);
        $brand->save();

        // Return response
        if ($request->ajax()) {
            return response()->json(['message' => labels('admin_labels.brand_created_successfully', 'Brand created successfully, Wait for approval of admin')]);
        }

        return redirect()->back()->with('success', labels('admin_labels.brand_created_successfully', 'Brand created successfully'));
    }
    public function list(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $search = trim(request('search'));
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $offset = $search || request('pagination_offset') ? request('pagination_offset') : 0;
        $limit = request('limit', 10);
        $status = $request->input('status', '');

        $brandData = Brand::when($search, function ($query) use ($search) {
            return $query->where('name', 'like', '%' . $search . '%');
        });
        if (!is_null($status) && $status !== '') {
            $brandData->where('status', $status);
        }
        $brandData->where('store_id', $storeId);
        $total = $brandData->count();

        // Fetch brand data
        $brands = $brandData->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $data = $brands->map(function ($b) {
            $languageCode = app(TranslationService::class)->getLanguageCode();
            $image = route('admin.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($b->image),
                'width' => 60,
                'quality' => 90
            ]);
            return [
                'id' => $b->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Brand::class, 'name', $b->id, $languageCode),
                'image' => '<div class=""><a href="' . app(MediaService::class)->getMediaImageUrl($b->image) . '" data-lightbox="image-' . $b->id . '"><img src="' . $image . '" alt="Avatar" class="rounded"/></a></div>',
            ];
        });

        return response()->json([
            "rows" => $data,
            "total" => $total,
        ]);
    }
}
