<?php

namespace App\Http\Controllers\Seller;

use App\Models\Seller;
use Illuminate\Http\Request;
use App\Models\PickupLocation;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Traits\HandlesValidation;

class PickupLocationController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        return view('seller.pages.forms.pickup_locations');
    }

    public function store(Request $request)
    {

        $user_id = Auth::user()->id;
        $seller_id = Seller::where('user_id', $user_id)->value('id');
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
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $location_data['seller_id'] = $seller_id ?? "";
        $location_data['pickup_location'] = $request->pickup_location ?? "";
        $location_data['name'] = $request->name ?? "";
        $location_data['email'] = $request->email ?? "";
        $location_data['phone'] = $request->phone ?? "";
        $location_data['city'] = $request->city ?? "";
        $location_data['country'] = $request->country ?? "";
        $location_data['state'] = $request->state ?? "";
        $location_data['pincode'] = $request->pincode ?? "";
        $location_data['address'] = $request->address ?? "";
        $location_data['address2'] = $request->address2 ?? "";
        $location_data['longitude'] = $request->longitude ?? "";
        $location_data['latitude'] = $request->latitude ?? "";
        $location_data['status'] = 1;


        PickupLocation::create($location_data);

        if ($request->ajax()) {
            return response()->json(['message' => labels('admin_labels.pickup_location_created_successfully', 'Pickup Location created successfully')]);
        }
    }

    public function list(Request $request)
    {
        $user_id = Auth::user()->id;
        $seller_id = Seller::where('user_id', $user_id)->value('id');

        // Pagination and sorting settings
        $search = trim($request->input('search'));
        $offset = $request->input('pagination_offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        // Build the query using Eloquent
        $query = PickupLocation::query();

        // Search filters
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('pickup_location', 'LIKE', "%$search%")
                    ->orWhere('email', 'LIKE', "%$search%")
                    ->orWhere('phone', 'LIKE', "%$search%");
            });
        }

        // Seller-specific filter
        if ($seller_id) {
            $query->where('seller_id', $seller_id);
        }

        // Count total records
        $total = $query->count();

        // Fetch the data with pagination
        $location_data = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        // Format the data
        $bulkData = [
            'total' => $total,
            'rows' => $location_data->map(function ($row) {
                return [
                    'id' => $row->id,
                    'pickup_location' => $row->pickup_location,
                    'name' => $row->name,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'address' => $row->address,
                    'address2' => $row->address2,
                    'city' => $row->city,
                    'state' => $row->state,
                    'country' => $row->country,
                    'pincode' => $row->pincode,
                ];
            })->toArray()
        ];

        return response()->json($bulkData);
    }
}
