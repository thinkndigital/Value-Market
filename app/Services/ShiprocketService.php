<?php

namespace App\Services;
use App\Models\OrderTracking;
use App\Libraries\Shiprocket;
use App\Models\Parcel;
use App\Models\Parcelitem;
use App\Models\OrderItems;
use App\Models\PickupLocation;
use App\Services\ParcelService;
use Illuminate\Support\Collection;
use App\Services\CurrencyService;
class ShiprocketService
{

    public function getShiprocketOrder($shiprocket_order_id)
    {
        $shiprocket = new Shiprocket();
        $res = $shiprocket->get_specific_order($shiprocket_order_id);
        return $res;
    }
    
    public function shiprocketRecomendedData($shiprocket_data)
    {
    
        $result = array();
        if (isset($shiprocket_data['data']['recommended_courier_company_id'])) {
            foreach ($shiprocket_data['data']['available_courier_companies'] as $rd) {
                if ($shiprocket_data['data']['recommended_courier_company_id'] == $rd['courier_company_id']) {
                    $result = $rd;
                    break;
                }
            }
        } else {
            foreach ($shiprocket_data['data']['available_courier_companies'] as $rd) {
                if ($rd['courier_company_id']) {
                    $result = $rd;
                    break;
                }
            }
        }
        return $result;
    }
    
    public function generateAwb($shipment_id)
    {
        $order_tracking = fetchDetails(OrderTracking::class, ['shipment_id' => $shipment_id], 'courier_company_id');
        $courier_company_id = !$order_tracking->isEmpty() ? $order_tracking[0]->courier_company_id : "";
    
        $shiprocket = new Shiprocket();
        $res = $shiprocket->generate_awb($shipment_id);
    
        if (isset($res['awb_assign_status']) && $res['awb_assign_status'] == 1) {
            $order_tracking_data = [
                'awb_code' => $res['response']['data']['awb_code'],
            ];
            $res_shippment_data = $shiprocket->get_order($shipment_id);
            updateDetails($order_tracking_data, ['shipment_id' => $shipment_id], OrderTracking::class);
        } else {
            $res = $shiprocket->generate_awb($shipment_id);
            $order_tracking_data = [
                'awb_code' => $res['response']['data']['awb_code'],
            ];
            $res_shippment_data = $shiprocket->get_order($shipment_id);
            updateDetails($order_tracking_data, ['shipment_id' => $shipment_id], OrderTracking::class);
        }
    
        return $res;
    }
    
    public function sendPickupRequest($shipment_id)
    {
    
        $shiprocket = new Shiprocket();
        $res = $shiprocket->request_for_pickup($shipment_id);
        if (isset($res['pickup_status']) && $res['pickup_status'] == 1) {
    
            $order_tracking_data = [
                'pickup_status' => $res['pickup_status'],
                'pickup_scheduled_date' => $res['response']['pickup_scheduled_date'],
                'pickup_token_number' => $res['response']['pickup_token_number'],
                'status' => $res['response']['status'],
                'pickup_generated_date' => json_encode(array($res['response']['pickup_generated_date'])),
                'data' => $res['response']['data'],
            ];
            updateDetails($order_tracking_data, ['shipment_id' => $shipment_id], OrderTracking::class);
        }
        return $res;
    }
    
