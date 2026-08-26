<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\Request;
use App\Traits\HandlesValidation;
use App\Services\StoreService;
class CustomFieldController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        return view('admin.pages.forms.custom_fields');
    }
    public function store(Request $request)
    {
        // dd($request);
        $rules = [
            'name' => 'required|string',
            'type' => 'required|in:text,number,file,date,radio,dropdown,checkbox,color,textarea',
            'field_length' => 'nullable|integer',
            'min' => 'nullable|integer',
            'max' => 'nullable|integer',
            'required' => 'boolean',
            'active' => 'boolean',
            'options' => 'nullable',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $store_id = app(StoreService::class)->getStoreId() ?? "";

        $options = null;

        if (in_array($request->type, ['radio', 'dropdown', 'checkbox']) && $request->filled('options')) {
            $decoded = json_decode($request->options, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $options = array_column($decoded, 'value');
            }
        }
        // dd($request->type);
        CustomField::create([
            'store_id' => $store_id,
            'name' => $request->name,
            'type' => $request->type,
            'field_length' => $request->field_length,
            'min' => $request->min,
            'max' => $request->max,
            'required' => $request->required ?? 0,
            'active' => $request->active ?? 0,
            'options' => $options,
        ]);
        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.custom_field_created_successfully', 'Custom Field created successfully')
            ]);
        }
    }


    public function list(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId() ?? '';

        $query = CustomField::where('store_id', $store_id);

        // Handle Bootstrap Table parameters
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'desc');

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $total = $query->count();

        $customFields = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($field) {
                $delete_url = route('custom_fields.edit', $field->id);
                $edit_url = route('custom_fields.destroy', $field->id);
                $action = '<div class="dropdown bootstrap-table-dropdown">
                            <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bx bx-dots-horizontal-rounded"></i>
                            </a>
                            <div class="dropdown-menu table_dropdown category_action_dropdown" aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item dropdown_menu_items" href="' . route('custom_fields.edit', $field->id) . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                                <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . route('custom_fields.destroy', $field->id) . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                            </div>
                        </div>';
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'type' => ucfirst($field->type),
                    'field_length' => $field->field_length,
                    'min' => $field->min,
                    'max' => $field->max,
                    'required' => $field->required
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>',
                    'active' => $field->active
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>',
                    'options' => in_array($field->type, ['radio', 'dropdown', 'checkbox']) && $field->options
                        ? implode(', ', is_array($field->options) ? $field->options : json_decode($field->options, true))
                        : '-',
                    'operate' => $action
                ];
            });

        return response()->json([
            'total' => $total,
            'rows' => $customFields,
        ]);
    }
    public function edit($id)
    {
        $customField = CustomField::findOrFail($id);
        return view('admin.pages.forms.update_custom_field', [
            'customField' => $customField
        ]);

    }

    public function update(Request $request, $id)
    {
        $rules = [
            'name' => 'required|string',
            'type' => 'required|in:text,number,file,date,radio,dropdown,checkbox,color,textarea',
            'field_length' => 'nullable|integer',
            'min' => 'nullable|integer',
            'max' => 'nullable|integer',
            'required' => 'boolean',
            'active' => 'boolean',
            'options' => 'nullable',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $customField = CustomField::findOrFail($id);

        $options = null;
        if (in_array($request->type, ['radio', 'dropdown', 'checkbox']) && $request->filled('options')) {
            $decoded = json_decode($request->options, true);
            if (json_last_error() == JSON_ERROR_NONE && is_array($decoded)) {
                $options = array_column($decoded, 'value');
            }
        }

        $customField->update([
            'name' => $request->name,
            'type' => $request->type,
            'field_length' => $request->field_length,
            'min' => $request->min,
            'max' => $request->max,
            'required' => $request->required ?? 0,
            'active' => $request->active ?? 0,
            'options' => $options,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.custom_field_updated_successfully', 'Custom Field updated successfully')
            ]);
        }
    }

    public function destroy($id)
    {
        $customField = CustomField::findOrFail($id);

        // First delete related entries from product_custom_field_values
        $customField = CustomField::findOrFail($id);

        // Delete related values
        $customField->productCustomFieldValues()->delete();

        // Delete the custom field
        $customField->delete();

        return response()->json([
            'error' => false,
            'message' => labels('admin_labels.field_deleted_successfully', 'Field deleted successfully!')
        ]);
    }
}