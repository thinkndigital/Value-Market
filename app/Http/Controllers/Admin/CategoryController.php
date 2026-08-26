<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\CategorySliders;
use App\Models\Language;
use App\Models\Product;
use App\Models\SellerStore;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\StoreService;
use App\Services\MediaService;
class CategoryController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languages = Language::all();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        $categories = Category::where('status', 1)->where('store_id', $storeId)->orderBy('id', 'desc')->get();

        return view('admin.pages.forms.categories', ['categories' => $categories, 'languages' => $languages, 'language_code' => $languageCode]);
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
            'status' => 1,
            'store_id' => $storeId,
        ];

        Category::create($categoryData);

        $successMessage = labels('admin_labels.category_created_successfully', 'Category created successfully');
        return $request->ajax()
            ? response()->json(['message' => $successMessage])
            : redirect()->back()->with('success', $successMessage);
    }



    public function edit($id)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languages = Language::all();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        $categories = Category::where('status', 1)
            ->where('store_id', $storeId)
            ->where('id', '!=', $id)
            ->get();

        $data = Category::where('store_id', $storeId)
            ->find($id);

        if ($data === null || empty($data)) {
            return view('admin.pages.views.no_data_found');
        } else {
            return view('admin.pages.forms.update_category', [
                'data' => $data,
                'categories' => $categories,
                'languages' => $languages,
                'language_code' => $languageCode
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'name' => 'required|string',
            'category_image' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $category = Category::findOrFail($id);
        $categoryData = $request->only(array_keys($rules));

        $newName = $categoryData['name'];
        $currentTranslations = json_decode($category->name, true);
        $currentName = $currentTranslations['en'] ?? $category->name;
        $currentSlug = $category->slug;

        $storeId = app(StoreService::class)->getStoreId();
        $duplicate = Category::where('store_id', $storeId)
            ->where('id', '!=', $category->id)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) = ?", [$newName])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'error' => true,
                'message' => 'Category name already exists.',
                'language_message_key' => 'category_name_exists',
            ], 400);
        }

        $translations = ['en' => $newName];
        if ($request->filled('translated_category_name')) {
            $translations = array_merge($translations, $request->translated_category_name);
        }

        // Update data
        $updateData = [
            'name' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            'image' => $categoryData['category_image'],
            'banner' => $request->banner,
            'parent_id' => $request->input('parent_id', 0),
            'slug' => ($currentName !== $newName)
                ? generateSlug($newName, 'categories', 'slug', $currentSlug, $currentName)
                : $currentSlug,
            'style' => $request->input('category_style', ''),
            'status' => 1,
        ];

        $category->update($updateData);

        $message = labels('admin_labels.category_updated_successfully', 'Category updated successfully');
        return $request->ajax()
            ? response()->json(['message' => $message, 'location' => route('categories.index')])
            : redirect()->back()->with('success', $message);
    }



    public function update_status($id)
    {
        $category = Category::findOrFail($id);
        $tables = [Product::class, SellerStore::class];
        $columns = ['category_id', 'category_ids'];
        if (isForeignKeyInUse($tables, $columns, $id)) {
            return response()->json([
                'status_error' => labels('admin_labels.cannot_deactivate_category_associated_with_products_seller', 'You cannot deactivate this category because it is associated with products and seller.')
            ]);
        } else {
            $category->status = $category->status == '1' ? '0' : '1';
            $category->save();
            return response()->json(['success' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.')]);
        }
    }


    public function destroy($id)
    {
        // Find the category by ID
        $category = Category::find($id);

        // Define the tables and columns to check for foreign key constraints
        $tables = [Product::class, SellerStore::class];
        $columns = ['category_id', 'category_ids'];

        // Check if there are foreign key constraints
        if (isForeignKeyInUse($tables, $columns, $id)) {
            return response()->json([
                'error' => labels('admin_labels.cannot_delete_category_associated_with_products_seller', 'You cannot delete this category because it is associated with products and seller.')
            ]);
        }

        // Check if the category ID exists in the comma-separated category_ids of the category_sliders table
        $isCategoryInSliders = CategorySliders::where('category_ids', 'LIKE', '%' . $id . '%')->exists();

        if ($isCategoryInSliders) {
            return response()->json([
                'error' => labels('admin_labels.cannot_delete_category_associated_with_sliders', 'You cannot delete this category because it is associated with category sliders.')
            ]);
        }

        // Attempt to delete the category
        if ($category && $category->delete()) {
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.category_deleted_successfully', 'Category deleted successfully!')
            ]);
        }

        return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
    }

    public function list(Request $request)
    {
        // Get the store ID
        $storeId = app(StoreService::class)->getStoreId();

        // Capture input parameters with defaults
        $search = trim($request->input('search', ''));
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $offset = request()->input('search') || (request('pagination_offset')) ? (request('pagination_offset')) : 0;
        $limit = $request->input('limit', 10);
        $status = $request->input('status', '');
        // Build the query for categories
        $categoryData = Category::where('store_id', $storeId);
        $languageCode = app(TranslationService::class)->getLanguageCode();

        
        // Apply search filter if provided
        if (!empty($search)) {
            $categoryData = Category::when($search, function ($query) use ($search, $languageCode) {
                $jsonPath = "$." . $languageCode;
    
                return $query->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, ?))) LIKE ?",
                    [$jsonPath, '%' . strtolower($search) . '%']
                );
            });
        }

        // Apply status filter only if status is provided
        if (!is_null($status) && $status !== '') {
            $categoryData->where('status', $status);
        }

        // Count total records before applying pagination
        $total = $categoryData->count();

        // Retrieve paginated data with sorting
        $categories = $categoryData->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Format data for response
        $data = $categories->map(function ($c) use($languageCode) {

            // Format 'status' field with HTML select

            $status =  ($c->status == 2)
                ? '<select class="form-select brand_status_dropdown change_toggle_status" data-id="' . $c->id . '" data-url="admin/categories/update_status/' . $c->id . '" aria-label="">' .
                '<option value="2" selected>Not Approved</option>' .
                '<option value="1">Approve</option>' .
                '</select>'
                : '<select class="form-select brand_status_dropdown change_toggle_status ' . ($c->status == 1 ? 'active_status' : 'inactive_status') . '" data-id="' . $c->id . '" data-url="admin/categories/update_status/' . $c->id . '" aria-label="">' .
                '<option value="1" ' . ($c->status == 1 ? 'selected' : '') . '>Active</option>' .
                '<option value="0" ' . ($c->status == 0 ? 'selected' : '') . '>Deactive</option>' .
                '</select>';


            // Format 'image' and 'banner' fields with HTML tags
            $image = route('admin.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($c->image),
                'width' => 60,
                'quality' => 90
            ]);
            $banner = route('admin.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($c->banner),
                'width' => 60,
                'quality' => 90
            ]);
            $image = '<div class="d-flex justify-content-around"><a href="' . app(MediaService::class)->getMediaImageUrl($c->image) . '" data-lightbox="image-' . $c->id . '"><img src="' . $image . '" alt="Avatar" class="rounded"/></a></div>';
            $banner = '<div><a href="' . app(MediaService::class)->getMediaImageUrl($c->banner) . '" data-lightbox="banner-' . $c->id . '"><img src="' . $banner . '" alt="Avatar" class="rounded"/></a></div>';

            // Format 'operate' field with dropdown menu HTML
            $action = '<div class="dropdown bootstrap-table-dropdown">
                <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-dots-horizontal-rounded"></i>
                </a>
                <div class="dropdown-menu table_dropdown category_action_dropdown" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item dropdown_menu_items" href="' . route('categories.update', $c->id) . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                    <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . route('admin.categories.destroy', $c->id) . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                </div>
            </div>';

            return [
                'id' => $c->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $c->id, $languageCode),
                'status' => $status,
                'image' => $image,
                'banner' => $banner,
                'operate' => $action,
            ];
        });

        // Return response as JSON
        return response()->json([
            "rows" => $data->toArray(), // Convert collection to array for JSON response
            "total" => $total,           // Return the total count
        ]);
    }



    public function get_seller_categories_filter()
    {
        $storeId = app(StoreService::class)->getStoreId();

        $store = Store::find($storeId);
        $categoryIdsString = $store?->sellers()->first()?->pivot->category_ids ?? null;

        $categoryIds = $categoryIdsString ? explode(',', $categoryIdsString) : [];

        $categories = Category::whereIn('id', $categoryIds)
            ->where('status', 1)
            ->where('store_id', $storeId)
            ->get();

        $languageCode = app(TranslationService::class)->getLanguageCode();

        $categories = $categories->map(function ($category) use ($languageCode) {
            return [
                'id' => $category->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $languageCode),
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
        $storeId = app(StoreService::class)->getStoreId();
        $search = trim($request->input('search'));
        $limit = (int) $request->input('limit', 10);

        $category = Category::where('name', 'like', '%' . $search . '%')
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->limit($limit)
            ->get(['id', 'name']);

        $totalCount = Category::where('name', 'like', '%' . $search . '%')
            ->where('store_id', $storeId)
            ->selectRaw('count(id) as total')
            ->first()
            ->total;
        $response = [
            'total' => $totalCount,
            'results' => $category->map(function ($category) {
                $languageCode = app(TranslationService::class)->getLanguageCode();
                return [
                    'id' => $category->id,
                    'text' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $languageCode),
                ];
            }),
        ];

        return response()->json($response);
    }

    public function getCategories(
        $id = null,
        $limit = null,
        $offset = null,
        $sort = 'row_order',
        $order = 'ASC',
        $hasChildOrItem = 'true',
        $slug = '',
        $ignoreStatus = '',
        $sellerId = '',
        $storeId = '',
        $languageCode = ""
    ) {
        $level = 0;

        $storeId = app(StoreService::class)->getStoreId();

        $query = Category::query();

        // Apply store filters
        if (!empty($storeId)) {
            $query->where('store_id', $storeId);
        }
        if (!empty($storeId)) {
            $query->where('store_id', $storeId);
        }

        // Filter by ID
        if (!empty($id)) {
            $query->where('id', $id);
            if ($ignoreStatus != 1) {
                $query->where('status', 1);
            }
        } else {
            if ($ignoreStatus != 1) {
                $query->where('status', 1);
            }
        }

        // Filter by slug
        if (!empty($slug)) {
            $query->where('slug', $slug);
        }

        // If has_child_or_item = false, filter categories with children or products
        if ($hasChildOrItem === 'false') {
            // Use whereHas for children or products
            $query->where(function ($q) {
                $q->whereHas('children')
                    ->orWhereHas('products');  // Assuming Category has products() relation
            });
        }

        // Pagination
        if (!is_null($offset)) {
            $query->skip($offset);
        }
        if (!is_null($limit)) {
            $query->take($limit);
        }

        // Sorting
        $query->orderBy($sort, $order);

        // Eager load children
        $categories = $query->with([
            'children' => function ($q) {
                $q->where('status', 1);
            }
        ])->get();

        $countRes = $categories->count();

        // Map categories to add translations and other metadata
        $categories = $categories->map(function ($category) use ($languageCode, $level) {
            $category->children = $this->formatSubCategories($category->children, $languageCode, $level + 1);

            $category->text = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $languageCode);
            $category->name = $category->text;
            $category->state = ['opened' => true];
            $category->icon = "jstree-folder";
            $category->level = $level;
            $category->image = app(MediaService::class)->dynamic_image(app(MediaService::class)->getImageUrl($category->image, 'thumb', 'sm'), 400);
            $category->banner = app(MediaService::class)->dynamic_image(app(MediaService::class)->getImageUrl($category->banner, 'thumb', 'md'), 400);

            return $category;
        });

        if ($categories->isNotEmpty()) {
            $categories[0]->total = $countRes;
        }

        return Response::json(compact('categories', 'countRes'));
    }
    private function formatSubCategories($subCategories, $languageCode, $level)
    {
        return $subCategories->map(function ($category) use ($languageCode, $level) {
            $category->children = $this->formatSubCategories($category->children, $languageCode, $level + 1);
            $category->text = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $languageCode);
            $category->name = $category->text;
            $category->state = ['opened' => true];
            $category->icon = "jstree-folder";
            $category->level = $level;
            $category->image = app(MediaService::class)->dynamic_image(app(MediaService::class)->getImageUrl($category->image, 'thumb', 'sm'), 400);
            $category->banner = app(MediaService::class)->dynamic_image(app(MediaService::class)->getImageUrl($category->banner, 'thumb', 'md'), 400);
            return $category;
        });
    }
    public function get_categories($id = null, $limit = '', $offset = '', $sort = 'row_order', $order = 'ASC', $hasChildOrItem = 'true', $slug = '', $ignoreStatus = '', $sellerId = '', $storeId = '', $search = '', $ids = '', $languageCode = "")
    {
        $languageCode = !empty($languageCode) ? $languageCode : app(TranslationService::class)->getLanguageCode();
        $storeId = isset($storeId) && !empty($storeId) ? $storeId : app(StoreService::class)->getStoreId();
        $idsArray = !empty($ids) ? explode(',', $ids) : [];
        // Build base query
        $query = Category::with([
            'children' => function ($q) {
                $q->with('children');
            }
        ]);
        if (!empty($storeId)) {
            $query->where('store_id', $storeId);
        }
        if (!empty($storeId)) {
            $query->where('store_id', $storeId);
        }
        if (!empty($idsArray)) {
            $query->whereIn('id', $idsArray);
        } else {
            if (!empty($id)) {
                $category = Category::find($id);
                if ($category && $category->parent_id != 0) {
                    $query->where('id', $category->parent_id);
                } else {
                    $query->where('id', $id);
                }
            } else {
                $query->where(function ($q) use ($ignoreStatus) {
                    $q->where(function ($q2) {
                        $q2->whereNull('parent_id')->orWhere('parent_id', 0);
                    });
                    if ($ignoreStatus != 1) {
                        $q->where('status', 1);
                    }
                });
            }
        }
        if (!empty($slug)) {
            $query->where('slug', $slug);
        }
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('children', function ($sub) use ($search) {
                        $sub->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }
        $total = $query->count();
        if (!empty($limit) || $limit === 0) {
            $query->offset($offset)->limit($limit);
        }
        $query->orderBy($sort, $order);
        $categories = $query->get();
        $formatted = $this->formatSubCategories($categories, $languageCode, 0);
        return response()->json(['categories' => $formatted, 'total' => $total]);
    }

    public function subCategories($id, $level, $languageCode = '')
    {
        // dd($languageCode);
        $level = $level + 1;
        $category = Category::find($id);
        $categories = $category->children;
        $languageCode = isset($languageCode) && !empty($languageCode) ? $languageCode : app(TranslationService::class)->getLanguageCode();
        $i = 0;
        foreach ($categories as $p_cat) {
            // dd('here');
            $categories[$i]->children = $this->subCategories($p_cat->id, $level, $languageCode);
            $categories[$i]->text = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $p_cat->id, $languageCode);
            $categories[$i]->name = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $p_cat->id, $languageCode);
            $categories[$i]->state = ['opened' => true];
            $categories[$i]->level = $level;
            $p_cat['image'] = app(MediaService::class)->getMediaImageUrl($p_cat['image']);
            $p_cat['banner'] = app(MediaService::class)->getMediaImageUrl($p_cat['banner']);
            $i++;
        }

        return $categories;
    }

    public function getSellerCategories(Request $request)
    {
        $level = 0;
        $storeId = app(StoreService::class)->getStoreId();
        $sellerId = $request->seller_id ?? '';

        // Fetch the store
        $store = Store::find($storeId);

        // Get the pivot data for the given seller
        $seller = $store?->sellers()->where('seller_id', $sellerId)->first();

        $categoryIdsString = $seller?->pivot->category_ids ?? null;
        $deliverableType = $seller?->pivot->deliverable_type ?? null;

        // Convert category_ids string to array
        $categoryIds = $categoryIdsString ? explode(',', $categoryIdsString) : [];

        if (empty($categoryIds)) {
            return [];
        }

        // Get top-level categories only (those without a parent)
        $categories = Category::with([
            'children' => function ($q) use ($storeId) {
                $q->with([
                    'children' => function ($q2) use ($storeId) {
                        $q2->where('status', 1)
                            ->where('store_id', $storeId);
                    }
                ])
                    ->where('status', 1)
                    ->where('store_id', $storeId);
            }
        ])
            ->whereIn('id', $categoryIds)
            ->where('status', 1)
            ->where('store_id', $storeId)
            ->get();

        $filteredCategories = [];
        $languageCode = app(TranslationService::class)->getLanguageCode();

        foreach ($categories as $pCat) {
            $category = $pCat->toArray();

            // Recursively format children
            $category['children'] = $this->formatSubCategories($pCat->children, $languageCode, $level);
            $category['text'] = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $pCat->id, $languageCode);
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
            $filteredCategories[0]['deliverable_type'] = $deliverableType;
        }

        return $filteredCategories;
    }



    public function categoryOrder()
    {
        $storeId = app(StoreService::class)->getStoreId();

        // Fetch only main categories (where parent_id is null or 0)
        $categories = Category::where('status', 1)
            ->where('store_id', $storeId)
            ->where(function ($query) {
                $query->whereNull('parent_id')
                    ->orWhere('parent_id', 0);
            })
            ->orderBy('row_order', 'asc')
            ->get();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        return view('admin.pages.tables.category_order', ['categories' => $categories, 'language_code' => $languageCode]);
    }
    public function updateCategoryOrder(Request $request)
    {

        $categoryIds = $request->input('category_id');
        $i = 0;

        foreach ($categoryIds as $categoryId) {
            $data = [
                'row_order' => $i
            ];

            Category::where('id', $categoryId)->update($data);

            $i++;
        }
        return response()->json(['error' => false, 'message' => 'Category Order Saved !']);
    }

    public function category_slider()
    {

        $storeId = app(StoreService::class)->getStoreId();
        $categories = Category::where('status', 1)->where('store_id', $storeId)->orderBy('id', 'desc')->get();
        $languages = Language::all();

        return view('admin.pages.forms.category_sliders', ['categories' => $categories, 'languages' => $languages]);
    }

    public function category_data(Request $request)
    {

        $storeId = app(StoreService::class)->getStoreId();
        $search = $request->input('term');
        $limit = (int) $request->input('limit', 10);


        // Query categories using where clause with name condition
        $query = Category::query()
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $categories = $query->paginate($limit);
        // Map categories to format for response
        $formattedCategories = $categories->getCollection()->map(function ($category) {
            $languageCode = app(TranslationService::class)->getLanguageCode();
            $level = 0;
            return [
                'id' => $category->id,
                'text' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $languageCode),
                'image' => app(MediaService::class)->getMediaImageUrl($category->image),
                'parent_id' => $category->parent_id ?? "",
            ];
        });
        // Create a new collection instance with formatted categories
        $formattedCollection = new Collection($formattedCategories);

        // Construct the response
        $response = [
            'total' => $categories->total(),
            'results' => $formattedCollection,
        ];

        return response()->json($response);
    }

    public function store_category_slider(Request $request)
    {

        $storeId = app(StoreService::class)->getStoreId();

        $rules = [
            'title' => 'required',
            'translated_category_slider_title' => 'nullable|array',
            'translated_category_slider_title.*' => 'nullable|string',
            'category_slider_style' => 'required',
            'background_color' => 'required',
            'banner_image' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $categorySliderData = $request->all();
        unset($categorySliderData['_method']);
        unset($categorySliderData['_token']);
        // Handle translations
        $translations = ['en' => $categorySliderData['title']];

        if (!empty($categorySliderData['translated_category_slider_title'])) {
            $translations = array_merge($translations, $categorySliderData['translated_category_slider_title']);
        }

        // Encode translations as JSON
        $categorySliderData['title'] = json_encode($translations, JSON_UNESCAPED_UNICODE);

        $categorySliderData['category_ids'] = isset($request->category_ids) ? implode(',', $request->category_ids) : '';

        // Rename the "category_slider_style" key to "style"
        $categorySliderData['style'] = $categorySliderData['category_slider_style'];
        unset($categorySliderData['category_slider_style']);
        unset($categorySliderData['translated_category_slider_title']);

        $categorySliderData['status'] = 1;
        $categorySliderData['store_id'] = isset($storeId) ? $storeId : '';

        $categorySliderData['banner_image'] = $request->banner_image;
        CategorySliders::create($categorySliderData);

        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.category_slider_created_successfully', 'Category slider created successfully')
            ]);
        }
    }

    public function category_sliders_list(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $search = trim($request->input('search'));
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $status = $request->input('status', '');

        $query = CategorySliders::where('store_id', $storeId);

        // Apply status filter
        if ($status !== '') {
            $query->where('status', $status);
        }

        // Handle search on title or category name
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%");

                // Get category IDs by name match
                $matchingCategoryIds = Category::where('name', 'like', "%$search%")->pluck('id')->toArray();

                if (!empty($matchingCategoryIds)) {
                    // Search in category_ids using PHP after fetching results
                    $q->orWhere(function ($q2) use ($matchingCategoryIds) {
                        $q2->where(function ($innerQuery) use ($matchingCategoryIds) {
                            foreach ($matchingCategoryIds as $categoryId) {
                                $innerQuery->orWhereRaw("FIND_IN_SET(?, category_ids)", [$categoryId]);
                            }
                        });
                    });
                }
            });
        }

        // Get total count before pagination
        $total = $query->count();

        // Paginate and fetch results
        $sliders = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $languageCode = app(TranslationService::class)->getLanguageCode();

        $data = $sliders->map(function ($s) use ($languageCode) {
            $deleteUrl = route('admin.category_sliders.destroy', $s->id);
            $editUrl = route('admin.category_sliders.update', $s->id);

            $categoryIds = explode(',', $s->category_ids);
            $categoryNames = Category::whereIn('id', $categoryIds)
                ->get()
                ->map(fn($category) => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category->id, $languageCode))
                ->implode(', ');

            $action = '<div class="dropdown bootstrap-table-dropdown">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </a>
                    <div class="dropdown-menu table_dropdown category_slider_action_dropdown">
                        <a class="dropdown-item dropdown_menu_items" href="' . $editUrl . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                        <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $deleteUrl . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                    </div>
                </div>';

            return [
                'id' => $s->id,
                'title' => app(TranslationService::class)->getDynamicTranslation(CategorySliders::class, 'title', $s->id, $languageCode),
                'categories' => $categoryNames,
                'status' => '<select class="form-select status_dropdown change_toggle_status ' . ($s->status == 1 ? 'active_status' : 'inactive_status') . '" data-id="' . $s->id . '" data-url="/admin/category_sliders/update_status/' . $s->id . '" aria-label="">
                        <option value="1" ' . ($s->status == 1 ? 'selected' : '') . '>Active</option>
                        <option value="0" ' . ($s->status == 0 ? 'selected' : '') . '>Deactive</option>
                        </select>',
                'operate' => $action,
            ];
        });

        return response()->json([
            "rows" => $data,
            "total" => $total,
        ]);
    }



    public function update_category_slider_status($id)
    {
        $categorySlider = CategorySliders::findOrFail($id);
        $categorySlider->status = $categorySlider->status == '1' ? '0' : '1';
        $categorySlider->save();
        return response()->json(['success' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.')]);
    }

    public function category_slider_destroy($id)
    {
        $category = CategorySliders::find($id);

        if ($category->delete()) {
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.slider_deleted_successfully', 'Slider deleted successfully!')
            ]);
        } else {
            return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
        }
    }

    public function category_slider_edit($data)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languages = Language::all();
        $categorySliders = CategorySliders::where('status', 1)->where('store_id', $storeId)->get();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        $categories = Category::where('status', 1)->where('store_id', $storeId)->orderBy('id', 'desc')->get();

        $data = CategorySliders::where('store_id', $storeId)
            ->find($data);

        if ($data === null || empty($data)) {
            return view('admin.pages.views.no_data_found');
        } else {
            return view('admin.pages.forms.update_category_slider', [
                'data' => $data,
                'category_sliders' => $categorySliders,
                'categories' => $categories,
                'languages' => $languages,
                'language_code' => $languageCode
            ]);
        }
    }


    public function category_slider_update(Request $request, $data)
    {

        // dd($request);
        $rules = [
            'title' => 'required',
            'category_slider_style' => 'required',
            'background_color' => 'required',
            'banner_image' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $slider = CategorySliders::find($data);


        $new_name = $request->title;
        $translations = ['en' => $new_name];

        if (!empty($request->translated_category_slider_title)) {
            $translations = array_merge($translations, $request->translated_category_slider_title);
        }
        $categorySliderData['title'] = json_encode($translations, JSON_UNESCAPED_UNICODE);
        $categorySliderData['category_ids'] = isset($request->category_ids) ? implode(',', $request->category_ids) : '';
        $categorySliderData['style'] = $request->category_slider_style;
        $categorySliderData['status'] = 1;
        $categorySliderData['banner_image'] = $request->banner_image;
        $slider->update($categorySliderData);

        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.slider_updated_successfully', 'Slider updated successfully'),
                'location' => route('category_slider.index')
            ]);
        }
    }
    public function delete_selected_data(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id'
        ]);

        $tables = [Product::class, SellerStore::class];
        $columns = ['category_id', 'category_ids'];

        // Initialize an array to store the IDs that can't be deleted
        $nonDeletableIds = [];

        foreach ($request->ids as $id) {
            if (isForeignKeyInUse($tables, $columns, $id)) {
                // Collect the ID that cannot be deleted
                $nonDeletableIds[] = $id;
            }
        }
        // Check if there are any non-deletable IDs
        if (!empty($nonDeletableIds)) {
            return response()->json([
                'error' => labels('admin_labels.cannot_delete_category_associated_with_products_seller', 'You cannot delete these categories: ' . implode(', ', $nonDeletableIds) . ' because they are associated with products and sellers.'),
                'non_deletable_ids' => $nonDeletableIds
            ], 401);
        }

        // Proceed to delete the categories that are deletable
        Category::destroy($request->ids);

        return response()->json(['message' => 'Selected categories deleted successfully.']);
    }
    public function delete_selected_slider_data(Request $request)
    {

        // Validate the incoming request
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:category_sliders,id'
        ]);

        CategorySliders::destroy($request->ids);

        return response()->json(['message' => 'Selected sliders deleted successfully.']);
    }
    public function bulk_upload()
    {
        return view('admin.pages.forms.category_bulk_upload');
    }

    public function process_bulk_upload(Request $request)
    {
        if (!$request->hasFile('upload_file')) {
            return response()->json(['error' => 'true', 'message' => 'Please choose a file.']);
        }

        $allowedMimeTypes = [
            'text/x-comma-separated-values',
            'text/comma-separated-values',
            'application/x-csv',
            'text/x-csv',
            'text/csv',
            'application/csv',
        ];

        $uploadedFile = $request->file('upload_file');
        $uploadedMimeType = $uploadedFile->getClientMimeType();

        if (!in_array($uploadedMimeType, $allowedMimeTypes)) {
            return response()->json(['error' => 'true', 'message' => 'Invalid File Format.']);
        }

        $csv = $_FILES['upload_file']['tmp_name'];
        $type = $request->type;
        $temp = 0;
        $temp1 = 0;
        // dd($request);
        $handle = fopen($csv, "r");

        if ($type == 'upload') {
            // First pass: validation
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp != 0) {
                    // dd('here');
                    $categoryNameJson = trim($row[0]);
                    if (empty($categoryNameJson)) {
                        return response()->json(['error' => 'true', 'message' => 'Name is empty at row ' . $temp]);
                    }

                    $decodedName = json_decode($categoryNameJson, true);
                    // dd($decodedName);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return response()->json(['error' => 'true', 'message' => 'Invalid JSON format at row ' . $temp]);
                    }

                    if (empty($decodedName['en'])) {
                        return response()->json(['error' => 'true', 'message' => 'English name is missing at row ' . $temp]);
                    }

                    if (Category::where('name->en', $decodedName['en'])->exists()) {
                        return response()->json(['error' => 'true', 'message' => 'English name already exists at row ' . $temp]);
                    }

                    if (empty($row[1])) {
                        return response()->json(['error' => 'true', 'message' => 'Image is empty at row ' . $temp]);
                    }

                    if (empty($row[2])) {
                        return response()->json(['error' => 'true', 'message' => 'Store ID is empty at row ' . $temp]);
                    }
                    if (!empty($row[3])) {
                        $parentId = $row[3];
                        $storeId = $row[2];

                        $parentCategory = Category::where('id', $parentId)
                            ->where('store_id', $storeId)
                            ->first();

                        if (!$parentCategory) {
                            return response()->json([
                                'error' => 'true',
                                'message' => 'Parent ID ' . $parentId . ' not found for Store ID ' . $storeId . ' at row ' . $temp
                            ]);
                        }
                    }
                }
                $temp++;
            }

            fclose($handle);
            $handle = fopen($csv, "r");

            // Second pass: insert
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp1 !== 0) {
                    $decodedName = json_decode(trim($row[0]), true);
                    // Validate parent_id again before inserting
                    $parentId = $row[3] ?? null;
                    $storeId = $row[2] ?? null;

                    if (!empty($parentId)) {
                        $parentCategory = Category::where('id', $parentId)
                            ->where('store_id', $storeId)
                            ->first();

                        if (!$parentCategory) {
                            return response()->json([
                                'error' => 'true',
                                'message' => 'Parent ID ' . $parentId . ' not found for Store ID ' . $storeId . ' at row ' . $temp1
                            ]);
                        }
                    }
                    $data = [
                        'name' => json_encode($decodedName, JSON_UNESCAPED_UNICODE),
                        'slug' => generateSlug($decodedName['en'], 'categories'),
                        'image' => $row[1],
                        'store_id' => $row[2],
                        'parent_id' => $parentId,
                        'banner' => $row[4] ?? null,
                        'status' => 1,
                    ];
                    Category::create($data);
                }
                $temp1++;
            }

            fclose($handle);
            return response()->json(['error' => 'false', 'message' => 'Categories uploaded successfully.']);
        }

        // BULK UPDATE
        else {
            fgetcsv($handle);

            $rowNumber = 1;
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $categoryId = $row[0];
                if (empty($categoryId)) {
                    return response()->json(['error' => 'true', 'message' => 'Category ID is missing at row ' . $rowNumber]);
                }

                if (!Category::where('id', $categoryId)->exists()) {
                    return response()->json(['error' => 'true', 'message' => 'Category not found at row ' . $rowNumber]);
                }

                $rowNumber++;
            }
            fclose($handle);
            $handle = fopen($csv, "r");

            // Second pass: update categories
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp1 !== 0) {
                    $categoryId = $row[0];
                    $category = Category::find($categoryId);

                    if ($category) {
                        $data = [];

                        if (!empty($row[1])) {
                            $decodedName = json_decode(trim($row[1]), true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return response()->json(['error' => 'true', 'message' => 'Invalid JSON name at row ' . $temp1]);
                            }

                            $data['name'] = json_encode($decodedName, JSON_UNESCAPED_UNICODE);

                            if (!empty($decodedName['en'])) {
                                $existing = Category::where('id', '!=', $categoryId)
                                    ->where('name->en', $decodedName['en'])
                                    ->exists();

                                if ($existing) {
                                    return response()->json(['error' => 'true', 'message' => 'English name already used at row ' . $temp1]);
                                }

                                $data['slug'] = generateSlug($decodedName['en'], 'categories');
                            }
                        }

                        if (!empty($row[2])) {
                            $data['image'] = $row[2];
                        }

                        if (!empty($row[3])) {
                            $parentId = $row[3];
                            $storeId = $category->store_id;

                            $parentCategory = Category::where('id', $parentId)
                                ->where('store_id', $storeId)
                                ->first();

                            if (!$parentCategory) {
                                return response()->json([
                                    'error' => 'true',
                                    'message' => 'Parent ID ' . $parentId . ' not found for Store ID ' . $storeId . ' at row ' . $temp1
                                ]);
                            }

                            $data['parent_id'] = $parentId;
                        }

                        if (!empty($row[4])) {
                            $data['banner'] = $row[4];
                        }

                        $category->update($data);
                    }
                }
                $temp1++;
            }

            fclose($handle);
            return response()->json(['error' => 'false', 'message' => 'Categories updated successfully.']);
        }
    }
}