    public function cancelShiprocketOrder($shiprocket_order_id)
    {
        $shiprocket = new Shiprocket();
        $res = $shiprocket->cancel_order($shiprocket_order_id);
    
        if (isset($res['status']) && $res['status'] == 200 || $res['status_code'] == 200) {
            $is_canceled = [
                'is_canceled' => 1,
            ];
            updateDetails($is_canceled, ['shiprocket_order_id' => $shiprocket_order_id], OrderTracking::class);
            $order_tracking = fetchDetails(OrderTracking::class, ['shiprocket_order_id' => $shiprocket_order_id]);
    
            $parcel_id = !$order_tracking->isEmpty() ? $order_tracking[0]->parcel_id : "";
            $uniqueStatus = ["processed"];
    
            $active_status = "cancelled";
            $status = json_encode($uniqueStatus);
    
            $old_active_status_data = fetchDetails(Parcel::class, ['id' => $parcel_id], ['active_status', 'store_id']);
    
            $old_active_status = !$old_active_status_data->isEmpty() ? $old_active_status_data[0]->active_status : "";
            $store_id = !$old_active_status_data->isEmpty() ? $old_active_status_data[0]->store_id : "";
    
            if ($old_active_status != "processed" || $old_active_status != "canceled") {
    
                if (app(OrderService::class)->updateOrder(['status' => 'cancelled'], ['id' => $parcel_id], true, "parcels", false, 0, Parcel::class)) {
                    app(OrderService::class)->updateOrder(['active_status' => $active_status], ['id' => $parcel_id], false, "parcels", false, 0, Parcel::class);
                    $parcel_item_details = fetchDetails(ParcelItem::class, ['parcel_id' => $parcel_id]);
                    foreach ($parcel_item_details as $item) {

                        app(OrderService::class)->updateOrder(['status' => 'cancelled'], ['id' => $item->order_item_id], true, "order_items", false, 0, OrderItems::class);
                        app(OrderService::class)->updateOrder(['active_status' => $active_status], ['id' => $item->order_item_id], false, "order_items", false, 0, OrderItems::class);
                    }
                }
            }
            $parcel_details = app(ParcelService::class)->viewAllParcels($order_tracking[0]->order_id, $parcel_id, '', 0, 10, 'DESC', 1, '', '', $store_id);
    
            $res['data'] = $parcel_details->original['data'][0];
        }
        return $res;
    }
    public function updateShiprocketOrderStatus($tracking_id)
    {
        $order_tracking_details = fetchDetails(OrderTracking::class, ['tracking_id' => $tracking_id, 'is_canceled' => 0], ['order_id', 'parcel_id']);
    
        if ($order_tracking_details->isEmpty()) {
            return [
                'error' => true,
                'message' => "Something Went Wrong. Order Not Found.",
                'data' => []
            ];
        }
        $parcel_id = $order_tracking_details[0]->parcel_id;
        $order_id = $order_tracking_details[0]->order_id;
        $shiprocket = new Shiprocket();
        $res = $shiprocket->tracking_order($tracking_id);
    
    
        if (isset($res[0][$tracking_id]['tracking_data']) && !empty($res[0][$tracking_id]['tracking_data'])) {
    
            $active_status = "";
            $status = [];
            $active_status_code = $res[0][$tracking_id]['tracking_data']['shipment_status'];
    
            $awb_code = $res[0][$tracking_id]['tracking_data']['shipment_track'][0]['awb_code'];
            $track_url = $res[0][$tracking_id]['tracking_data']['track_url'];
            $data = [
                'url' => $track_url,
                'awb_code' => $awb_code
            ];
    
            if ($active_status_code != 8) {
                updateDetails($data, ['tracking_id' => $tracking_id], OrderTracking::class);
            }
    
            $track_activities = $res[0][$tracking_id]['tracking_data']['shipment_track_activities'];
            $shiprocket_status_codes = config('ezeemart.shiprocket_status_codes');
    
            foreach ($shiprocket_status_codes as $status) {
    
                if ($active_status_code == $status['code']) {
                    $active_status = $status['description'];
                }
                if (($track_activities) != null) {
                    foreach ($track_activities as $track_list) {
                        if ($track_list['sr-status'] == $status['code']) {
                            $data = [
                                $status['description'],
                                $track_list['date'],
                            ];
                            array_push($status, $data);
                        }
                    }
                }
            }
    
            if ($active_status == 'delivered') {
                $data = [
                    $active_status,
                    $res[0]['tracking_data']['shipment_track'][0]['delivered_date'] ?? date("Y-m-d") . " " . date("h:i:sa")
                ];
                array_push($status, $data);
            }
            if (empty($active_status) && empty($status)) {
                $response['error'] = true;
                $response['message'] = "Check Status Manually From Given Tracking Url!";
                $response['data'] = [
                    'track_url' => $track_url
                ];
                return $response;
            }
            $parcel_item_details = fetchDetails(ParcelItem::class, ['parcel_id' => $parcel_id]);
    
            $parcel_items = fetchDetails(Parcel::class, ['id' => $parcel_id]);
            if ($parcel_items->isEmpty() || $parcel_item_details->isEmpty()) {
                $response['error'] = true;
                $response['message'] = "Something Went Wrong. Order Not Found.";
                $response['data'] = [
                    'track_url' => $track_url
                ];
                return $response;
            }
    
            if (!empty($active_status) && empty($status)) {
                $status = [[$active_status, date("Y-m-d") . " " . date("h:i:sa")]];
            }
            if (empty($active_status) && !empty($status)) {
                $active_status = $parcel_items[0]->active_status;
            }
    
            $uniqueStatus = [];
            // remove duplicate status
            foreach ($status as $entry) {
    
                $status = $entry;
                if (!in_array($status, array_column($uniqueStatus, 0))) {
                    $uniqueStatus[] = $entry;
                }
            }
    
            $response_data = [];
            $active_status = str_replace(" ", "_", $active_status);
            if ($active_status == "cancelled") {
                $data += [
                    'is_canceled' => 1
                ];
                $uniqueStatus = ["processed"];
                $active_status = "cancelled";
                updateDetails($data, ['tracking_id' => $tracking_id], OrderTracking::class);
            }
            $status = json_encode($uniqueStatus);
            if (app(OrderService::class)->updateOrder(['status' => 'cancelled'], ['id' => $parcel_id], true, "parcels", false, 0, Parcel::class)) {
                app(OrderService::class)->updateOrder(['active_status' => $active_status], ['id' => $parcel_id], false, "parcels", false, 0, Parcel::class);
    
                foreach ($parcel_item_details as $item) {
                    app(OrderService::class)->updateOrder(['status' => 'cancelled'], ['id' => $item->order_item_id], true, "order_items", false, 0, OrderItems::class);
                    app(OrderService::class)->updateOrder(['active_status' => $active_status], ['id' => $item->order_item_id], false, "order_items", false, 0, OrderItems::class);
                    $data = [
                        'consignment_id' => $parcel_id,
                        'order_item_id' => $item->order_item_id,
                        'status' => $active_status
                    ];
                    array_push($response_data, $data);
                }
            }
            if ($active_status == "cancelled") {
                $response['error'] = true;
                $response['message'] = "Shiprocket Order Is Cancelled!";
                $response['data'] = [
                    'track_url' => $track_url
                ];
            } else {
                $response['error'] = false;
                $response['message'] = "Status Updated Successfully";
                $response['data'] = $response_data;
            }
            return $response;
        } else {
            return [
                'error' => true,
                'message' => $tracking_data['error'] ?? 'Tracking data not available'
            ];
        }
    }
    
