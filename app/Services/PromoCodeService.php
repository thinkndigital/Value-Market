<?php

namespace App\Services;
use App\Services\SettingService;
use App\Models\Product;
use App\Models\Promocode;
use Carbon\Carbon;
class PromoCodeService
{
    public function getPromoCodes($limit = null, $offset = null, $sort = 'id', $order = 'DESC', $search = null, $store_id = null)
    {
        $query = Promocode::query()
            ->selectRaw('*, DATEDIFF(end_date, start_date) as remaining_days')
            ->where('status', 1)
            ->where('store_id', $store_id)
            ->where('list_promocode', 1)
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now());

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('promo_code', 'LIKE', "%{$search}%")
                    ->orWhere('message', 'LIKE', "%{$search}%")
                    ->orWhere('start_date', 'LIKE', "%{$search}%")
                    ->orWhere('end_date', 'LIKE', "%{$search}%")
                    ->orWhere('discount', 'LIKE', "%{$search}%")
                    ->orWhere('repeat_usage', 'LIKE', "%{$search}%")
                    ->orWhere('max_discount_amount', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $promoCodes = $query
            ->orderBy($sort, $order)
            ->when($limit !== null, function ($q) use ($limit, $offset) {
                return $q->skip($offset)->take($limit);
            })
            ->get();

        $data = $promoCodes->map(function ($row) {
            return [
                'id' => $row->id,
                'promo_code' => $row->promo_code,
                'message' => $row->message,
                'store_id' => $row->store_id,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'discount' => $row->discount,
                'repeat_usage' => $row->repeat_usage == 1 ? 'Allowed' : 'Not Allowed',
                'min_order_amt' => $row->minimum_order_amount,
                'no_of_users' => $row->no_of_users,
                'discount_type' => $row->discount_type,
                'max_discount_amt' => $row->max_discount_amount,
                'image' => app(MediaService::class)->getMediaImageUrl($row->image),
                'no_of_repeat_usage' => $row->no_of_repeat_usage,
                'status' => $row->status,
                'is_cashback' => $row->is_cashback,
                'list_promocode' => $row->list_promocode,
                'remaining_days' => $row->remaining_days,
            ];
        });

        return [
            'error' => $promoCodes->isEmpty(),
            'message' => $promoCodes->isEmpty() ? 'Promo code(s) does not exist' : 'Promo code(s) retrieved successfully',
            'total' => $total,
            'data' => $data,
        ];
    }
    public function validatePromoCode($promo_code, $user_id, $final_total, $for_place_order = 0, $language_code = '')
    {
        if (empty($promo_code)) {
            return response()->json(['error' => true, 'message' => 'Promo code is required']);
        }

        $currentDate = Carbon::now()->toDateString();

        // Promo by code or ID depending on context
        if ($for_place_order == 1) {
            $promo = PromoCode::withCount([
                'orders as promo_used_counter',
                'orders as user_promo_usage_counter' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }
            ])
                ->where('id', $promo_code)
                ->where('status', 1)
                ->whereDate('start_date', '<=', $currentDate)
                ->whereDate('end_date', '>=', $currentDate)
                ->first();
        } else {
            $promo = PromoCode::withCount([
                'orders as promo_used_counter',
                'orders as user_promo_usage_counter' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }
            ])
                ->where('promo_code', $promo_code)
                ->where('status', 1)
                ->whereDate('start_date', '<=', $currentDate)
                ->whereDate('end_date', '>=', $currentDate)
                ->first();
        }

        if (!$promo) {
            return response()->json([
                'error' => true,
                'message' => 'The promo code is not available or expired',
                'language_message_key' => 'the_promo_code_is_not_available_or_expired',
                'data' => ['final_total' => strval(floatval($final_total))]
            ]);
        }

        if ($promo->promo_used_counter >= $promo->no_of_users) {
            return response()->json([
                'error' => true,
                'message' => "This promo code is applicable only for first {$promo->no_of_users} users",
                'data' => ['final_total' => strval(floatval($final_total))]
            ]);
        }

        if ($final_total < $promo->minimum_order_amount) {
            return response()->json([
                'error' => true,
                'message' => "This promo code is applicable only for amount greater than or equal to {$promo->minimum_order_amount}",
                'language_message_key' => 'this_promo_code_is_applicable_only_for_amount_greater_than_or_equal_to',
                'data' => ['final_total' => strval(floatval($final_total))]
            ]);
        }

        $canUse = false;

        if ($promo->repeat_usage == 1 && $promo->user_promo_usage_counter <= $promo->no_of_repeat_usage) {
            $canUse = true;
        } elseif ($promo->repeat_usage == 0 && $promo->user_promo_usage_counter == 0) {
            $canUse = true;
        }

        if (!$canUse) {
            return response()->json([
                'error' => true,
                'message' => 'This promo code cannot be redeemed as it exceeds the usage limit',
                'language_message_key' => 'promo_code_can_not_be_redeemed_as_it_exceeds_the_usage_limit',
                'data' => ['final_total' => strval(floatval($final_total))]
            ]);
        }

        // Calculate Discount
        $promo_code_discount = $promo->discount_type === 'percentage'
            ? floatval($final_total * $promo->discount / 100)
            : floatval($promo->discount);

        if ($promo_code_discount > $promo->max_discount_amount) {
            $promo_code_discount = floatval($promo->max_discount_amount);
        }

        $total = ($promo->is_cashback ?? 0) == 0
            ? floatval($final_total) - $promo_code_discount
            : floatval($final_total);

        // Final Formatting
        $promo->final_total = strval(floatval($total));
        $promo->final_discount = strval(floatval($promo_code_discount));
        $promo->title = app(TranslationService::class)->getDynamicTranslation(PromoCode::class, 'title', $promo->id, $language_code);
        $promo->message = app(TranslationService::class)->getDynamicTranslation(PromoCode::class, 'message', $promo->id, $language_code);
        $promo->currency_final_total_data = app(CurrencyService::class)->getPriceCurrency($promo->final_total);
        $promo->currency_final_discount_data = app(CurrencyService::class)->getPriceCurrency($promo->final_discount);
        $promo->image = isset($promo->image) ? app(MediaService::class)->getImageUrl($promo->image) : '';

        return response()->json([
            'error' => false,
            'message' => 'The promo code is valid',
            'language_message_key' => 'the_promo_code_is_valid',
            'data' => [$promo]
        ]);
    }
    public function recalculatePromoDiscount($promo_code, $promo_discount, $user_id, $total, $payment_method, $delivery_charge, $wallet_balance)
    {

        /* recalculate promocode discount if the status of the order_items is cancelled or returned */
        $promo_code_discount = $promo_discount;
        if (isset($promo_code) && !empty($promo_code) && $promo_code != ' ') {

            $promo_code = $this->validatePromoCode($promo_code, $user_id, $total, true)->original;

            if ($promo_code['error'] == false) {

                if ($promo_code['data'][0]->discount_type == 'percentage') {
                    $promo_code_discount = floatval($total * $promo_code['data'][0]->discount / 100);
                } else {
                    $promo_code_discount = $promo_code['data'][0]->discount;
                }
                if (trim(strtolower($payment_method)) != 'cod' && $payment_method != 'Bank Transfer') {
                    /* If any other payment methods are used like razorpay, paytm, flutterwave or stripe then
                         obviously customer would have paid complete amount so making total_payable = 0*/
                    $total_payable = 0;
                    if ($promo_code_discount > $promo_code['data'][0]->max_discount_amount) {
                        $promo_code_discount = $promo_code['data'][0]->max_discount_amount;
                    }
                } else {
                    /* also check if the previous discount and recalculated discount are
                         different or not, then only modify total_payable*/
                    if ($promo_code_discount <= $promo_code['data'][0]->max_discount_amount && $promo_discount != $promo_code_discount) {
                        $total_payable = floatval($total) + $delivery_charge - $promo_code_discount - $wallet_balance;
                    } else if ($promo_discount != $promo_code_discount) {
                        $total_payable = floatval($total) + $delivery_charge - $promo_code['data'][0]->max_discount_amount - $wallet_balance;
                        $promo_code_discount = $promo_code['data'][0]->max_discount_amount;
                    }
                }
            } else {
                $promo_code_discount = 0;
            }
        }
        return $promo_code_discount;
    }
}