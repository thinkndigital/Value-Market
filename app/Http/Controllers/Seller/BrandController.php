<?php

namespace App\Http\Controllers\Seller;

use App\Models\Brand;
use App\Models\Language;
use App\Models\Product;
use App\Models\Seller;
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
        // Brand-request lifecycle (docs/CHANGELOG_FEATURE_AUDIT.md v1.0.6) - see the identical comment in
        // Seller\CategoryController::store() for why this is resolved via the Seller row's id, not Auth::id().
        $brandData['requested_by_seller_id'] = Seller::where('user_id', Auth::id())->value('id');
        $brandData['approval_status'] = Brand::APPROVAL_PENDING;
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
    /**
     * "My Brand Requests" - scoped to the logged-in seller's own submitted rows (any approval status),
     * never the full store brand list (docs/CHANGELOG_FEATURE_AUDIT.md v1.0.11 "Seller App can view
     * pending Brands"). Ownership resolved via the Seller row's id - see store() above.
     */
    public function list(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $seller_id = Seller::where('user_id', Auth::id())->value('id');
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
        $brandData->where('store_id', $storeId)
            ->where('requested_by_seller_id', $seller_id);
        $total = $brandData->count();

        // Fetch brand data
        $brands = $brandData->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $data = $brands->map(function ($b) use ($seller_id) {
            $languageCode = app(TranslationService::class)->getLanguageCode();
            $image = route('seller.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($b->image),
                'width' => 60,
                'quality' => 90
            ]);

            $status = $this->requestStatusBadge($b);

            $operate = '';
            if ($b->requested_by_seller_id == $seller_id && $b->approval_status == Brand::APPROVAL_PENDING) {
                $operate = '<div class="dropdown bootstrap-table-dropdown">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </a>
                    <div class="dropdown-menu table_dropdown" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . route('seller.brands.destroy', $b->id) . '"><i class="bx bx-trash mx-2"></i> ' . labels('admin_labels.delete', 'Delete') . '</a>
                    </div>
                </div>';
            }

            return [
                'id' => $b->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Brand::class, 'name', $b->id, $languageCode),
                'status' => $status,
                'image' => '<div class=""><a href="' . app(MediaService::class)->getMediaImageUrl($b->image) . '" data-lightbox="image-' . $b->id . '"><img src="' . $image . '" alt="Avatar" class="rounded"/></a></div>',
                'operate' => $operate,
            ];
        });

        return response()->json([
            "rows" => $data,
            "total" => $total,
        ]);
    }

    /** See Seller\CategoryController::requestStatusBadge() for the identical rationale. */
    private function requestStatusBadge(Brand $b): string
    {
        if ($b->approval_status == Brand::APPROVAL_PENDING) {
            return '<span class="badge bg-warning">' . labels('admin_labels.pending_approval', 'Pending Approval') . '</span>';
        }
        if ($b->approval_status == Brand::APPROVAL_REJECTED) {
            return '<span class="badge bg-danger">' . labels('admin_labels.rejected', 'Rejected') . '</span>';
        }
        return $b->status == 1
            ? '<span class="badge bg-success">' . labels('admin_labels.active', 'Active') . '</span>'
            : '<span class="badge bg-danger">' . labels('admin_labels.deactive', 'Deactive') . '</span>';
    }

    /**
     * Withdraw a still-pending brand request. Ownership- and status-scoped - see
     * Seller\CategoryController::destroy() for the identical rationale.
     */
    public function destroy($id)
    {
        $seller_id = Seller::where('user_id', Auth::id())->value('id');
        $brand = Brand::where('id', $id)->where('requested_by_seller_id', $seller_id)->first();

        if (!$brand) {
            return response()->json(['error' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }

        if ($brand->approval_status != Brand::APPROVAL_PENDING) {
            return response()->json(['error' => labels('seller_labels.only_pending_brand_requests_can_be_deleted', 'Only pending brand requests can be deleted.')]);
        }

        $brand->delete();

        return response()->json(['error' => false, 'message' => labels('admin_labels.brand_deleted_successfully', 'Brand deleted Successfully')]);
    }
}