    public function generateLabel($shipment_id)
    {
        $shiprocket = new Shiprocket();
        $res = $shiprocket->generate_label($shipment_id);
    
        if (isset($res['label_created']) && $res['label_created'] == 1) {
            $label_data = [
                'label_url' => $res['label_url'],
            ];
            updateDetails($label_data, ['shipment_id' => $shipment_id], OrderTracking::class);
        }
        return $res;
    }
    
    public function generateInvoice($shiprocket_order_id)
    {
        $shiprocket = new Shiprocket();
        $res = $shiprocket->generate_invoice($shiprocket_order_id);
    
        if (isset($res['is_invoice_created']) && $res['is_invoice_created'] == 1) {
            $invoice_data = [
                'invoice_url' => $res['invoice_url'],
            ];
            updateDetails($invoice_data, ['shiprocket_order_id' => $shiprocket_order_id], OrderTracking::class);
        }
        return $res;
    }

    public function checkParcelsDeliverability($parcels, $userPincode)
    {
        $shiprocket = new Shiprocket();

        $minDays = $maxDays = $deliveryChargeWithCod = $deliveryChargeWithoutCod = 0;
        $data = [];

        foreach ($parcels as $sellerId => $parcel) {
            foreach ($parcel as $pickupLocation => $parcelWeight) {
                $pickupPostcode = fetchDetails(PickupLocation::class, ['pickup_location' => $pickupLocation], 'pincode');

                if (isset($parcel[$pickupLocation]['weight']) && $parcel[$pickupLocation]['weight'] > 15) {
                    $data = "More than 15kg weight is not allowed";
                } else {
                    $availabilityData = [
                        'pickup_postcode' => !$pickupPostcode->isEmpty() ? $pickupPostcode[0]->pincode : "",
                        'delivery_postcode' => $userPincode,
                        'cod' => 0,
                        'weight' => $parcelWeight['weight'],
                    ];

                    $checkDeliverability = $shiprocket->check_serviceability($availabilityData);
                    $shiprocketData = $this->shiprocketRecommendedData($checkDeliverability);

                    $availabilityDataWithCod = [
                        'pickup_postcode' => $pickupPostcode[0]->pincode,
                        'delivery_postcode' => $userPincode,
                        'cod' => 1,
                        'weight' => $parcelWeight['weight'],
                    ];

                    $checkDeliverabilityWithCod = $shiprocket->check_serviceability($availabilityDataWithCod);
                    $shiprocketDataWithCod = $this->shiprocketRecommendedData($checkDeliverabilityWithCod);

                    $data[$sellerId][$pickupLocation]['parcel_weight'] = $parcelWeight['weight'];
                    $data[$sellerId][$pickupLocation]['pickup_availability'] = isset($shiprocketData['pickup_availability']) ? $shiprocketData['pickup_availability'] : '';
                    $data[$sellerId][$pickupLocation]['courier_name'] = isset($shiprocketData['courier_name']) ? $shiprocketData['courier_name'] : '';
                    $data[$sellerId][$pickupLocation]['delivery_charge_with_cod'] = isset($shiprocketDataWithCod['rate']) ? $shiprocketDataWithCod['rate'] : 0;
                    $data[$sellerId][$pickupLocation]['currency_delivery_charge_with_cod'] = isset($shiprocketDataWithCod['rate']) ? app(CurrencyService::class)->getPriceCurrency($shiprocketDataWithCod['rate']) : 0;
                    $data[$sellerId][$pickupLocation]['delivery_charge_without_cod'] = isset($shiprocketData['rate']) ? $shiprocketData['rate'] : 0;
                    $data[$sellerId][$pickupLocation]['currency_delivery_charge_without_cod'] = isset($shiprocketData['rate']) ? app(CurrencyService::class)->getPriceCurrency($shiprocketData['rate']) : 0;

                    $data[$sellerId][$pickupLocation]['estimate_date'] = isset($shiprocketData['etd']) ? $shiprocketData['etd'] : '';
                    $data[$sellerId][$pickupLocation]['estimate_days'] = isset($shiprocketData['estimated_delivery_days']) ? $shiprocketData['estimated_delivery_days'] : '';

                    $minDays = isset($shiprocketData['estimated_delivery_days']) && (empty($minDays) || $shiprocketData['estimated_delivery_days'] < $minDays) ? $shiprocketData['estimated_delivery_days'] : $minDays;
                    $maxDays = isset($shiprocketData['estimated_delivery_days']) && (empty($maxDays) || $shiprocketData['estimated_delivery_days'] > $maxDays) ? $shiprocketData['estimated_delivery_days'] : $maxDays;

                    $deliveryChargeWithCod += $data[$sellerId][$pickupLocation]['delivery_charge_with_cod'];
                    $deliveryChargeWithoutCod += $data[$sellerId][$pickupLocation]['delivery_charge_without_cod'];
                }
            }
        }

        $deliveryDay = ($minDays == $maxDays) ? $minDays : $minDays . '-' . $maxDays;
        $shippingParcels = [
            'error' => false,
            'estimated_delivery_days' => $deliveryDay,
            'estimate_date' => isset($shiprocketData['etd']) ? $shiprocketData['etd'] : '',
            'delivery_charge' => 0,
            'delivery_charge_with_cod' => round($deliveryChargeWithCod),
            'currency_delivery_charge_with_cod' => app(CurrencyService::class)->getPriceCurrency($deliveryChargeWithCod),
            'delivery_charge_without_cod' => round($deliveryChargeWithoutCod),
            'currency_delivery_charge_without_cod' => app(CurrencyService::class)->getPriceCurrency($deliveryChargeWithoutCod),
            'data' => $data
        ];

        return $shippingParcels;
    }

