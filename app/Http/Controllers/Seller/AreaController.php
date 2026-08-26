<?php

namespace App\Http\Controllers\Seller;

use App\Models\City;
use App\Models\Language;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Zone;
use App\Models\Zipcode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\TranslationService;
use App\Services\StoreService;
class AreaController extends Controller
{

    // zipcode

    public function zipcodes()
    {
        $languages = Language::all();
        return view('seller.pages.tables.zipcodes', ['languages' => $languages]);
    }


    public function zipcode_list(Request $request, $language_code = '')
    {
        $search = trim($request->input('search'));
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $offset = $request->input('pagination_offset', 0);
        $limit = $request->input('limit', 10);
        $language_code = $language_code ?: app(TranslationService::class)->getLanguageCode();

        $query = Zipcode::with('city');

        // Apply search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('zipcode', 'like', "%$search%")
                    ->orWhere('minimum_free_delivery_order_amount', 'like', "%$search%")
                    ->orWhere('delivery_charges', 'like', "%$search%")
                    ->orWhereHas('city', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%$search%");
                    });
            });
        }

        $total = $query->count();

        $rows = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($zipcode) use ($language_code) {
                return [
                    'id' => $zipcode->id,
                    'zipcode' => $zipcode->zipcode,
                    'city_name' => $zipcode->city
                        ? app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $zipcode->city->id, $language_code)
                        : '',
                    'city_id' => $zipcode->city->id ?? '',
                    'minimum_free_delivery_order_amount' => $zipcode->minimum_free_delivery_order_amount ?? 0,
                    'delivery_charges' => $zipcode->delivery_charges ?? 0,
                ];
            });

        return response()->json([
            'rows' => $rows,
            'total' => $total,
        ]);
    }


    // city

    public function city()
    {
        $languages = Language::all();
        return view('seller.pages.tables.city', ['languages' => $languages]);
    }




    public function city_list(Request $request, $language_code = '')
    {
        $search = trim(request('search'));
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : "0";
        $limit = (request('limit')) ? request('limit') : "10";
        $language_code = isset($language_code) && !empty($language_code) ? $language_code : app(TranslationService::class)->getLanguageCode();
        $city_data = City::when($search, function ($query) use ($search) {
            return $query->where('name', 'like', '%' . $search . '%');
        });

        $total = $city_data->count();

        // Use Paginator to handle the server-side pagination
        $cities = $city_data->orderBy($sort, $order)->offset($offset)
            ->limit($limit)
            ->get();

        // Prepare the data for the "Actions" field
        $data = $cities->map(function ($c) use ($language_code) {
            return [
                'id' => $c->id ?? '',
                'name' => app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $c->id, $language_code) ?? '',
                'text' => app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $c->id, $language_code) ?? '',
                'minimum_free_delivery_order_amount' => $c->minimum_free_delivery_order_amount ?? '',
                'delivery_charges' => $c->delivery_charges ?? '',
            ];
        });

        return response()->json([
            "rows" => $data, // Return the formatted data for the "Actions" field
            "total" => $total,
        ]);
    }


    public function get_cities(Request $request)
    {
        $search = trim($request->search) ?? "";
        $cities = City::whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", ['%' . strtolower($search) . '%'])->get();
        $data = array();
        foreach ($cities as $city) {
            $data[] = array("id" => $city->id, "text" => $city->name);
        }
        return response()->json($data);
    }

    public function get_zipcodes(Request $request)
    {
        $search = trim($request->search) ?? "";
        $zipcodes = Zipcode::where('zipcode', 'like', '%' . $search . '%')->get();

        $data = array();
        foreach ($zipcodes as $zipcode) {
            $data[] = array("id" => $zipcode->id, "text" => $zipcode->zipcode);
        }
        return response()->json($data);
    }

    public function getCities(Request $request)
    {
        $search = trim($request->search) ?? "";
        $cities = City::whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", ['%' . strtolower($search) . '%'])->get();
        $language_code = app(TranslationService::class)->getLanguageCode();
        $data = array();
        foreach ($cities as $city) {
            $data[] = array("id" => $city->id, "text" => app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $city->id, $language_code));
        }
        return response()->json($data);
    }

    public function zone_data(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $user_id = Auth::user()->id;
        $seller_id = Seller::where('user_id', $user_id)->value('id');
        $seller_zones = fetchDetails(SellerStore::class, ['seller_id' => $seller_id, 'store_id' => $store_id], ['deliverable_type', 'deliverable_zones']);
        $seller_zones = !$seller_zones->isEmpty() ? $seller_zones[0] : [];
        $search = trim($request->input('search'));

        $limit = (int) $request->input('limit', 50);

        $query = Zone::where('status', 1)
            ->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });

        if ($seller_zones->deliverable_type == '2' || $seller_zones->deliverable_type == '3') {
            $zone_ids = explode(',', $seller_zones->deliverable_zones);
            $query->whereIn('id', $zone_ids);
        }
        $zones = $query->limit($limit)->get(['id', 'name', 'serviceable_city_ids', 'serviceable_zipcode_ids']);
        $total = $query->count();

        $cities = [];
        $zipcodes = [];
        $language_code = app(TranslationService::class)->getLanguageCode();
        foreach ($zones as $zone) {
            $city_ids = explode(',', $zone->serviceable_city_ids);
            $zipcode_ids = explode(',', $zone->serviceable_zipcode_ids);

            $cities = array_unique(array_merge($cities, $city_ids));
            $zipcodes = array_unique(array_merge($zipcodes, $zipcode_ids));
        }

        $city_names = City::whereIn('id', $cities)->pluck('name', 'id')->toArray();

        $zipcode_names = Zipcode::whereIn('id', $zipcodes)->pluck('zipcode', 'id')->toArray();

        $response = [
            'total' => $total,
            'results' => $zones->map(function ($zone) use ($city_names, $zipcode_names, $language_code) {
                $city_ids = explode(',', $zone->serviceable_city_ids);
                $zipcode_ids = explode(',', $zone->serviceable_zipcode_ids);

                return [
                    'id' => $zone->id,
                    'text' => app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code),
                    'serviceable_cities' => implode(', ', array_map(function ($city_id) use ($language_code) {
                        return app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $city_id, $language_code);
                    }, $city_ids)),
                    'serviceable_zipcodes' => implode(', ', array_map(function ($zipcode_id) use ($zipcode_names) {
                        return $zipcode_names[$zipcode_id] ?? null;
                    }, $zipcode_ids)),
                ];
            }),
        ];

        return response()->json($response);
    }

    public function zones()
    {
        return view('seller.pages.tables.zones');
    }

    public function get_zones(Request $request)
    {
        // dd($request);
        $search = trim($request->input('term', $request->input('q', '')));
        $limit = (int) $request->input('limit', 50);

        // Start the query with 'status = 1'
        $query = Zone::where('status', 1);

        // Only apply search if a valid string is provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%'); // Search only in name field
            });
        }

        $zones = $query->limit($limit)->get(['id', 'name', 'serviceable_city_ids', 'serviceable_zipcode_ids']);
        $total = $query->count();

        $cities = [];
        $zipcodes = [];
        $language_code = app(TranslationService::class)->getLanguageCode();
        foreach ($zones as $zone) {
            $city_ids = array_filter(explode(',', $zone->serviceable_city_ids));
            $zipcode_ids = array_filter(explode(',', $zone->serviceable_zipcode_ids));

            $cities = array_unique(array_merge($cities, $city_ids));
            $zipcodes = array_unique(array_merge($zipcodes, $zipcode_ids));
        }

        $city_names = City::whereIn('id', $cities)->pluck('name', 'id')->toArray();
        $zipcode_names = Zipcode::whereIn('id', $zipcodes)->pluck('zipcode', 'id')->toArray();

        $response = [
            'total' => $total,
            'results' => $zones->map(function ($zone) use ($city_names, $zipcode_names, $language_code) {
                $city_ids = array_filter(explode(',', $zone->serviceable_city_ids));
                $zipcode_ids = array_filter(explode(',', $zone->serviceable_zipcode_ids));

                return [
                    'id' => $zone->id,
                    'text' => app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code),
                    'serviceable_cities' => implode(', ', array_map(function ($city_id) use ($language_code) {
                        return app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $city_id, $language_code);
                    }, $city_ids)),
                    'serviceable_zipcodes' => implode(', ', array_map(fn($zipcode_id) => $zipcode_names[$zipcode_id] ?? null, $zipcode_ids)),
                ];
            }),
        ];

        return response()->json($response);
    }


    public function zone_list(Request $request)
    {
        $search = trim($request->input('search'));
        $limit = (int) $request->input('limit', 10);
        $offset = (int) $request->input('pagination_offset', 0);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $user_id = Auth::user()->id;

        // Fetch seller ID
        $seller_id = Seller::where('user_id', $user_id)->value('id');

        // Fetch seller's deliverable type and zones
        $seller = SellerStore::where('seller_id', $seller_id)
            ->select('deliverable_type', 'deliverable_zones')
            ->first();

        // Query to filter and fetch zones
        $query = Zone::where('status', 1)
            ->when($search, function ($query) use ($search) {
                return $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });

        // If deliverable_type is 2, filter zones by deliverable_zones
        if ($seller && $seller->deliverable_type == 2) {
            $deliverable_zone_ids = explode(',', $seller->deliverable_zones);
            $query->whereIn('id', $deliverable_zone_ids);
        }

        $total = $query->count();
        $language_code = app(TranslationService::class)->getLanguageCode();
        // Fetch paginated results
        $zones = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'name', 'serviceable_city_ids', 'serviceable_zipcode_ids']);

        // Extract unique city and zipcode IDs
        $city_ids = [];
        $zipcode_ids = [];

        foreach ($zones as $zone) {
            $city_ids = array_merge($city_ids, explode(',', $zone->serviceable_city_ids));
            $zipcode_ids = array_merge($zipcode_ids, explode(',', $zone->serviceable_zipcode_ids));
        }

        $city_ids = array_unique(array_filter($city_ids));
        $zipcode_ids = array_unique(array_filter($zipcode_ids));

        // Fetch city and zipcode names
        $city_names = City::whereIn('id', $city_ids)->pluck('name', 'id')->toArray();
        $zipcode_names = Zipcode::whereIn('id', $zipcode_ids)->pluck('zipcode', 'id')->toArray();

        // Format response data
        $data = $zones->map(function ($zone) use ($city_names, $zipcode_names, $language_code) {
            return [
                'id' => $zone->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code),
                'serviceable_cities' => implode(', ', array_map(fn($id) => app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $id, $language_code), explode(',', $zone->serviceable_city_ids))),
                'serviceable_zipcodes' => implode(', ', array_map(fn($id) => $zipcode_names[$id] ?? '', explode(',', $zone->serviceable_zipcode_ids))),
            ];
        });

        return response()->json([
            'total' => $total,
            'rows' => $data,
        ]);
    }
}
