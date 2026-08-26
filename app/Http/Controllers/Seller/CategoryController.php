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


        $seller_data = SellerStore::select('category_ids')->where('seller_id', $seller_id)->where('store_id', $store_id)->get();

        if (!$seller_data) {
            return response()->json([
                "rows" => [],
                "total" => 0,
            ]);
        }

        $category_ids = explode(",", $seller_data[0]->category_ids);

        $category_data = Category::whereIn('id', $category_ids)->where('store_id', $store_id);
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
        $data = $categories->map(function ($c) use ($language_code) {
            $status = ($c->status == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Deactive</span>';
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
            return [
                'id' => $c->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $c->id, $language_code),
                'status' => $status,
                'image' => '<div><a href="' . app(MediaService::class)->getMediaImageUrl($c->image)  . '" data-lightbox="image-' . $c->id . '"><img src="' . $image  . '" alt="Avatar" class="rounded"/></a></div>',
                'banner' => '<div ><a href="' . app(MediaService::class)->getMediaImageUrl($c->banner) . '" data-lightbox="banner-' . $c->id . '"><img src="' . $banner  . '" alt="Avatar" class="rounded"/></a></div>',
            ];
        });

        return response()->json([
            "rows" => $data,
            "total" => $total,
        ]);
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