    public function getShipmentId($itemId, $orderId)
    {
        $query = OrderTracking::select('*')
            ->where('order_id', $orderId)
            ->whereRaw('FIND_IN_SET(?, order_item_id) <> 0', [$itemId])
            ->get()
            ->toArray();

        return !empty($query) ? $query : false;
    }
    public function shiprocketRecommendedData($shiprocketData)
    {
        $result = [];


        if (isset($shiprocketData['data']) && !empty($shiprocketData['data'])) {

            if (isset($shiprocketData['data']['recommended_courier_company_id'])) {
                foreach ($shiprocketData['data']['available_courier_companies'] as $rd) {
                    if ($shiprocketData['data']['recommended_courier_company_id'] == $rd['courier_company_id']) {
                        $result = $rd;
                        break;
                    }
                }
            } else {
                foreach ($shiprocketData['data']['available_courier_companies'] as $rd) {
                    if ($rd['courier_company_id']) {
                        $result = $rd;
                        break;
                    }
                }
            }
            return $result;
        } else {
            return $shiprocketData;
        }
    }

    function makeShippingParcels(Collection $data)
    {
        $parcels = collect();

        $data->each(function ($product) use (&$parcels) {

            if (trim($product->pickup_location) !== '') {

                $sellerId = $product->seller_id;
                $pickupLocation = $product->pickup_location;
                $weight = $product->weight;

                if (!$parcels->has($sellerId)) {
                    $parcels->put($sellerId, collect());
                }

                if (!$parcels[$sellerId]->has($pickupLocation)) {
                    $parcels[$sellerId]->put($pickupLocation, collect(['weight' => 0]));
                }

                $parcels[$sellerId][$pickupLocation]['weight'] += $weight * $product->qty;
            }
        });

        return $parcels;
    }
}