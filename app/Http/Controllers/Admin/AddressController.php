<?php

namespace App\Http\Controllers\Admin;

use App\Models\Address;
use App\Models\Area;
use App\Models\City;
use App\Models\Zipcode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Traits\HandlesValidation;

class AddressController extends Controller
{
    use HandlesValidation;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {


        $rules = [
            'mobile' => 'numeric',
            'alternate_mobile' => 'numeric',
            'pincode_name' => 'numeric',
            'pincode' => 'numeric',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $addressData = [];

        if (request()->filled('user_id')) {
            $addressData['user_id'] = request('user_id');
        }
        if (request()->filled('id')) {
            $addressData['id'] = request('id');
        }
        if (request()->filled('type')) {
            $addressData['type'] = request('type');
        }
        if (request()->filled('name')) {
            $addressData['name'] = request('name');
        }
        if (request()->filled('mobile')) {
            $addressData['mobile'] = request('mobile');
        }
        $addressData['country_code'] = (request()->filled('country_code') && is_numeric(request('country_code'))) ? request('country_code') : 0;
        if (request()->filled('alternate_mobile')) {
            $addressData['alternate_mobile'] = request('alternate_mobile');
        }
        if (request()->filled('address')) {
            $addressData['address'] = request('address');
        }
        if (request()->filled('landmark')) {
            $addressData['landmark'] = request('landmark');
        }
        $city = fetchDetails(City::class, ['id' => request('city_id')], 'name');
        $area = fetchDetails(Area::class, ['id' => request('area_id')], 'name');

        if (request()->filled('general_area_name')) {
            $addressData['area'] = $request->input('general_area_name', '');
        }
        if (request()->filled('edit_general_area_name')) {
            $addressData['area'] = $request->input('edit_general_area_name', '');
        }
        if (request()->filled('city_id')) {
            $addressData['city_id'] = $request->input('city_id', 0);
            // $addressData['city'] = isset($city) && !empty($city) ?  $city[0]->name : '';
            $addressData['city'] = isset($city) && !empty($city) ?  json_decode($city[0]->name)->en : '';
        }

        if (request()->filled('city_name')) {

            $addressData['city'] = $request->input('city_name');
        }
        if (request()->filled('area_name')) {
            $addressData['area'] = $request->input('area_name', !empty($area[0]->name) ?? '');
        }
        if (request()->filled('other_city')) {
            $addressData['city'] = $request->input('other_city', $city[0]->name);
        }
        if (request()->filled('other_areas')) {
            $addressData['area'] = $request->input('other_areas', !empty($area[0]->name) ?? '');
        }
        if (request()->filled('pincode_name') || request()->filled('pincode')) {
            $addressData['system_pincode'] = $request->input('pincode_name') ? 0 : 1;
            $addressData['pincode'] =  $request->input('pincode_name', $request->input('pincode'));
        }
        if (request()->filled('state')) {
            $addressData['state'] = $request->input('state');
        }
        if (request()->filled('country')) {
            $addressData['country'] = $request->input('country');
        }
        if (request()->filled('latitude')) {
            $addressData['latitude'] = $request->input('latitude');
        }
        if (request()->filled('longitude')) {
            $addressData['longitude'] = $request->input('longitude');
        }
        if (request()->filled('id')) {

            if (request()->filled('is_default') && ($request->input('is_default') == true || $request->input('is_default') == 1)) {
                $address = fetchDetails(Address::class, ['id' => $request->input('id')], '*');
                updateDetails(['is_default' => '0'], ['user_id' => $address[0]->user_id], Address::class);
                updateDetails(['is_default' => '1'], ['id' => $request->input('id')], Address::class);
            }
            updateDetails($addressData, ['id' => $request->input('id')], Address::class);
        } else {

            $lastInsertId = Address::insertGetId($addressData);
            if (request()->filled('is_default') && ($request->input('is_default') == true || $request->input('is_default') == 1)) {
                updateDetails(['is_default' => '0'], ['user_id' => request('user_id')], Address::class);
                updateDetails(['is_default' => '1'], ['id' => $lastInsertId], Address::class);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $address = Address::find($id);

        if ($address->delete()) {
            return response()->json(['error' => false, 'message' => labels('admin_labels.address_deleted_successfully', 'Address Deleted Successfully')]);
        } else {
            return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
        }
    }

    public function getAddress($user_id, $id = null, $fetch_latest = false, $is_default = false)
    {
        $query = Address::query();

        if (!is_null($user_id)) {
            $query->where('user_id', $user_id);
        }

        if (!is_null($id)) {
            $query->where('id', $id);
        }

        if ($is_default) {
            $query->where('is_default', true);
        }

        $query->orderByDesc('id');

        if ($fetch_latest) {
            $query->limit(1);
        }

        $addresses = $query->get();

        $addresses = $addresses->map(
            function ($address) {
                $zipcode = $address->pincode ?? null;
                $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
                $zipcode_id = !$zipcode_id->isEmpty() ? $zipcode_id[0]->id : '';
                $zipcode_id = $zipcode_id ?? '';

                $minimumFreeDeliveryOrderAmount = fetchDetails(Zipcode::class, ['id' => $zipcode_id], ['minimum_free_delivery_order_amount', 'delivery_charges']);

                $address->minimum_free_delivery_order_amount = optional($minimumFreeDeliveryOrderAmount)->minimum_free_delivery_order_amount ?? 0;
                $address->delivery_charges = optional($minimumFreeDeliveryOrderAmount)->delivery_charges ?? 0;
                $address->area_id = $address->area_id ?? "";
                $address->city_id = $address->city_id ?? "";
                $address->latitude = $address->latitude ?? "";
                $address->longitude = $address->longitude ?? "";
                $address->landmark = $address->landmark ?? "";
                return $address;
            }
        );
        return $addresses;
    }
}
