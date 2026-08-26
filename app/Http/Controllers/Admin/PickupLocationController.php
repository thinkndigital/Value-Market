<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Libraries\Shiprocket;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HandlesValidation;

class PickupLocationController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        return view('admin.pages.tables.manage_pickup_locations');
    }

    public function store(Request $request)
    {
        $rules = [
            'pickup_location' => 'required',
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required|numeric',
            'address' => 'required',
            'address2' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'pincode' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        // Get user and seller IDs
        $user_id = Auth::id();
        $seller_id = $request->input('seller_id') ?: Seller::where('user_id', $user_id)->value('id');
        // Prepare data for storage and API request
        $pickup_location_data = [
            'seller_id' => $seller_id,
            'pickup_location' => $request['pickup_location'],
            'name' => $request['name'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'address' => $request['address'],
            'address2' => $request['address2'],
            'city' => $request['city'],
            'state' => $request['state'],
            'country' => $request['country'],
            'pincode' => $request['pincode'],
            'latitude' => $request['latitude'],
            'longitude' => $request['longitude'],
        ];
        // dd($pickup_location_data);
        // Send request to Shiprocket API
        $shiprocket_data = $pickup_location_data;
        unset($shiprocket_data['pincode']); // Only remove for API request
        $shiprocket_data['pin_code'] = $request['pincode']; // Add pin_code for API

        $shiprocket = new Shiprocket();
        $data = $shiprocket->add_pickup_location($shiprocket_data);
        if (isset($data['success']) && $data['success'] == true) {
            PickupLocation::create($pickup_location_data);
        }
        // Store the pickup location in the database

        // Return response based on the request type
        return $request->ajax()
            ? response()->json(['message' => labels('admin_labels.pickup_location_created_successfully', 'Pickup Location created successfully')])
            : $data;
    }


    public function list(Request $request, $from_app = false)
    {
        $search = trim($request->input('search', ''));
        $offset = $search || request('pagination_offset') ? request('pagination_offset', 0) : 0;
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'desc');
        $sellerId = $request->input('seller_id');
        $status = $request->input('status');

        // Eloquent query builder
        $query = PickupLocation::query();

        // Apply search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('pickup_location', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        // Apply filters
        if (!empty($sellerId)) {
            $query->where('seller_id', $sellerId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        // Get total count before pagination
        $total = $query->count();

        // Fetch paginated results
        $results = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        // Format response
        $rows = [];

        foreach ($results as $row) {
            $tempRow = [
                'id' => $row->id,
                'seller_id' => $row->seller_id ?? '',
                'pickup_location' => $row->pickup_location ?? '',
                'name' => $row->name ?? '',
                'email' => $row->email ?? '',
                'phone' => $row->phone ?? '',
                'address' => $row->address ?? '',
                'address2' => $row->address2 ?? '',
                'city' => $row->city ?? '',
                'state' => $row->state ?? '',
                'status' => $row->status ?? '',
                'country' => $row->country ?? '',
                'pincode' => $row->pincode ?? '',
            ];

            if (!$from_app) {
                $tempRow['verified'] = '<select class="form-select status_dropdown change_toggle_status ' . ($row->status == 1 ? 'pickup_location_active_status' : 'pickup_location_inactive_status') . '" data-id="' . $row->id . '" data-url="/admin/pickup_location/update_status/' . $row->id . '">
                    <option value="1" ' . ($row->status == 1 ? 'selected' : '') . '>Verified</option>
                    <option value="0" ' . ($row->status == 0 ? 'selected' : '') . '>Unverified</option>
                </select>';

                $delete_url = route('admin.pickup_location.destroy', $row->id);
                $tempRow['operate'] = '<div class="dropdown bootstrap-table-dropdown">
                <a href="#" class="text-dark" data-bs-toggle="dropdown">
                    <i class="bx bx-dots-horizontal-rounded"></i>
                </a>
                <div class="dropdown-menu table_dropdown">
                    <a class="dropdown-item dropdown_menu_items edit-pickup_location" data-id="' . $row->id . '" data-bs-toggle="modal" data-bs-target="#edit_modal"><i class="bx bx-pencil mx-2"></i> Edit</a>
                    <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $delete_url . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                </div>
            </div>';
            }

            $rows[] = $tempRow;
        }

        return [
            'total' => $total,
            'rows' => $rows,
        ];
    }

    public function update_status($id)
    {
        $res = PickupLocation::findOrFail($id);
        $pickup_location = $res['pickup_location'];


        if (isForeignKeyInUse(Product::class, 'pickup_location', $pickup_location)) {
            return response()->json(['status_error' => labels('admin_labels.cannot_deactivate_location', 'You cannot deactivate this pickup location because it is associated with products.')]);
        } else {

            $res->status = $res->status == '1' ? '0' : '1';
            $res->save();
            return response()->json(['success' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.')]);
        }
    }

    public function edit($id)
    {

        $data = PickupLocation::find($id);

        return response()->json($data);
    }
    public function update(Request $request, $id)
    {

        $rules = [
            'pickup_location' => 'required',
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'pincode' => 'required',
            'address' => 'required',
            'address2' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
            'seller_id' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $res = PickupLocation::findOrFail($id);

        $location_data = $request->all();

        unset($location_data['_method']);
        unset($location_data['_token']);

        $res->update($location_data);

        if ($request->ajax()) {
            return response()->json(['message' => labels('admin_labels.pickup_location_updated_successfully', 'Pickup location updated successfully')]);
        }
    }

    public function destroy($id)
    {
        $res = PickupLocation::find($id);
        $pickup_location = $res['pickup_location'];

        if (isForeignKeyInUse(Product::class, 'pickup_location', $pickup_location)) {
            return response()->json(['error' => labels('admin_labels.cannot_delete_location', 'You cannot delete this pickup location because it is associated with products.')]);
        } else {
            if ($res) {
                $res->delete();
                return response()->json(['error' => false, 'message' => labels('admin_labels.pickup_location_deleted_successfully', 'Pickup location deleted successfully!')]);
            } else {
                return response()->json(['error' => labels('admin_labels.data_not_found', 'Data Not Found')]);
            }
        }
    }
}
