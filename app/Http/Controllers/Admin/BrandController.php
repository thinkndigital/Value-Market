<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use App\Models\Language;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        return view('admin.pages.forms.brands', compact('languages'));
    }


    public function store(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();

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
        $brandData['status'] = 1;
        $brandData['store_id'] = $storeId;
        unset($brandData['_method']);
        unset($brandData['_token']);

        $brand = new Brand();
        $brand->fill($brandData);
        $brand->save();

        // Return response
        if ($request->ajax()) {
            return response()->json(['message' => labels('admin_labels.brand_created_successfully', 'Brand created successfully')]);
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
        $languageCode = app(TranslationService::class)->getLanguageCode();

        $brandData = Brand::when($search, function ($query) use ($search, $languageCode) {
            $jsonPath = "$." . $languageCode;

            return $query->whereRaw(
                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, ?))) LIKE ?",
                [$jsonPath, '%' . strtolower($search) . '%']
            );
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

        $data = $brands->map(function ($b) use ($languageCode) {
            $editUrl = route('brands.edit', $b->id);
            $deleteUrl = route('brands.destroy', $b->id);
            $action = '<div class="dropdown bootstrap-table-dropdown">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </a>
                    <div class="dropdown-menu table_dropdown brand_action_dropdown" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item dropdown_menu_items" href="' . $editUrl . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                        <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $deleteUrl . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                    </div>
                </div>';
            $image = route('admin.dynamic_image', [
                'url' => app(MediaService::class)->getMediaImageUrl($b->image),
                'width' => 60,
                'quality' => 90
            ]);
            return [
                'id' => $b->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Brand::class, 'name', $b->id, $languageCode),
                'operate' => $action,
                        'status' => ($b->status == 2)
                ? '<select class="form-select brand_status_dropdown change_toggle_status" data-id="' . $b->id . '" data-url="/admin/brand/update_status/' . $b->id . '" aria-label="">' .
                    '<option value="2" selected>Not Approved</option>' .
                    '<option value="1">Approve</option>' .
                '</select>'
                : '<select class="form-select brand_status_dropdown change_toggle_status ' . ($b->status == 1 ? 'active_status' : 'inactive_status') . '" data-id="' . $b->id . '" data-url="/admin/brand/update_status/' . $b->id . '" aria-label="">' .
                    '<option value="1" ' . ($b->status == 1 ? 'selected' : '') . '>Active</option>' .
                    '<option value="0" ' . ($b->status == 0 ? 'selected' : '') . '>Deactive</option>' .
                '</select>',
                'image' => '<div class=""><a href="' . app(MediaService::class)->getMediaImageUrl($b->image) . '" data-lightbox="image-' . $b->id . '"><img src="' . $image . '" alt="Avatar" class="rounded"/></a></div>',
            ];
        });

        return response()->json([
            "rows" => $data,
            "total" => $total,
        ]);
    }


    public function update_status($id)
    {
        $brand = Brand::findOrFail($id);

        if (isForeignKeyInUse(Product::class, 'brand', $id)) {
            return response()->json(['status_error' => labels('admin_labels.you_can_not_deactivate_this_brand_because_it_is_associated_with_product', 'You cannot deactivate this brand because it is associated with products')]);
        } else {
            $brand->status = $brand->status == '1' ? '0' : '1';
            $brand->save();
            return response()->json(['success' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.')]);
        }
    }

    public function destroy($id)
    {
        $brand = Brand::find($id);
        if (isForeignKeyInUse(Product::class, 'brand', $id)) {
            return response()->json(['error' => labels('admin_labels.you_can_not_delete_this_brand_because_it_is_associated_with_product', 'You cannot delete this brand because it is associated with products')]);
        } else {
            if ($brand) {
                $brand->delete();
                return response()->json(['error' => false, 'message' => labels('admin_labels.brand_deleted_successfully', 'Brand deleted Successfully')]);
            } else {
                return response()->json(['error' => labels('admin_labels.data_not_found', 'Data Not Found')]);
            }
        }
    }

    public function bulk_upload()
    {
        return view('admin.pages.forms.brand_bulk_upload');
    }

    public function process_bulk_upload(Request $request)
    {

        if (!$request->hasFile('upload_file')) {
            return response()->json(['error' => 'true', 'message' => labels('admin_labels.please_choose_file', 'Please Choose File')]);
        }

        // Validate allowed mime types
        $allowedMimeTypes = [
            'text/x-comma-separated-values',
            'text/comma-separated-values',
            'application/x-csv',
            'text/x-csv',
            'text/csv',
            'application/csv',
        ];

        $uploadFile = $request->file('upload_file');
        $uploadedMimeType = $uploadFile->getClientMimeType();

        if (!in_array($uploadedMimeType, $allowedMimeTypes)) {
            return response()->json(['error' => 'true', 'message' => labels('admin_labels.invalid_file_format', 'Invalid File Format')]);
        }

        $csv = $_FILES['upload_file']['tmp_name'];
        $temp = 0;
        $temp1 = 0;
        $handle = fopen($csv, "r");

        $type = $request->type;

        if ($type == 'upload') {
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp != 0) {
                    if (empty($row[0])) {
                        return response()->json(['error' => 'true', 'message' => 'Name is empty at row ' . $temp]);
                    }


                    $brandName = trim($row[0]);
                    $brandName = stripslashes($brandName);

                    $decodedBrandName = json_decode($brandName, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return response()->json(['error' => 'true', 'message' => 'Invalid JSON format in name at row ' . $temp]);
                    }


                    if (!isset($decodedBrandName['en']) || empty($decodedBrandName['en'])) {
                        return response()->json(['error' => 'true', 'message' => 'English name is missing in JSON at row ' . $temp]);
                    }


                    if (empty($row[1])) {
                        return response()->json(['error' => 'true', 'message' => 'Image is empty at row ' . $temp]);
                    }
                    if (empty($row[2])) {
                        return response()->json(['error' => 'true', 'message' => 'Store ID is empty at row ' . $temp]);
                    }
                }
                $temp++;
            }

            fclose($handle);
            $handle = fopen($csv, "r");

            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp1 !== 0) {
                    $brandName = trim($row[0]);
                    $brandName = stripslashes($brandName);

                    $decodedBrandName = json_decode($brandName, true);

                    $data = [
                        'name' => json_encode($decodedBrandName, JSON_UNESCAPED_UNICODE),
                        'slug' => generateSlug($decodedBrandName['en'] ?? '', 'brands'),
                        'image' => $row[1],
                        'status' => 1,
                        'store_id' => $row[2],
                    ];

                    Brand::create($data);
                }
                $temp1++;
            }

            fclose($handle);
            return response()->json(['error' => 'false', 'message' => 'Brand Uploaded Successfully']);
        } else { // bulk_update
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                // dd($row[0]);
                if ($temp != 0) {
                    if (!empty($row[0])) {
                        if (!isExist(['id' => $row[0]], Brand::class)) {
                            return response()->json(['error' => 'true', 'message' => labels('admin_labels.brand_not_exist_please_provide_another_brand_id_at_row', 'Brand not exist please provide another brand id at row') . $temp]);
                        }
                    }
                }
                $temp++;
            }
            fclose($handle);
            $handle = fopen($csv, "r");
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp1 !== 0) {
                    $brandId = $row[0];
                    $brands = fetchDetails(Brand::class, ['id' => $brandId], '*');

                    if (!$brands->isEmpty()) {
                        $data = [];
                        if (!empty($row[1])) {
                            $brandName = trim($row[1]);
                            $brandName = stripslashes($brandName);

                            $decodedBrandName = json_decode($brandName, true);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return response()->json(['error' => 'true', 'message' => "Invalid JSON format in name at row {$temp1}"]);
                            }

                            $data['name'] = json_encode($decodedBrandName, JSON_UNESCAPED_UNICODE);

                            if (isset($decodedBrandName['en']) && !empty($decodedBrandName['en'])) {
                                $data['slug'] = generateSlug($decodedBrandName['en'], 'brands');
                            }
                        } else {
                            $data['name'] = $brands[0]['name'];
                        }
                        $data['image'] = !empty($row[2]) ? $row[2] : $brands[0]['image'];

                        Brand::where('id', $brandId)->update($data);
                    }
                }
                $temp1++;
            }
            fclose($handle);
            return response()->json(['error' => 'false', 'message' =>  labels('admin_labels.brand_updated_successfully', 'Brand Updated Successfully')]);
        }
    }

    public function edit($data)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languages = Language::all();
        $data = Brand::where('store_id', $storeId)
            ->find($data);
        if ($data === null || empty($data)) {
            return view('admin.pages.views.no_data_found');
        } else {
            return view('admin.pages.forms.update_brand', [
                'data' => $data,
                'languages' => $languages
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'name' => 'required|string',
            'translated_brand_name' => 'sometimes|array',
            'translated_brand_name.*' => 'nullable|string',
            'image' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        // Find the brand
        $brand = Brand::find($id);

        $existingBrand = Brand::where('store_id', app(StoreService::class)->getStoreId())
            ->where('id', '!=', $brand->id)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) = ?", [$request->name])
            ->first();

        if ($existingBrand) {
            return response()->json([
                'error' => true,
                'message' => 'Brand name already exists.',
                'language_message_key' => 'brand_name_exists',
            ], 400);
        }

        if (!$brand) {
            return response()->json(['error' => 'Brand not found.'], 404);
        }

        $existingTranslations = json_decode($brand->name, true) ?? [];

        $existingTranslations['en'] = $request->name;

        // Check for translated names and merge them
        if (!empty($request->translated_brand_name)) {
            $existingTranslations = array_merge($existingTranslations, $request->translated_brand_name);
        }

        // Encode updated translations to store as JSON
        $brand->name = json_encode($existingTranslations, JSON_UNESCAPED_UNICODE);
        $brand->image = $request->image;
        $brand->slug = generateSlug($existingTranslations['en'], 'brands', 'slug', $brand->slug);
        $brand->status = 1;

        // Save the updated brand
        $brand->save();

        // Return response
        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.brand_updated_successfully', 'Brand Updated Successfully'),
                'location' => route('brands.index')
            ]);
        }

        return redirect()->route('brands.index')->with('success', labels('admin_labels.brand_updated_successfully', 'Brand Updated Successfully'));
    }


    public function get_brand_list($search = "", $offset = 0, $limit = 25, $storeId, $ids = "", $languageCode = "")
    {

        $query = Brand::where('store_id', $storeId)->where('status', '1');

        if (!empty($ids)) {
            // Convert the comma-separated ids string to an array
            $idsArray = explode(',', $ids);
            $query->whereIn('id', $idsArray);
        }
        if (!empty($search)) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        $total = $query->count();

        $brands = $query->skip($offset)->take($limit)->get()->toArray();

        // dd($language_key);

        if (!empty($brands)) {
            for ($i = 0; $i < count($brands); $i++) {
                // dd($brands[$i]);
                $translatedName = app(TranslationService::class)->getDynamicTranslation(Brand::class, 'name', $brands[$i]['id'], $languageCode);
                $brands[$i]['image'] = app(MediaService::class)->getMediaImageUrl($brands[$i]['image']);
                $brands[$i]['store_id'] = intval($brands[$i]['store_id']);
                $brands[$i]['status'] = intval($brands[$i]['status']);
                $brands[$i]['name'] = $translatedName;
                unset($brands[$i]['created_at']);
                unset($brands[$i]['updated_at']);
            }
        }

        // dd($brands);
        $brandsData = [
            'error'   => empty($brands),
            'message' => empty($brands) ? labels('admin_labels.brands_not_found', 'Brands not found') : labels('admin_labels.brands_retrived_successfully', 'Brands Retrived Successfully'),
            'language_message_key' => empty($brands) ? 'brands_not_found' : 'brands_retrived_successfully',
            'total'   => $total,
            'data'    => empty($brands) ? [] : $brands,
        ];
        return $brandsData;
    }
    public function delete_selected_data(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:brands,id'
        ]);

        // Initialize an array to store the IDs that can't be deleted
        $nonDeletableIds = [];

        // Loop through each brand ID
        foreach ($request->ids as $id) {
            // Check if the brand is associated with products
            if (isForeignKeyInUse(Product::class, 'brand', $id)) {
                // Add the ID to the list of non-deletable IDs
                $nonDeletableIds[] = $id;
            }
        }

        // If there are non-deletable IDs, return them in the response
        if (!empty($nonDeletableIds)) {
            return response()->json([
                'error' => labels(
                    'admin_labels.cannot_delete_brand_associated_with_products',
                    'You cannot delete these brands: ' . implode(', ', $nonDeletableIds) . ' because they are associated with products'
                ),
                'non_deletable_ids' => $nonDeletableIds
            ], 401);
        }

        // Delete the brands if no association is found
        Brand::destroy($request->ids);

        return response()->json(['message' => 'Selected brands deleted successfully.']);
    }
}
