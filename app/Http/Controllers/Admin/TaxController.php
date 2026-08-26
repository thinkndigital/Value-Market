<?php

namespace App\Http\Controllers\Admin;

use App\Models\Language;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;

class TaxController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $languages = Language::all();
        return view('admin.pages.forms.taxes', ['languages' => $languages]);
    }

    public function store(Request $request)
    {
        $rules = [
            'title' => 'required|unique:taxes,title',
            'translated_tax_name' => 'sometimes|array',
            'translated_tax_name.*' => 'nullable|string',
            'percentage' => 'required|numeric|between:0,100',
        ];

        $messages = [
            'title.unique' => 'Tax with the same name already exists.',
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages)) {
            return $response;
        }

        $validated = $request->only(array_keys($rules));

        // Check for duplicate tax (based on JSON "en" value)
        $existing_tax = Tax::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) = ?", $validated['title'])
            ->first();

        if ($existing_tax) {
            return response()->json([
                'error' => true,
                'message' => 'Tax already exists.',
                'language_message_key' => 'tax_exists',
            ], 400);
        }

        // Prepare translations
        $translations = ['en' => $validated['title']];
        if (!empty($validated['translated_tax_name'])) {
            $translations = array_merge($translations, $validated['translated_tax_name']);
        }

        $validated['title'] = json_encode($translations, JSON_UNESCAPED_UNICODE);
        $validated['status'] = 1;
        unset($validated['translated_tax_name']);

        Tax::create($validated);

        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.tax_created_successfully', 'Tax created successfully')
            ]);
        }

        // Optional redirect for non-AJAX if needed
        return redirect()->back()->with('success', 'Tax created successfully.');
    }


    public function list()
    {
        $search = trim(request('search'));
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;
        $limit = (request('limit')) ? request('limit') : "10";

        $taxesQuery = Tax::query();

        if ($search) {
            $taxesQuery->where('title', 'like', '%' . $search . '%')
                ->orWhere('percentage', 'like', '%' . $search . '%');
        }

        $total = $taxesQuery->count();

        // Execute the query and get the paginated results
        $taxes = $taxesQuery->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($t) {
                $delete_url = route('taxes.destroy', $t->id);
                $action = '<div class="dropdown bootstrap-table-dropdown">
                            <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                               <i class="bx bx-dots-horizontal-rounded"></i>
                            </a>
                            <div class="dropdown-menu table_dropdown tax_action_dropdown" aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item dropdown_menu_items edit-tax" data-id="' . $t->id . '"><i class="bx bx-pencil mx-2"></i>Edit</a>
                                <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $delete_url . '"><i class="bx bx-trash mx-2"></i></i>Delete</a>
                            </div>';
                $language_code = app(TranslationService::class)->getLanguageCode();
                return [
                    'id' => $t->id,
                    'title' => app(TranslationService::class)->getDynamicTranslation(Tax::class, 'title', $t->id, $language_code),
                    'percentage' => $t->percentage,
                    'operate' => $action,
                    'status' => '<select class="form-select status_dropdown change_toggle_status ' . ($t->status == 1 ? 'active_status' : 'inactive_status') . '" data-id="' . $t->id . '" data-url="/admin/tax/update_status/' . $t->id . '" aria-label="">
                                  <option value="1" ' . ($t->status == 1 ? 'selected' : '') . '>Active</option>
                                  <option value="0" ' . ($t->status == 0 ? 'selected' : '') . '>Deactive</option>
                              </select>',
                ];
            });

        return response()->json([
            "rows" => $taxes,
            "total" => $total,
        ]);
    }


    public function update_status($id)
    {
        $tax = Tax::findOrFail($id);

        // Check if there are products associated with this tax

        if (isForeignKeyInUse(Product::class, 'tax', $id)) {
            return response()->json([
                'status_error' => labels('admin_labels.cannot_deactivate_tax_associated_with_products', 'You cannot deactivate this tax because it is associated with products.')
            ]);
        } else {
            $tax->status = $tax->status == '1' ? '0' : '1';
            $tax->save();
            return response()->json(['success' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.')]);
        }
    }

    public function destroy($id)
    {
        $tax = Tax::find($id);

        if (isForeignKeyInUse(Product::class, 'tax', $id)) {
            return response()->json([
                'error' => labels('admin_labels.cannot_delete_tax_associated_with_products', 'You cannot delete this tax because it is associated with products.')
            ]);
        } else {
            if ($tax->delete()) {
                return response()->json([
                    'error' => false,
                    'message' => labels('admin_labels.tax_deleted_successfully', 'Tax deleted successfully!')
                ]);
            } else {
                return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
            }
        }
    }

    public function edit($id)
    {
        $tax = Tax::find($id);
        if (!$tax) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')], 404);
        }

        return response()->json($tax);
    }

    public function update(Request $request, $id)
    {
        // dd($request);
        $tax = Tax::find($id);
        if (!$tax) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')], 404);
        } else {

            $rules = [
                'edit_title' => 'required|unique:taxes,title,' . $id,
                'edit_percentage' => 'required|numeric|between:0,100',
            ];

            $messages = [
                'edit_title.unique' => 'Tax with the same name already exists.',
            ];

            if ($response = $this->HandlesValidation($request, $rules ,$messages)) {
                return $response;
            }

            $existing_tax = Tax::where('id', '!=', $tax->id)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) = ?", [$request->edit_title])
                ->first();

            if ($existing_tax) {
                return response()->json([
                    'error' => true,
                    'message' => 'Tax already exists.',
                    'language_message_key' => 'tax_exists',
                ], 400);
            }
            $existingTranslations = json_decode($tax->edit_title, true) ?? [];

            $existingTranslations['en'] = $request->edit_title;

            if (!empty($request->translated_tax_name)) {
                $existingTranslations = array_merge($existingTranslations, $request->translated_tax_name);
            }
            // dd($existingTranslations);
            // Encode updated translations to store as JSON
            $tax->title = json_encode($existingTranslations, JSON_UNESCAPED_UNICODE);
            // $tax->title = $request->input('edit_title');
            $tax->percentage = $request->input('edit_percentage');
            // dd($tax);
            $tax->save();

            if ($request->ajax()) {
                return response()->json([
                    'message' => labels('admin_labels.tax_updated_successfully', 'Tax updated successfully')
                ]);
            }
        }
    }


    public function getTaxes(Request $request)
    {
        $search = trim($request->search) ?? "";
        $taxes = Tax::where('title', 'like', '%' . $search . '%')->where('status', 1)->get();
        $language_code = app(TranslationService::class)->getLanguageCode();
        $data = array();
        foreach ($taxes as $tax) {
            $data[] = array("id" => $tax->id, "text" =>  app(TranslationService::class)->getDynamicTranslation(Tax::class, 'title', $tax->id, $language_code) . ' (' . $tax->percentage . '%)');
        }
        return response()->json($data);
    }
    public function delete_selected_data(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:taxes,id'
        ]);

        $nonDeletableIds = [];

        foreach ($request->ids as $id) {

            if (isForeignKeyInUse(Product::class, 'tax', $id)) {

                $nonDeletableIds[] = $id;
            }
        }
        if (!empty($nonDeletableIds)) {
            return response()->json([
                'error' => labels(
                    'admin_labels.cannot_delete_tax_associated_with_products',
                    'You cannot delete these tax: ' . implode(', ', $nonDeletableIds) . ' because they are associated with products'
                ),
                'non_deletable_ids' => $nonDeletableIds
            ], 401);
        }
        Tax::destroy($request->ids);

        return response()->json(['message' => 'Selected taxes deleted successfully.']);
    }
}
