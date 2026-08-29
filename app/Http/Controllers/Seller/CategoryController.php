<?php

namespace App\Http\Controllers\Seller;

use App\Models\Seller;
use App\Models\Category;
use App\Models\SellerStore;
use App\Models\Language;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\StoreService;
use App\Services\MediaService;
use App\Services\TenantContext;
class CategoryController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $categories = Category::where('status', 1)->get();
        $languages = Language::all();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        return view('seller.pages.tables.categories', ['categories' => $categories, 'languages' => $languages, 'language_code' => $languageCode]);
    }


    public function store(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();

        // Security fix (docs/SECURITY_AUDIT.md §6.2, same fix already made to PosController::place_order()/
        // combo_place_order() and BrandController::store()): SetDefaultStore middleware lets an
        // unauthenticated `?store=slug` query param silently repoint session('store_id'), which
        // StoreService::getStoreId() then trusts blindly - a seller could create categories attributed to a
        // store_id they don't manage. Verify ownership before using it.
        if (app(TenantContext::class)->verifiedSellerStoreId($storeId) === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        // Validate request data
        $rules = [
            'name' => 'required|string',
            'category_image' => 'required',
            'banner' => 'required',
            'translated_category_name' => 'nullable|array',
            'translated_category_name.*' => 'nullable|string',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $categoryData = $request->only(array_keys($rules));

        $existingCategory = Category::where('store_id', $storeId)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) = ?", [$categoryData['name']])
            ->first();

        if ($existingCategory) {
            return response()->json([
                'error' => true,
                'message' => 'Category name already exists.',
                'language_message_key' => 'category_name_exists',
            ], 400);
        }

        // Handle translations
        $translations = ['en' => $categoryData['name']];
        if (!empty($categoryData['translated_category_name'])) {
            $translations = array_merge($translations, $categoryData['translated_category_name']);
        }

        // Category-request lifecycle (docs/CHANGELOG_FEATURE_AUDIT.md v1.0.6): resolved via the Seller row's
        // id, never Auth::id() directly - sellers and their Seller row have different ids, and every other
        // seller-scoped ownership check in this codebase (list() below included) resolves it the same way.
        $seller_id = Seller::where('user_id', Auth::id())->value('id');

        // Build data for storage
        $categoryData = [
            'name' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            'slug' => generateSlug($translations['en'], 'categories'),
            'image' => $categoryData['category_image'],
            'banner' => $request->banner,
            'parent_id' => $request->parent_id ?? 0,
            'style' => $request->category_style ?? '',
            'status' => 2,
            'store_id' => $storeId,
            'requested_by_seller_id' => $seller_id,
            'approval_status' => Category::APPROVAL_PENDING,
        ];

        Category::create($categoryData);

        $successMessage = labels('admin_labels.category_created_successfully', 'Category created successfully, Wait for approval of admi');
        return $request->ajax()
            ? response()->json(['message' => $successMessage])
            : redirect()->back()->with('success', $successMessage);
    }

    public function list(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $user_id = Auth::user()->id;

        $seller_id = Seller::where('user_id', $user_id)->value('id');

        $search = trim(request('search'));
        $sort = request('sort') ?: 'id';
        $order = request('order') ?: 'DESC';
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;
        $limit = request('limit') ?: 10;


        $seller_store = SellerStore::select('category_ids')->where('seller_id', $seller_id)->where('store_id', $store_id)->first();
        $category_ids = ($seller_store && $seller_store->category_ids) ? explode(",", $seller_store->category_ids) : [];

        // A seller sees two things in this same table: the categories admin has assigned them
        // (category_ids on their seller_store pivot row) AND every category-request they've submitted
        // themselves (any approval status - pending/approved/rejected), scoped to their own Seller row id
        // per docs/CHANGELOG_FEATURE_AUDIT.md v1.0.11 "Seller App can view pending Categories". Never
        // Auth::id() - see the ownership-scoping comment in store() above.
        $category_data = Category::where('store_id', $store_id)
            ->where(function ($query) use ($category_ids, $seller_id) {
                if (!empty($category_ids)) {
                    $query->whereIn('id', $category_ids);
                }
                $query->orWhere('requested_by_seller_id', $seller_id);
            });
        if ($search) {
            $category_data->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('parent_id', 'like', '%' . $search . '%');
            });
        }
        $total = $category_data->count();

        $categories = $category_data->orderBy($sort, $order)->offset($offset)
            ->limit($limit)
            ->get();
        $language_code = app(TranslationService::class)->getLanguageCode();
        $data = $categories->map(function ($c) use ($language_code, $seller_id) {
            $status = $this->requestStatusBadge($c);
            $image = route('seller.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($c->image),
                'width' => 60,
                'quality' => 90
            ]);
            $banner = route('seller.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($c->banner),
                'width' => 60,
                'quality' => 90
            ]);

            $operate = '';
            if ($c->requested_by_seller_id == $seller_id && $c->approval_status == Category::APPROVAL_PENDING) {
                $operate = '<div class="dropdown bootstrap-table-dropdown">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </a>
                    <div class="dropdown-menu table_dropdown" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . route('seller.categories.destroy', $c->id) . '"><i class="bx bx-trash mx-2"></i> ' . labels('admin_labels.delete', 'Delete') . '</a>
                    </div>
                </div>';
            }

            return [
                'id' => $c->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $c->id, $language_code),
                'status' => $status,
                'image' => '<div><a href="' . app(MediaService::class)->getMediaImageUrl($c->image)  . '" data-lightbox="image-' . $c->id . '"><img src="' . $image  . '" alt="Avatar" class="rounded"/></a></div>',
                'banner' => '<div ><a href="' . app(MediaService::class)->getMediaImageUrl($c->banner) . '" data-lightbox="banner-' . $c->id . '"><img src="' . $banner  . '" alt="Avatar" class="rounded"/></a></div>',
                'operate' => $operate,
            ];
        });

        return response()->json([
            "rows" => $data,
            "total" => $total,
        ]);
    }

    /**
     * Human-readable status badge for the seller's own "Manage Categories" table, distinguishing a
     * pending/rejected request from a plain active/inactive category (docs/CHANGELOG_FEATURE_AUDIT.md
     * v1.0.6/v1.0.11).
     */
    private function requestStatusBadge(Category $c): string
    {
        if ($c->approval_status == Category::APPROVAL_PENDING) {
            return '<span class="badge bg-warning">' . labels('admin_labels.pending_approval', 'Pending Approval') . '</span>';
        }
        if ($c->approval_status == Category::APPROVAL_REJECTED) {
            return '<span class="badge bg-danger">' . labels('admin_labels.rejected', 'Rejected') . '</span>';
        }
        return $c->status == 1
            ? '<span class="badge bg-success">' . labels('admin_labels.active', 'Active') . '</span>'
            : '<span class="badge bg-danger">' . labels('admin_labels.deactive', 'Deactive') . '</span>';
    }

    /**
     * Withdraw a still-pending category request. Ownership- and status-scoped: only the requesting
     * seller's own row, and only while it's still pending - an approved/rejected request stays visible in
     * the seller's request history instead (docs/CHANGELOG_FEATURE_AUDIT.md v1.0.11).
     */
    public function destroy($id)
    {
        $seller_id = Seller::where('user_id', Auth::id())->value('id');
        $category = Category::where('id', $id)->where('requested_by_seller_id', $seller_id)->first();

        if (!$category) {
            return response()->json(['error' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }

        if ($category->approval_status != Category::APPROVAL_PENDING) {
            return response()->json(['error' => labels('seller_labels.only_pending_category_requests_can_be_deleted', 'Only pending category requests can be deleted.')]);
        }

        $category->delete();

        return response()->json(['error' => false, 'message' => labels('admin_labels.category_deleted_successfully', 'Category deleted successfully!')]);
    }


    public function getSellerCategories(Request $request)
    {
        $level = 0;
        $store_id = app(StoreService::class)->getStoreId();
        $user_id = Auth::user()->id;
        $sellerId = Seller::where('user_id', $user_id)->value('id');

        // Fetch the store
        $store = Store::find($store_id);

        // Get the pivot data for the given seller
        $seller = $store?->sellers()->where('seller_id', $sellerId)->first();

        $category_ids_str = $seller?->pivot->category_ids ?? null;
        $deliverable_type = $seller?->pivot->deliverable_type ?? null;

        // Convert category_ids string to array
        $category_ids = $category_ids_str ? explode(',', $category_ids_str) : [];

        if (empty($category_ids)) {
            return [];
        }

        // Get top-level categories only (those without a parent)
        $categories = Category::with(['children' => function ($q) use ($store_id) {
            $q->with(['children' => function ($q2) use ($store_id) {
                $q2->where('status', 1)
                    ->where('store_id', $store_id);
            }])
                ->where('status', 1)
                ->where('store_id', $store_id);
        }])
            ->whereIn('id', $category_ids)
            ->where('status', 1)
            ->where('store_id', $store_id)
            ->get();

        $filteredCategories = [];
        $language_code = app(TranslationService::class)->getLanguageCode();

        foreach ($categories as $pCat) {
            $category = $pCat->toArray();

            // Recursively format children
            $category['children'] = $this->formatSubCategories($pCat->children, $language_code, $level);
            $category['text'] = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $pCat->id, $language_code);
            $category['name'] = $category['text'];
            $category['state'] = ['opened' => true];
            $category['icon'] = "jstree-folder";
            $category['level'] = $level;
            $category['image'] = app(MediaService::class)->getMediaImageUrl($category['image']);
            $category['banner'] = app(MediaService::class)->getMediaImageUrl($category['banner']);

            $filteredCategories[] = $category;
        }

        // Add total and deliverable_type to the first item if exists
        if (!empty($filteredCategories)) {
            $filteredCategories[0]['total'] = count($categories);
            $filteredCategories[0]['deliverable_type'] = $deliverable_type;
        }

        return $filteredCategories;
    }
    private function formatSubCategories($subCategories, $language_code, $level)
    {
        return $subCategories->map(function ($category) use ($language_code, $level) {
            $category->children = $this->formatSubCategories($category->children, $language_code, $level + 1);
            $category->text = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $language_code);
            $category->name = $category->text;
            $category->state = ['opened' => true];
            $category->icon = "jstree-folder";
            $category->level = $level;
            $category->image = app(MediaService::class)->dynamic_image(app(MediaService::class)->getImageUrl($category->image, 'thumb', 'sm'), 400);
            $category->banner = app(MediaService::class)->dynamic_image(app(MediaService::class)->getImageUrl($category->banner, 'thumb', 'md'), 400);
            return $category;
        });
    }
    public function get_seller_categories(Request $request, $language_code = '')
    {
        $store_id = $request->store_id ?? app(StoreService::class)->getStoreId();
        $user_id = Auth::user()->id;
        $seller_id = Seller::where('user_id', $user_id)->value('id');

        $level = 0;
        $seller_id = $request->seller_id ?? $seller_id;
        $search = trim($request->input('search', ''));

        $seller_data = SellerStore::select('category_ids')
            ->where('store_id', $store_id)
            ->where('seller_id', $seller_id)
            ->first();

        if (!$seller_data) {
            return response()->json([
                'categories' => [],
                'total' => 0
            ]);
        }

        $category_ids = explode(",", $seller_data->category_ids);

        // Root categories only (parent_id = 0 or null)
        $categoriesQuery = Category::with(['children' => function ($q) use ($store_id) {
            $q->where('store_id', $store_id)->where('status', 1);
        }])
            ->whereIn('id', $category_ids)
            ->where('status', 1)
            ->where('store_id', $store_id)
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            });

        if ($search) {
            $categoriesQuery->where('name', 'like', '%' . $search . '%');
        }

        $categories = $categoriesQuery->get();

        $formatted = $this->formatSubCategories($categories, $language_code, $level);

        return response()->json([
            'categories' => $formatted,
            'total' => $formatted->count()
        ]);
    }

    public function get_seller_categories_filter()
    {
        $store_id = app(StoreService::class)->getStoreId();
        $user_id = Auth::user()->id;

        // Get the current seller's ID
        $seller_id = Seller::where('user_id', $user_id)->value('id');

        // Load the store
        $store = Store::find($store_id);

        // Filter the sellers relationship by seller_id before calling first()
        $category_ids_str = $store?->sellers()->where('seller_id', $seller_id)->first()?->pivot->category_ids ?? null;

        // Convert comma-separated category IDs to an array
        $category_ids = $category_ids_str ? explode(',', $category_ids_str) : [];

        // Fetch the categories by ID
        $categories = Category::whereIn('id', $category_ids)
            ->where('status', 1)
            ->where('store_id', $store_id)
            ->get();

        $language_code = app(TranslationService::class)->getLanguageCode();

        // Format the categories
        $categories = $categories->map(function ($category) use ($language_code) {
            return [
                'id' => $category->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $language_code),
                'slug' => $category->slug,
                'image' => $category->image,
                'status' => $category->status,
                'store_id' => $category->store_id,
            ];
        });

        return $categories->toArray();
    }


    public function getCategoryDetails(Request $request)
    {
        $store_id = $request->store_id ?? app(StoreService::class)->getStoreId();
        $search = trim($request->input('search'));
        $limit = (int) $request->input('limit', 10);

        $category = Category::where('name', 'like', '%' . $search . '%')
            ->where('store_id', $store_id)
            ->where('status', 1)
            ->get(['id', 'parent_id', 'name']);

        $totalCount = Category::where('name', 'like', '%' . $search . '%')
            ->where('store_id', $store_id)
            ->selectRaw('count(id) as total')
            ->first()
            ->total;
        $language_code = app(TranslationService::class)->getLanguageCode();
        $response = [
            'total' => $totalCount,
            'results' => $category->map(function ($category) use ($language_code) {
                return [
                    'id' => $category->id,
                    'text' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $language_code),
                    'parent_id' => $category->parent_id,
                ];
            }),
        ];

        return response()->json($response);
    }
}
