<?php

namespace App\Http\Controllers\Admin;


use App\Models\City;
use App\Models\Language;
use App\Models\Zipcode;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
class AreaController extends Controller
{
    use HandlesValidation;

    // zipcode

    public function displayZipcodes()
    {
        $cities = City::get();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        return view('admin.pages.forms.zipcodes', ['cities' => $cities, 'language_code' => $languageCode]);
    }

    public function storeZipcodes(Request $request)
    {

        $rules = [
            'city' => 'required',
            'zipcode' => 'required',
            'minimum_free_delivery_order_amount' => 'required',
            'delivery_charges' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }


        if (Zipcode::where(['city_id' => $request->city, 'zipcode' => $request->zipcode])->exists()) {
            return response()->json(['error_message' => labels('admin_labels.combination_already_exist', 'Combination Already Exist ! Provide a unique Combination')]);
        }

        $zipcode = new Zipcode();
        $zipcode->city_id = $request->city;
        $zipcode->zipcode = $request->zipcode;
        $zipcode->minimum_free_delivery_order_amount = $request->minimum_free_delivery_order_amount;
        $zipcode->delivery_charges = $request->delivery_charges;


        $zipcode->save();

        if ($request->ajax()) {
            return response()->json(['message' => labels('admin_labels.zipcode_added_successfully', 'Zipcode added successully')]);
        }
    }

