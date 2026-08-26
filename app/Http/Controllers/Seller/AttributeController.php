<?php

namespace App\Http\Controllers\Seller;

use App\Models\Attribute;
use Illuminate\Http\Request;
use App\Models\Attribute_values;
use Illuminate\Routing\Controller;
use App\Services\StoreService;
class AttributeController extends Controller
{
    public function index()
    {
        $attributes = Attribute::where('status', 1)->get();
        return view('seller.pages.tables.attributes', ['attributes' => $attributes]);
    }

    public function list(Request $request)
    {
        $store_id = !empty($request->store_id) ? $request->store_id : app(StoreService::class)->getStoreId();
        $search = trim($request->search);
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';
        $limit = $request->limit ?? 10;
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;

        // Check if attribute_value_ids and attribute_ids are present in the request
        $attribute_value_ids = $request->attribute_value_ids ? explode(',', $request->attribute_value_ids) : [];
        $attribute_ids = $request->attribute_ids ? explode(',', $request->attribute_ids) : [];

        // Fetch attributes with applied filters
        $attributes = Attribute::where('store_id', $store_id)->where('status', 1)
            ->with('attribute_values')
            ->when($search, function ($query) use ($search) {
                return $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%')
                    ->orWhereHas('attribute_values', function ($query) use ($search) {
                        $query->where('value', 'like', '%' . $search . '%');
                    });
            })
            ->when(!empty($attribute_ids), function ($query) use ($attribute_ids) {
                // Filter by attribute_ids if provided
                return $query->whereIn('id', $attribute_ids);
            })
            ->when(!empty($attribute_value_ids), function ($query) use ($attribute_value_ids) {
                // Filter by attribute_value_ids if provided
                return $query->whereHas('attribute_values', function ($query) use ($attribute_value_ids) {
                    $query->whereIn('id', $attribute_value_ids);
                });
            });

        // Get the total count before applying limit and offset
        $total = $attributes->count();

        // Apply sorting, pagination, and fetch the data
        $attributes = $attributes->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        // Format the attributes data
        $attributes = $attributes->map(function ($attribute) {
            $status = ($attribute->status == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Deactive</span>';
            return [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'attribute_value_id' => $attribute->attribute_values->pluck('id')->implode(','),
                'value' => $attribute->attribute_values->pluck('value')->implode(','),
                'status' => $status,
                'status_code' => $attribute->status,
            ];
        });

        // Return the response with attributes and total count
        return response()->json([
            "rows" => $attributes,
            "total" => $total,
        ]);
    }

    public function getAttributes(Request $request)
    {
        $attributes = Attribute::with(['attribute_values' => function ($query) {
            $query->select('id', 'value', 'attribute_id');
        }])
            ->where('status', 1)
            ->where('category_id', $request->category_id)
            ->get(['id', 'name']);
        // dd($attributes);
        $attributes_refind = [];

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

            $attributes_refind[$attribute->name] = $values;
        }

        if (!empty($attributes_refind)) {
            $response['error'] = false;
            $response['data'] = $attributes_refind;
        } else {
            $response['error'] = true;
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function getAttributeValue(Request $request)
    {
        $store_id = $request->input('store_id', app(StoreService::class)->getStoreId());
        $search = trim($request->input('search'));
        $attribute_id = $request->input('attribute_id');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        // Start query
        $query = Attribute_values::with('attribute')
            ->where('status', 1)
            ->whereHas('attribute', function ($q) use ($store_id) {
                $q->where('store_id', $store_id)->where('status', 1);
            });

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                    ->orWhere('swatche_value', 'like', "%{$search}%");
            });
        }

        // Attribute ID filter
        if ($attribute_id) {
            $query->where('attribute_id', $attribute_id);
        }

        // Get total count for pagination
        $totalCount = $query->count();

        // Fetch data with pagination
        $attributes = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        // Format result
        $data = $attributes->map(function ($attr) {
            return [
                'id' => $attr->id,
                'attribute_id' => $attr->attribute_id,
                'filterable' => $attr->filterable,
                'value' => $attr->value,
                'swatche_type' => $attr->swatche_type,
                'swatche_value' => $attr->swatche_value,
                'status' => $attr->status,
                'attribute_name' => optional($attr->attribute)->name,
            ];
        });

        return response()->json([
            'error' => $data->isEmpty(),
            'message' => $data->isEmpty() ? 'Attributes Not Found' : 'Attributes Retrieved Successfully',
            'total' => $totalCount,
            'data' => $data,
        ]);
    }
}
