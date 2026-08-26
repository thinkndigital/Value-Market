<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attribute;
use Illuminate\Http\Request;
use App\Models\Attribute_values;
use App\Models\Category;
use Illuminate\Routing\Controller;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\StoreService;
class AttributeController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $attributes = Attribute::where('status', 1)->get();
        return view('admin.pages.forms.attributes', ['attributes' => $attributes]);
    }


    public function store(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();

        $rules = [
            'attribute_value' => 'required|array',
            'category_id' => 'required|exists:categories,id',
        ];

        if ($request->attribute_id == 0) {
            $rules['name'] = 'required';
        } else {
            $rules['attribute_id'] = 'required|exists:attributes,id';
        }

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $filteredValues = array_filter($request->attribute_value, fn($val) => !is_null($val) && $val !== '');
        if (empty($filteredValues)) {
            return response()->json([
                'error' => true,
                'error_message' => 'Please provide at least one valid attribute value.'
            ]);
        }

        $name = $request->name ?? Attribute::find($request->attribute_id)?->name ?? '';

        if (!$name) {
            return response()->json([
                'error' => true,
                'error_message' => 'Attribute name not found.'
            ]);
        }

        // Check if any value already exists
        $existingAttribute = Attribute::with('attribute_values')
            ->where('category_id', $request->category_id)
            ->where('name', $name)
            ->first();

        if ($existingAttribute) {
            $existingValues = $existingAttribute->attribute_values->pluck('values')->toArray();
            foreach ($filteredValues as $value) {
                if (in_array($value, $existingValues)) {
                    return response()->json([
                        'error' => true,
                        'message' => labels('admin_labels.combination_already_exist', 'Combination already exist')
                    ], 422);
                }
            }
        }

        // Create new attribute if not exists
        if (!$existingAttribute) {
            $existingAttribute = Attribute::create([
                'name' => $name,
                'category_id' => $request->category_id,
                'status' => '1',
                'store_id' => $storeId,
            ]);
        }

        // Create values
        foreach ($filteredValues as $i => $val) {
            $valueData = [
                'value' => $val,
                'attribute_id' => $existingAttribute->id,
                'swatche_type' => $request->swatche_type[$i] ?? null,
                'swatche_value' => $request->swatche_value[$i] ?? null,
                'status' => '1',
            ];
            Attribute_values::create($valueData);
        }

        return response()->json([
            'error' => false,
            'message' => labels('admin_labels.attribute_added_successfully', 'Attribute added successfully'),
            'addAttribute' => true
        ]);
    }

    public function list()
    {
        $storeId = app(StoreService::class)->getStoreId();
        $search = trim(request('search'));
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $limit = request("limit", 10);
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;

        $attributes = Attribute::with('attribute_values', 'category')
            ->where('store_id', $storeId)
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhereHas('attribute_values', function ($q) use ($search) {
                            $q->where('value', 'like', '%' . $search . '%');
                        });
                });
            });

        $total = $attributes->count();

        $attributes = $attributes->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $attributes = $attributes->map(function ($attribute) {
            $languageCode = app(TranslationService::class)->getLanguageCode();
            return [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'value' => $attribute->attribute_values->pluck('value')->implode(','),
                'category' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $attribute->category->id, $languageCode) ?? "",
                'status' => '<select class="form-select status_dropdown change_toggle_status ' . ($attribute->status == 1 ? 'active_status' : 'inactive_status') . '" data-id="' . $attribute->id . '" data-url="/attribute/update_status/' . $attribute->id . '" aria-label="">
                <option value="1" ' . ($attribute->status == 1 ? 'selected' : '') . '>Active</option>
                <option value="0" ' . ($attribute->status == 0 ? 'selected' : '') . '>Deactive</option>
            </select>',
            ];
        });

        return response()->json([
            "rows" => $attributes,
            "total" => $total,
        ]);
    }
    public function update_status($id)
    {
        $attribute = Attribute::findOrFail($id);

        $attribute->status = $attribute->status == '1' ? '0' : '1';
        $attribute->save();
        return response()->json(['success' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.')]);
    }

    public function getAttributes(Request $request)
    {
        $attributes = Attribute::with([
            'attribute_values' => function ($query) {
                $query->select('id', 'value', 'attribute_id');
            }
        ])
            ->where('status', 1)
            ->where('category_id', $request->category_id)
            ->get(['id', 'name']);
        // dd($attributes);
        $attributesRefind = [];

        foreach ($attributes as $attribute) {
            // dd($attribute->attribute_values);
            $values = [];

            foreach ($attribute->attribute_values as $value) {
                $values[] = [
                    'id' => $value->id,
                    'text' => $value->value,
                    'data_values' => $value->value,
                    'attr_id' => $attribute->id,
                ];
            }

            $attributesRefind[$attribute->name] = $values;
        }

        if (!empty($attributesRefind)) {
            $response['error'] = false;
            $response['data'] = $attributesRefind;
        } else {
            $response['error'] = true;
            $response['data'] = [];
        }

        return response()->json($response);
    }
}