    public function zipcodeList()
    {
        $search = trim(request('search', ''));
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $offset = request('pagination_offset', 0);
        $limit = request('limit', 10);
        $languageCode = app(TranslationService::class)->getLanguageCode();

        $query = Zipcode::with('city')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%$search%")
                        ->orWhere('zipcode', 'like', "%$search%");
                });
            });

        $total = $query->count();

        $zipcodes = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $rows = [];
        foreach ($zipcodes as $zipcode) {
            $cityName = $zipcode->city
                ? app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $zipcode->city_id, $languageCode)
                : '';
            $deleteUrl = route('admin.zipcodes.destroy', $zipcode->id);
            $tempRow = [
                'id' => $zipcode->id,
                'zipcode' => $zipcode->zipcode,
                'city_name' => $cityName,
                'minimum_free_delivery_order_amount' => $zipcode->minimum_free_delivery_order_amount ?? 0,
                'delivery_charges' => $zipcode->delivery_charges ?? 0,
                'operate' => '<div class="dropdown bootstrap-table-dropdown">
                            <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bx bx-dots-horizontal-rounded"></i>
                            </a>
                            <div class="dropdown-menu table_dropdown zipcode_action_dropdown" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item dropdown_menu_items edit-zipcode" data-id="' . $zipcode->id . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                                <a class="dropdown_menu_items dropdown-item delete-data" data-url="' . $deleteUrl . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                            </div>
                        </div>',
            ];

            $rows[] = $tempRow;
        }

        return response()->json([
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    public function zipcodeDestroy($id)
    {
        $zipcode = Zipcode::find($id);

        if ($zipcode->delete()) {
            return response()->json(['error' => false, 'message' => labels('admin_labels.zipcode_deleted_successfully', 'Zipcode deleted successfully')]);
        } else {
            return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
        }
    }

    // city

    public function displayCity()
    {
        $languages = Language::all();
        return view('admin.pages.forms.city', ['languages' => $languages]);
    }

    public function storeCity(Request $request)
    {
        $rules = [
            'name' => 'required|unique:cities',
            'minimum_free_delivery_order_amount' => 'required',
            'delivery_charges' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $city = new City();
        $translations = [
            'en' => $request->name
        ];
        if (!empty($request->translated_city_name)) {
            $translations = array_merge($translations, $request->translated_city_name);
        }
        // dd($translations);
        $city->name = json_encode($translations, JSON_UNESCAPED_UNICODE);
        $city->minimum_free_delivery_order_amount = $request->minimum_free_delivery_order_amount;
        $city->delivery_charges = $request->delivery_charges;

        $city->save();

        if ($request->ajax()) {
            return response()->json(['message' => labels('admin_labels.city_added_successfully', 'City added successfully')]);
        }
    }


    public function cityList(Request $request)
    {
        $search = trim(request('search'));
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;
        $limit = (request('limit')) ? request('limit') : "10";

        $cityData = City::when($search, function ($query) use ($search) {
            return $query->where('name', 'like', '%' . $search . '%');
        });

        $total = $cityData->count();

        // Use Paginator to handle the server-side pagination
        $cities = $cityData->orderBy($sort, $order)->offset($offset)
            ->limit($limit)
            ->get();

        // Prepare the data for the "Actions" field
        $data = $cities->map(function ($c) {
            $deleteUrl = route('admin.city.destroy', $c->id);
            $action = '<div class="dropdown bootstrap-table-dropdown">
                <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-dots-horizontal-rounded"></i>
                </a>
                <div class="dropdown-menu table_dropdown city_action_dropdown" aria-labelledby="dropdownMenuButton">
                <a class="dropdown-item dropdown_menu_items edit-city" data-id="' . $c->id . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                    <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $deleteUrl . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                </div>
            </div>';
            $languageCode = app(TranslationService::class)->getLanguageCode();
            return [
                'id' => $c->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $c->id, $languageCode),
                'minimum_free_delivery_order_amount' => $c->minimum_free_delivery_order_amount,
                'delivery_charges' => $c->delivery_charges,
                'operate' => $action,
            ];
        });

        return response()->json([
            "rows" => $data, // Return the formatted data for the "Actions" field
            "total" => $total,
        ]);
    }

    public function cityDestroy($id)
    {
        $city = City::find($id);
        if (isForeignKeyInUse(Zipcode::class, 'city_id', $id)) {
            return response()->json(['error' => labels('admin_labels.you_cannot_delete_this_city_because_it_is_assoicated_with_zipcode', 'You cannot delete this city because it is associated with zipcode.')]);
        }

        if ($city->delete()) {
            return response()->json(['error' => false, 'message' => labels('admin_labels.city_deleted_successfully', 'City deleted successfully')]);
        }
        return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
    }

    public function getCities(Request $request)
    {
        $search = trim($request->search);

        $languageCode = app(TranslationService::class)->getLanguageCode();
        $cities = City::whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", ['%' . strtolower($search) . '%'])->get();
        $data = array();
        foreach ($cities as $city) {
            $data[] = array("id" => $city->id, "text" => app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $city->id, $languageCode));
        }
        return response()->json($data);
    }

    public function getCitiesList($sort = "c.name", $order = "ASC", $search = "", $limit = '', $offset = '', $languageCode = '')
    {

        $query = City::select('cities.*')
            ->leftJoin('areas', 'cities.id', '=', 'areas.city_id');

        if (!empty($search)) {
            $query->where("LOWER(JSON_UNQUOTE(JSON_EXTRACT(cities.name, '$.en'))) LIKE ?", ['%' . strtolower($search) . '%']);
        }
        $totalRecords = $query->count();
        $cities = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();
        // Remove created_at and updated_at fields from each item in the collection
        $cities->each(function ($item) use ($languageCode) {
            $item->name = app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $item->id, $languageCode);
            unset($item->created_at);
            unset($item->updated_at);
        });

        $bulkData = [
            'error' => $cities->isEmpty(),
            'total' => $totalRecords,
            'language_message_key' => 'cities_retrived_successfully',
            'data' => $cities->isEmpty() ? [] : $cities->toArray(),
        ];


        return response()->json($bulkData);
    }


    public function get_zipcodes(Request $request)
    {
        $search = trim($request->input('search', ''));
        $zipcodes = Zipcode::where('zipcode', 'like', '%' . $search . '%')->get();

        $data = array();
        foreach ($zipcodes as $zipcode) {
            $data[] = array("id" => $zipcode->id, "text" => $zipcode->zipcode);
        }
        return response()->json($data);
    }

    public function getZipcodes($search = '', $limit = '', $offset = 0)
    {
        $search = !empty($search) ? $search : "";
        $limit = !empty($limit) ? $limit : null;

        $zipcode = new Zipcode();

        $query = $zipcode;
        $totalQuery = clone $zipcode;

        if (!empty($search)) {
            $query = $query->where('zipcode', 'like', '%' . $search . '%');
            $totalQuery = $totalQuery->where('zipcode', 'like', '%' . $search . '%');
        }

        $total = $totalQuery->count();

        if (!is_null($limit)) {
            $query = $query->take($limit);
        }

        if (!is_null($offset)) {
            $query = $query->skip($offset);
        }

        $zipcodes = $query->get();


        $bulkData = [
            'error' => $zipcodes->isEmpty(),
            'message' => $zipcodes->isEmpty() ? labels('admin_labels.zipcode_not_exist', 'Zipcode not exist') : labels('admin_labels.zipcode_retrived_successfully', 'Zipcode retrived successfully'),
            'total' => $zipcodes->isEmpty() ? 0 : $total,
            'data' => $zipcodes
        ];

        return $bulkData;
    }

    public function location_bulk_upload()
    {
        return view('admin.pages.forms.location_bulk_upload');
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
        $locationType = $request->location_type;
        $csv = $_FILES['upload_file']['tmp_name'];
        $temp = 0;
        $temp1 = 0;
        $handle = fopen($csv, "r");

        $type = $request->type;
        $languageCode = app(TranslationService::class)->getLanguageCode();
        if ($type == 'upload' && $locationType == 'zipcode') {
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                if ($temp != 0) {
                    if (empty($row[0]) && $row[0] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.zipcode_empty_at_row', 'Zipcode is empty at row') . $temp]);
                    }

                    if (empty($row[1]) && $row[1] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.city_id_empty_at_row', 'City id is empty at row') . $temp]);
                    }

                    if (!empty($row[1]) && $row[1] != "") {
                        if (!isExist(['id' => $row[1]], City::class)) {
                            return response()->json(['error' => 'true', 'message' => labels('admin_labels.city_not_exist_database_at_row', 'City does not exist in your database at row') . $temp]);
                        }
                    }

                    if (empty($row[2]) && $row[2] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.minimum_free_delivery_order_amount_is_empty_at_row', 'Minimum Free Delivery Order Amount is empty at row') . $temp]);
                    }
                    if (empty($row[3]) && $row[3] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.delivery_charges_empty_at_row', 'Delivery Charges is empty at row') . $temp]);
                    }


                    if (Zipcode::where(['city_id' => $row[1], 'zipcode' => $row[0]])->exists()) {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.combination_already_exists', 'Combination Already Exists! Provide a unique Combination at row') . $temp]);
                    }
                }
                $temp++;
            }
            fclose($handle);
            $handle = fopen($csv, "r");
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                if ($temp1 !== 0) {
                    $data = [
                        'zipcode' => $row[0],
                        'city_id' => $row[1],
                        'minimum_free_delivery_order_amount' => $row[2],
                        'delivery_charges' => $row[3],
                    ];
                    Zipcode::create($data);
                }
                $temp1++;
            }
            fclose($handle);
            return response()->json(['error' => 'false', 'message' => labels('admin_labels.zipcode_uploaded_successfully', 'Zipcode uploaded successfully')]);
        } else if ($type == 'upload' && $locationType == 'city') {
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                if ($temp != 0) {
                    if (empty($row[0])) {
                        return response()->json(['message' => 'Name is empty at row ' . $temp]);
                    }
                    if (empty($row[1]) && $row[1] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.minimum_free_delivery_order_amount_is_empty_at_row', 'Minimum Free Delivery Order Amount is empty at row') . $temp]);
                    }
                    if (empty($row[2]) && $row[2] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.delivery_charges_empty_at_row', 'Delivery Charges is empty at row') . $temp]);
                    }
                    if (!empty($row[0])) {
                        if (isExist(['name' => $row[0]], City::class)) {
                            return response()->json(['error' => 'true', 'message' => labels('admin_labels.city_already_exist_provide_another_city_name_at_row', 'City Already Exist! Provide another city name at row') . $temp]);
                        }
                    }
                }
                $temp++;
            }
            fclose($handle);
            $handle = fopen($csv, "r");
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp1 !== 0) {
                    $cityName = trim($row[0]);
                    $cityName = stripslashes($cityName);

                    $decodedCityName = json_decode($cityName, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return response()->json(['error' => 'true', 'message' => "Invalid JSON format in city name at row {$temp1}"]);
                    }

                    $data = [
                        'name' => json_encode($decodedCityName, JSON_UNESCAPED_UNICODE),
                        'minimum_free_delivery_order_amount' => $row[1] ?? null,
                        'delivery_charges' => $row[2] ?? null,
                    ];

                    City::create($data);
                }
                $temp1++;
            }
            fclose($handle);
            return response()->json(['error' => 'false', 'message' => labels('admin_labels.city_uploaded_successfully', 'City uploaded successfully!')]);
        } else if ($type == 'upload' && $locationType == 'zone') {
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                $validator = Validator::make($request->all(), [
                    'upload_file' => 'required|mimes:csv,txt|max:2048',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()->all()], 422);
                }
                $file = $request->file('upload_file');
                $csv = Reader::createFromPath($file->getPathname(), 'r');
                $csv->setHeaderOffset(0);

                $records = $csv->getRecords();
                $errors = [];
                $zoneRows = [];

                foreach ($records as $index => $row) {
                    $rowValidator = Validator::make($row, [
                        'name' => 'required',
                        'serviceable_zipcode_id' => 'required|integer',
                        'zipcode_delivery_charge' => 'required|numeric',
                        'serviceable_city_id' => 'required|integer',
                        'city_delivery_charge' => 'required|numeric',
                    ]);

                    if ($rowValidator->fails()) {
                        $errors[] = [
                            'row' => $index + 1,
                            'errors' => $rowValidator->errors()->all(),
                        ];
                        continue;
                    }
                    $zoneRows[$row['name']]['zipcode_group'][] = [
                        'serviceable_zipcode_id' => $row['serviceable_zipcode_id'],
                        'zipcode_delivery_charge' => $row['zipcode_delivery_charge'],
                    ];
                    $zoneRows[$row['name']]['city_group'][] = [
                        'serviceable_city_id' => $row['serviceable_city_id'],
                        'city_delivery_charge' => $row['city_delivery_charge'],
                    ];
                }

                if (!empty($errors)) {
                    return response()->json(['errors' => $errors], 422);
                }
                foreach ($zoneRows as $name => $groups) {
                    $zipcodeIds = collect($groups['zipcode_group'])->pluck('serviceable_zipcode_id')->implode(',');
                    $cityIds = collect($groups['city_group'])->pluck('serviceable_city_id')->implode(',');

                    $existingZone = Zone::where('serviceable_zipcode_ids', $zipcodeIds)
                        ->where('serviceable_city_ids', $cityIds)
                        ->first();

                    if ($existingZone) {
                        return response()->json([
                            'error' => true,
                            'message' => 'A zone with the same serviceable cities and zipcodes already exists as ' . app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $existingZone->id, $languageCode),
                        ]);
                    }

                    $data = [
                        'name' => $name,
                        'serviceable_zipcode_ids' => $zipcodeIds,
                        'serviceable_city_ids' => $cityIds,
                        'status' => 1,
                    ];
                    Zone::create($data);
                    foreach ($groups['zipcode_group'] as $zipcode) {
                        Zipcode::where('id', $zipcode['serviceable_zipcode_id'])
                            ->update(['delivery_charges' => $zipcode['zipcode_delivery_charge']]);
                    }
                    foreach ($groups['city_group'] as $city) {
                        City::where('id', $city['serviceable_city_id'])
                            ->update(['delivery_charges' => $city['city_delivery_charge']]);
                    }
                }

                return response()->json(['error' => 'false', 'message' => labels('admin_labels.zones_uploaded_successfully', 'Zones uploaded successfully!')]);
            }
        } else if ($type == 'update' && $locationType == 'zipcode') {
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) //get row vales
            {
                if ($temp != 0) {
                    if (empty($row[0])) {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.zipcode_id_empty_at_row', 'Zipcode id empty at row') . $temp]);
                    }

                    if (!empty($row[0]) && $row[0] != "") {
                        if (!isExist(['id' => $row[0]], Zipcode::class)) {
                            return response()->json(['error' => 'true', 'message' => labels('admin_labels.zipcode_not_exist_database_at_row', 'Zipcode id is not exist in your database at row') . $temp]);
                        }
                    }

                    if (empty($row[1]) && $row[1] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.zipcode_empty_at_row', 'Zipcode is empty at row') . $temp]);
                    }

                    if (empty($row[2]) && $row[2] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.city_id_empty_at_row', 'City id is empty at row') . $temp]);
                    }

                    if (!empty($row[2]) && $row[2] != "") {
                        if (!isExist(['id' => $row[2]], City::class)) {
                            return response()->json(['error' => 'true', 'message' => labels('admin_labels.city_not_exist_database_at_row', 'City does not exist in your database at row') . $temp]);
                        }
                    }

                    if (empty($row[3]) && $row[3] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.minimum_free_delivery_order_amount_is_empty_at_row', 'Minimum Free Delivery Order Amount is empty at row') . $temp]);
                    }
                    if (empty($row[4]) && $row[4] == "") {
                        return response()->json(['error' => 'true', 'message' => labels('admin_labels.delivery_charges_empty_at_row', 'Delivery Charges is empty at row') . $temp]);
                    }
                }
                $temp++;
            }
            fclose($handle);
            $handle = fopen($csv, "r");
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                if ($temp1 != 0) {
                    $zipcodeId = $row[0];
                    $zipcode = fetchDetails(Zipcode::class, ['id' => $zipcodeId], '*');
                    if (!$zipcode->isEmpty()) {
                        if (!empty($row[1])) {
                            $data['zipcode'] = $row[1];
                            $data['city_id'] = $row[2];
                            $data['minimum_free_delivery_order_amount'] = $row[3];
                            $data['delivery_charges'] = $row[4];
                            $existing_zipcode = Zipcode::where(['city_id' => $row[1], 'zipcode' => $row[0]])->exists();
                            $data['zipcode'] = $row[1];
                            if ($existing_zipcode) {
                                return response()->json(['error' => 'true', 'message' => "Zipcode '{$data['zipcode']}' already exists. Please provide another zipcode."]);
                            }
                        } else {
                            $data['zipcode'] = $zipcode[0]['zipcode'];
                            $data['city_id'] = $zipcode[0]['city_id'];
                            $data['minimum_free_delivery_order_amount'] = $zipcode[0]['minimum_free_delivery_order_amount'];
                            $data['delivery_charges'] = $zipcode[0]['delivery_charges'];
                        }
                        Zipcode::where('id', $zipcodeId)->update($data);
                    } else {
                        return response()->json(['error' => 'true', 'message' => 'Zipcode id: ' . $zipcodeId . ' not exist!']);
                    }
                }
                $temp1++;
            }
            fclose($handle);
            return response()->json([
                'error' => 'false',
                'message' => labels('admin_labels.zipcodes_updated_successfully', 'Zipcodes updated successfully')
            ]);
        } else if ($type == 'update' && $locationType == 'city') {
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                if ($temp != 0) {
                    if (empty($row[0])) {
                        return response()->json([
                            'error' => 'true',
                            'message' => labels('admin_labels.city_id_empty_at_row', 'City id empty at row')
                                . $temp
                        ]);
                    }

                    if (!empty($row[0]) && $row[0] != "") {
                        if (!isExist(['id' => $row[0]], City::class)) {
                            return response()->json([
                                'error' => 'true',
                                'message' => labels('admin_labels.city_id_not_exist_database_at_row', 'City id is not exist in your database at row')
                                    . $temp
                            ]);
                        }
                    }

                    if (empty($row[1])) {
                        return response()->json([
                            'error' => 'true',
                            'message' => labels('admin_labels.city_empty_at_row', 'City empty at row')
                                . $temp
                        ]);
                    }
                }
                $temp++;
            }
            fclose($handle);
            $handle = fopen($csv, "r");
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if ($temp1 !== 0) {
                    $cityId = $row[0];
                    $city = fetchDetails(City::class, ['id' => $cityId], '*');

                    if (!$city->isEmpty()) {
                        $data = [];
                        if (!empty($row[1])) {
                            $cityName = trim($row[1]);
                            $cityName = stripslashes($cityName);

                            $decodedCityName = json_decode($cityName, true);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return response()->json(['error' => 'true', 'message' => "Invalid JSON format in name at row {$temp1}"]);
                            }

                            $data['name'] = json_encode($decodedCityName, JSON_UNESCAPED_UNICODE);
                        }
                        $data['minimum_free_delivery_order_amount'] = !empty($row[2]) ? $row[2] : '';
                        $data['delivery_charges'] = !empty($row[3]) ? $row[3] : '';

                        City::where('id', $cityId)->update($data);
                    }
                }
                $temp1++;
            }
            fclose($handle);
            return response()->json([
                'error' => 'false',
                'message' => labels('admin_labels.city_updated_successfully', 'City updated successfully!')
            ]);
        } else if ($type == 'update' && $locationType == 'zone') {
            while (($row = fgetcsv($handle, 10000, ",")) != FALSE) {
                $validator = Validator::make($request->all(), [
                    'upload_file' => 'required|mimes:csv,txt|max:2048',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()->all()], 422);
                }
                $file = $request->file('upload_file');
                $csv = Reader::createFromPath($file->getPathname(), 'r');
                $csv->setHeaderOffset(0);

                $records = $csv->getRecords();
                $errors = [];
                $zipcodeGroup = [];
                $cityGroup = [];

                foreach ($records as $index => $row) {
                    // Validate each row
                    $rowValidator = Validator::make($row, [
                        'id' => 'required|integer|exists:zones,id',
                        'name' => 'nullable|string',
                        'serviceable_zipcode_id' => 'required|integer',
                        'zipcode_delivery_charge' => 'required|numeric',
                        'serviceable_city_id' => 'required|integer',
                        'city_delivery_charge' => 'required|numeric',
                    ]);

                    if ($rowValidator->fails()) {
                        $errors[] = [
                            'row' => $index + 1,
                            'errors' => $rowValidator->errors()->all(),
                        ];
                        continue;
                    }
                    $zoneData = Zone::find($row['id']);

                    if (!$zoneData) {
                        $errors[] = [
                            'row' => $index + 1,
                            'errors' => ['Zone with ID ' . $row['id'] . ' not found.'],
                        ];
                        continue;
                    }
                    if (!empty($row['name'])) {
                        $zoneData->name = $row['name'];
                    }
                    $zipcodeGroup[] = [
                        'serviceable_zipcode_id' => $row['serviceable_zipcode_id'],
                        'zipcode_delivery_charge' => $row['zipcode_delivery_charge'],
                    ];

                    $cityGroup[] = [
                        'serviceable_city_id' => $row['serviceable_city_id'],
                        'city_delivery_charge' => $row['city_delivery_charge'],
                    ];
                    Zipcode::where('id', $row['serviceable_zipcode_id'])
                        ->update(['delivery_charges' => $row['zipcode_delivery_charge']]);
                    City::where('id', $row['serviceable_city_id'])
                        ->update(['delivery_charges' => $row['city_delivery_charge']]);
                }
                if (!empty($errors)) {
                    return response()->json(['errors' => $errors], 422);
                }
                $serviceableZipcodeIds = collect($zipcodeGroup)->pluck('serviceable_zipcode_id')->implode(',');
                $serviceableCityIds = collect($cityGroup)->pluck('serviceable_city_id')->implode(',');
                if ($zoneData->serviceable_zipcode_ids == $serviceableZipcodeIds && $zoneData->serviceable_city_ids == $serviceableCityIds) {
                    $zoneData->update([
                        'name' => $zoneData->name,
                        'serviceable_zipcode_ids' => $serviceableZipcodeIds,
                        'serviceable_city_ids' => $serviceableCityIds,
                    ]);
                } else {
                    $existingZone = Zone::where('serviceable_zipcode_ids', $serviceableZipcodeIds)
                        ->where('serviceable_city_ids', $serviceableCityIds)
                        ->where('id', '!=', $zoneData->id)
                        ->first();

                    if ($existingZone) {
                        return response()->json([
                            'error' => true,
                            'message' => 'A zone with the same serviceable cities and zipcodes already exists as ' . app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $existingZone->id, $languageCode),
                        ]);
                    }
                    $zoneData->update([
                        'name' => !empty($row['name']) ? $row['name'] : $zoneData->name,
                        'serviceable_zipcode_ids' => $serviceableZipcodeIds,
                        'serviceable_city_ids' => $serviceableCityIds,
                    ]);
                }
                return response()->json([
                    'error' => 'false',
                    'message' => labels('admin_labels.zone_updated_successfully', 'Zone updated successfully!')
                ]);
            }
        } else {
            return response()->json([
                'error' => 'true',
                'message' => labels('admin_labels.invalid_type_or_type_location', 'Invalid Type or Type Location!')
            ]);
        }
    }


    public function zipcodesEdit($id)
    {
        return $this->editData(Zipcode::class, $id, labels('admin_labels.data_not_found', 'Data Not Found'));
    }

    public function zipcodeShow($id)
    {
        $zipcode = Zipcode::with('city')->findOrFail($id);
        $languageCode = app(TranslationService::class)->getLanguageCode();
    
        $cityName = $zipcode->city
            ? app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $zipcode->city_id, $languageCode)
            : '';
    
        return response()->json([
            'id' => $zipcode->id,
            'zipcode' => $zipcode->zipcode,
            'city_id' => $zipcode->city_id,
            'city_name' => $cityName,
            'minimum_free_delivery_order_amount' => $zipcode->minimum_free_delivery_order_amount ?? 0,
            'delivery_charges' => $zipcode->delivery_charges ?? 0,
        ]);
    }

    public function zipcodesUpdate(Request $request, $id)
    {
        $fields = ['zipcode', 'city_id', 'minimum_free_delivery_order_amount', 'delivery_charges'];
        return $this->updateData(
            $request,
            Zipcode::class,
            $id,
            $fields,
            labels('admin_labels.zipcode_updated_successfully', 'Zipcode updated successfully'),
            'zipcode'
        );
    }
    public function distroyZipcode($id)
    {

        $zipcode = Zipcode::find($id);

        if ($zipcode->delete()) {
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.zipcode_deleted_successfully', 'Zipcode deleted successfully!')
            ]);
        } else {
            return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
        }
    }

    public function cityEdit($id)
    {
        return $this->editData(City::class, $id, labels('admin_labels.data_not_found', 'Data Not Found'));
    }

    public function cityUpdate(Request $request, $id)
    {
        $fields = ['name', 'minimum_free_delivery_order_amount', 'delivery_charges'];

        return $this->updateData(
            $request,
            City::class,
            $id,
            $fields,
            labels('admin_labels.city_updated_successfully', 'City updated successfully'),
            'city'
        );
    }

    // general function for fetch edit data

    public function editData($modelName, $id, $errorMessage)
    {
        $data = $modelName::find($id);

        if (!$data) {
            return response()->json(['error' => true, 'message' => $errorMessage], 404);
        }
        return response()->json($data);
    }

    // general function for update fetched data

    public function updateData(Request $request, $modelName, $id, $fields, $successMessage, $tableName = "")
    {
        $data = $modelName::find($id);
        // dd($modelName);
        if (!$data) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')], 404);
        } else {
            // Define validation rules
            $rules = [];
            foreach ($fields as $field) {
                $rules[$field] = 'required';
            }

            // Validate the request
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $errors = $validator->errors();

                if ($request->ajax()) {
                    return response()->json(['errors' => $errors->all()], 422);
                }
                return redirect()->back()->withErrors($errors)->withInput();
            }

            // Update the data
            if (count($fields) === 1) {
                // dd($data);
                $field = reset($fields);
                $data->{strtolower($field)} = $request->input($field);
            } else {
                if (isset($tableName) && $tableName == 'zipcode') {
                    foreach ($fields as $field) {
                        $data->{strtolower($field)} = $request->input($field);
                    }
                } else {
                    foreach ($fields as $field) {
                        $existingTranslations = json_decode($request->input('name'), true);

                        // Ensure it's an array
                        if (!is_array($existingTranslations)) {
                            $existingTranslations = [];
                        }

                        $existingTranslations['en'] = $request->input('name');
                        // dd($request->translated_city_name);
                        if (!empty($request->translated_city_name)) {
                            $existingTranslations = array_merge($existingTranslations, $request->translated_city_name);
                        }
                        // dd($existingTranslations);
                        $data->name = json_encode($existingTranslations, JSON_UNESCAPED_UNICODE);
                        $data->minimum_free_delivery_order_amount = $request->minimum_free_delivery_order_amount;
                        $data->delivery_charges = $request->minimum_free_delivery_order_amount;
                        // $data->{strtolower($field)} = $request->input($field);
                    }
                }
            }
            // dd($data);
            $data->save();

            if ($request->ajax()) {
                return response()->json(['message' => $successMessage]);
            }
        }
    }


    public function getAreaByCity($cityId, $sort = 'zipcode', $order = 'ASC', $search = '', $limit = '', $offset = '')
    {
        $query = Zipcode::query();

        // Filter by city_id
        if (!empty($cityId)) {
            $query->where('city_id', $cityId);
        }

        // Search by zipcode
        if (!empty($search)) {
            $query->where('zipcode', 'like', '%' . $search . '%');
        }

        // Sorting
        $query->orderBy($sort, $order);

        // Pagination
        if (!empty($limit)) {
            $query->limit($limit);
        }

        if (!empty($offset)) {
            $query->offset($offset);
        }

        // Fetch selected columns only
        $areas = $query->select('zipcode', 'id')->get();

        return [
            'error' => $areas->isEmpty(),
            'data' => $areas->toArray(),
        ];
    }
    public function delete_selected_data(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:zipcodes,id'
        ]);

        foreach ($request->ids as $id) {
            $zipcodes = Zipcode::find($id);

            if ($zipcodes) {
                Zipcode::where('id', $id)->delete();
            }
        }

        return response()->json([
            'error' => false,
            'message' => labels('admin_labels.zipcode_deleted_successfully', 'Selected zipcodes deleted successfully!'),
        ]);
    }
    public function delete_selected_city_data(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:cities,id'
        ]);

        foreach ($request->ids as $id) {
            $city = City::find($id);

            if ($city) {
                City::where('id', $id)->delete();
            }
        }

        return response()->json([
            'error' => false,
            'message' => labels('admin_labels.cities_deleted_successfully', 'Selected cities deleted successfully!'),
        ]);
    }
}
