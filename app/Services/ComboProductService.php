<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Zipcode;
use App\Models\ComboProduct;
use App\Models\ComboProductAttributeValue;
use App\Models\Zone;
use App\Models\ComboProductFaq;
use App\Models\ComboProductRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\TranslationService;
use App\Services\DeliveryService;
use App\Services\MediaService;
use App\Services\CurrencyService;
use App\Services\SettingService;
class ComboProductService
{
    public function fetchComboProduct($user_id = NULL, $filter = NULL, $id = NULL, $limit = NULL, $offset = NULL, $sort = 'p.id', $order = 'DESC', $return_count = NULL, $is_deliverable = NULL, $seller_id = NULL, $store_id = NULL, $category_id = '', $brand_id = '', $type = '', $from_seller = '', $language_code = '')
    {

        // Load settings
        $settings = app(SettingService::class)->getSettings('system_settings', true);
        $settings = json_decode($settings, true);
        $low_stock_limit = $settings['low_stock_limit'] ?? 5;

        // Build the query
        $query = ComboProduct::with([
            'sellerData',
            'sellerStore',
            'user',
            'taxes',
            'products',
            'attributeValues',
            'productVariants',
            'comboProductAttributeValues',
        ])
            ->select([
                'combo_products.*',
                'seller_store.rating as seller_rating',
                'seller_store.slug as seller_slug',
                'seller_store.no_of_ratings as seller_no_of_ratings',
                'seller_store.logo as seller_profile',
                'seller_store.store_name as store_name',
                'seller_store.store_description',
                'users.username as seller_name',
                DB::raw('(SELECT GROUP_CONCAT(taxes.percentage) FROM taxes WHERE FIND_IN_SET(taxes.id, combo_products.tax)) as tax_percentage'),
                DB::raw('GROUP_CONCAT(DISTINCT taxes.id) as tax_id'),
                DB::raw('
                CASE
                    WHEN combo_products.special_price > 0 THEN ((combo_products.price - combo_products.special_price) / combo_products.price) * 100
                    ELSE 0
                END AS cal_discount_percentage
            '),
                'product_variants.attribute_value_ids AS variant_attribute_value_ids',
                'products.id AS product_id',
                'products.name AS product_name',
                'products.category_id',
                'products.brand',
                DB::raw('GROUP_CONCAT(DISTINCT attribute_values.id) AS attr_value_ids'),
            ])
            ->leftJoin('seller_data', 'combo_products.seller_id', '=', 'seller_data.id')
            ->Join('seller_store', function ($join) use ($store_id) {
                $join->on('combo_products.seller_id', '=', 'seller_store.seller_id')
                    ->where('seller_store.store_id', '=', $store_id);
            })
            ->leftJoin('users', 'users.id', '=', 'seller_data.user_id')
            ->leftJoin('taxes', fn($join) => $join->on(DB::raw('FIND_IN_SET(taxes.id, combo_products.tax)'), '>', DB::raw('0')))
            ->leftJoin('products', fn($join) => $join->on(DB::raw('FIND_IN_SET(products.id, combo_products.product_ids)'), '>', DB::raw('0')))
            ->leftJoin('attribute_values', fn($join) => $join->on(DB::raw('FIND_IN_SET(attribute_values.id, combo_products.attribute_value_ids)'), '>', DB::raw('0')))
            ->leftJoin('product_attributes', 'product_attributes.id', '=', 'attribute_values.attribute_id')
            ->leftJoin('product_variants', fn($join) => $join->on(DB::raw('FIND_IN_SET(product_variants.product_id, combo_products.product_ids)'), '>', DB::raw('0')))
            ->leftJoin('combo_product_attribute_values', fn($join) => $join->on(DB::raw('FIND_IN_SET(combo_product_attribute_values.id, combo_products.attribute_value_ids)'), '>', DB::raw('0')))
            ->leftJoin('combo_product_attributes', 'combo_product_attributes.id', '=', 'combo_product_attribute_values.combo_product_attribute_id')
            ->groupBy('combo_products.id');

        // ⭐ Filter by store ID
        $query->when(!empty($store_id), fn($q) => $q->where('combo_products.store_id', $store_id));

        // ⭐ Filter by active products and seller
        $query->when(
            !isset($filter['show_only_active_products']) || $filter['show_only_active_products'] != 0,
            fn($q) => $q->where('combo_products.status', 1)
                ->whereHas('sellerData', fn($sub) => $sub->where('status', 1))
        );

        // ⭐ Filter by rating
        $query->when(!empty($filter['rating']), fn($q) => $q->where('combo_products.rating', '>=', $filter['rating']));

        // ⭐ Filter by price (special_price or price)
        $query->when(
            !empty($filter['minimum_price']) || !empty($filter['maximum_price']),
            function ($q) use ($filter) {
                $min = $filter['minimum_price'] ?? 0;
                $max = $filter['maximum_price'] ?? PHP_INT_MAX;
                $q->where(function ($sub) use ($min, $max) {
                    $sub->where('combo_products.special_price', '>', 0)
                        ->whereBetween('combo_products.special_price', [$min, $max])
                        ->orWhere(function ($sub2) use ($min, $max) {
                            $sub2->where('combo_products.special_price', 0)
                                ->whereBetween('combo_products.price', [$min, $max]);
                        });
                });
            }
        );

        // ⭐ Full-text search (tags and title)
        $query->when(!empty($filter['search']), function ($q) use ($filter) {
            $tags = preg_split('/\s+/', $filter['search'], -1, PREG_SPLIT_NO_EMPTY);
            $q->where(function ($sub) use ($tags, $filter) {
                foreach ($tags as $tag) {
                    $sub->orWhere('combo_products.tags', 'like', '%' . trim($tag) . '%');
                }
                $sub->orWhere('combo_products.title', 'like', '%' . trim($filter['search']) . '%');
            });
        });

        // ⭐ Filter by tags (comma-separated)
        $query->when(!empty($filter['tags']), function ($q) use ($filter) {
            $tags = preg_split('/[,\|]/', $filter['tags'], -1, PREG_SPLIT_NO_EMPTY);

            $q->where(function ($sub) use ($tags) {
                foreach ($tags as $tag) {
                    $sub->orWhere('combo_products.tags', 'like', '%' . trim($tag) . '%');
                }
            });
        });

        // ⭐ Filter by brand
        $query->when(!empty($brand_id), fn($q) => $q->whereIn('products.brand', (array) $brand_id));

        // ⭐ Filter by slug
        $query->when(!empty($filter['slug']), fn($q) => $q->where('combo_products.slug', $filter['slug']));

        // ⭐ Filter by seller ID
        $query->when(!empty($seller_id), fn($q) => $q->where('combo_products.seller_id', $seller_id));

        // ⭐ Filter by attribute value IDs
        $query->when(!empty($filter['attribute_value_ids']), function ($q) use ($filter) {

            foreach ($filter['attribute_value_ids'] as $valueId) {
                $q->whereRaw('FIND_IN_SET(?, combo_products.attribute_value_ids)', [$valueId]);
                $q->orWhereRaw('FIND_IN_SET(?, product_variants.attribute_value_ids)', [$valueId]);
            }

        });

        // ⭐ Filter by category
        $query->when(!empty($category_id), fn($q) => $q->whereIn('products.category_id', (array) $category_id));

        // ⭐ Filter by product type
        $query->when(!empty($type), function ($q) use ($type) {
            if ($type === 'physical_product') {
                $q->where('combo_products.product_type', 'physical_product');
            } elseif ($type === 'digital_product') {
                $q->where('combo_products.product_type', 'digital_product');
            }
        });

        // ⭐ Filter by stock availability
        $query->when(
            !empty($filter['show_only_stock_product']) && $filter['show_only_stock_product'] == 1,
            fn($q) => $q->whereNotNull('combo_products.stock')
        );

        // ⭐ Filter by low stock or out of stock
        $query->when(
            !empty($filter['flag']) && $filter['flag'] !== 'null',
            function ($q) use ($filter, $low_stock_limit) {
                if ($filter['flag'] == 'low') {
                    $q->whereHas('products', function ($sub) use ($low_stock_limit) {
                        $sub->where(function ($sub2) use ($low_stock_limit) {
                            $sub2->whereNotNull('products.stock_type')
                                ->where('products.stock', '<=', $low_stock_limit)
                                ->where('products.availability', 1);
                        })->orWhere(function ($sub2) use ($low_stock_limit) {
                            $sub2->where('products.stock', '<=', $low_stock_limit)
                                ->where('products.availability', 1);
                        });
                    });
                } else {
                    $q->whereHas('products', fn($sub) => $sub->where('products.availability', 0)
                        ->orWhere('products.stock', 0));
                }
            }
        );

        // ⭐ Filter by most selling products
        $query->when(
            !empty($filter['product_type']) && strtolower($filter['product_type']) === 'most_selling_products',
            fn($q) => $q->orderBy('combo_products.total_sale', 'desc')
        );

        // ⭐ Filter by products on sale
        $query->when(
            !empty($filter['product_type']) && strtolower($filter['product_type']) === 'products_on_sale',
            fn($q) => $q->where('combo_products.special_price', '>', 0)
        );

        // ⭐ Filter by top rated products
        $query->when(
            !empty($filter['product_type']) && strtolower($filter['product_type']) === 'top_rated_products',
            fn($q) => $q->where('combo_products.no_of_ratings', '>', 0)
                ->orderBy('combo_products.rating', 'desc')
                ->orderBy('combo_products.no_of_ratings', 'desc')
        );

        // ⭐ Filter by top rated including all products
        $query->when(
            !empty($filter['product_type']) && strtolower($filter['product_type']) === 'top_rated_product_including_all_products',
            fn($q) => $q->orderBy('combo_products.rating', 'desc')
                ->orderBy('combo_products.no_of_ratings', 'desc')
        );

        // ⭐ Filter by new added products
        $query->when(
            !empty($filter['product_type']) && $filter['product_type'] === 'new_added_products',
            fn($q) => $q->orderBy('combo_products.id', 'desc')
        );

        // ⭐ Filter by old products first
        $query->when(
            !empty($filter['product_type']) && $filter['product_type'] === 'old_products_first',
            fn($q) => $q->orderBy('combo_products.id', 'asc')
        );

        // ⭐ Filter by product IDs
        $query->when(!empty($id), function ($q) use ($id, $filter) {
            if (is_array($id)) {
                $q->whereIn('combo_products.id', $id);
            } else {
                if (!empty($filter['is_similar_products']) && $filter['is_similar_products'] == '1') {
                    $q->where('combo_products.id', '!=', $id);
                } else {
                    $q->where('combo_products.id', $id);
                }
            }
        });

        // ⭐ Filter by discount
        $query->when(!empty($filter['discount']), function ($q) use ($filter) {
            $discount = $filter['discount'];
            $q->havingRaw('cal_discount_percentage <= ?', [$discount])
                ->havingRaw('cal_discount_percentage > 0');
        });

        // ⭐ Sort by price
        $query->when(
            $sort === 'p.price' && !empty($sort),
            fn($q) => $q->orderByRaw("
                IF(combo_products.special_price > 0,
                    IF(combo_products.is_prices_inclusive_tax = 1,
                        combo_products.special_price,
                        combo_products.special_price + ((combo_products.special_price * combo_products.tax) / 100)
                    ),
                    IF(combo_products.is_prices_inclusive_tax = 1,
                        combo_products.price,
                        combo_products.price + ((combo_products.price * combo_products.tax) / 100)
                    )
                ) " . ($order ?? 'asc')
            )
        );

        // ⭐ Sort by discount
        $query->when(
            $sort === 'discount' || !empty($filter['discount']),
            fn($q) => $q->orderByRaw('combo_products.special_price > 0 DESC')
                ->when(!empty($filter['discount']), fn($sub) => $sub->orderBy('cal_discount_percentage', 'desc'))
        );

        // ⭐ Default sorting
        $query->when(
            $sort !== 'p.price' && empty($filter['product_type']),
            fn($q) => $q->orderBy('combo_products.id', 'desc')
        );

        // Get total count and products

        $totalCount = count($query->get());
        // $totalCount = (clone $query)->count();
        if ($limit !== null || $offset !== null) {

            $query->skip($offset)->take($limit);
        }
        $product = $query->get();

        // dd($query->toSql(),$query->getBindings());

        // Additional data
        $category_ids = $product->pluck('category_id')->unique()->values()->all();
        $brand_ids = $product->pluck('brand')->unique()->values()->all();
        $min_price = $this->getComboPrice('min', $store_id);
        $max_price = $this->getComboPrice('max', $store_id);

        // Weekly sales
        $weekly_sales = DB::table('order_items as oi')
            ->join('combo_products as cp', 'cp.id', '=', 'oi.product_variant_id')
            ->select('cp.id', DB::raw('SUM(oi.quantity) as weekly_sale'))
            ->where('oi.created_at', '>=', now()->subDays(7))
            ->where('oi.order_type', '=', 'combo_order')
            ->groupBy('cp.id')
            ->pluck('weekly_sale', 'id')
            ->toArray();
        $max_weekly_sale = !empty($weekly_sales) ? max($weekly_sales) : 0;

        if (!empty($product)) {

            for ($i = 0; $i < count($product); $i++) {

                $rating = $this->fetchComboRating($product[$i]->id, '', 8, 0, '', 'desc', '', 1);
                $product[$i]->translated_name = json_decode($product[$i]->title);
                $product[$i]->translated_short_description = json_decode($product[$i]->short_description);
                $product[$i]->product_name = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product[$i]->product_id, $language_code);
                $product[$i]->title = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'title', $product[$i]->id, $language_code);
                $product[$i]->short_description = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'short_description', $product[$i]->id, $language_code);
                if ((isset($product[$i]->is_prices_inclusive_tax) && $product[$i]->is_prices_inclusive_tax == 0)) {

                    if (isset($from_seller) && $from_seller == 1) {
                        //in seller get_products return orignal price without tax
                        $percentage = (isset($product[$i]->tax_percentage) && intval($product[$i]->tax_percentage) > 0 && $product[$i]->tax_percentage != null) ? $product[$i]->tax_percentage : '0';
                        $product[$i]->price = strval($product[$i]->price);
                        $product[$i]->price_with_tax = strval(calculatePriceWithTax($percentage, $product[$i]->price));

                        $product[$i]->special_price = strval($product[$i]->special_price);
                        $product[$i]->special_price_with_tax = strval(calculatePriceWithTax($percentage, $product[$i]->special_price));

                        //convert price in multi currency
                        $product[$i]->currency_price_data = app(CurrencyService::class)->getPriceCurrency($product[$i]->price);
                        $product[$i]->currency_special_price_data = app(CurrencyService::class)->getPriceCurrency($product[$i]->special_price);
                    } else {

                        $percentage = (isset($product[$i]->tax_percentage) && intval($product[$i]->tax_percentage) > 0 && $product[$i]->tax_percentage != null) ? $product[$i]->tax_percentage : '0';

                        $product[$i]->price = strval(calculatePriceWithTax($percentage, $product[$i]->price));

                        $product[$i]->special_price = strval(calculatePriceWithTax($percentage, $product[$i]->special_price));
                        //convert price in multi currency
                        $product[$i]->currency_price_data = app(CurrencyService::class)->getPriceCurrency($product[$i]->price);
                    }
                } else {

                    if (isset($from_seller) && $from_seller == 1) {
                        //in seller get_products return orignal price without tax
                        $percentage = (isset($product[$i]->tax_percentage) && intval($product[$i]->tax_percentage) > 0 && $product[$i]->tax_percentage != null) ? $product[$i]->tax_percentage : '0';
                        $product[$i]->price = strval($product[$i]->price);
                        $product[$i]->price_with_tax = $product[$i]->price;

                        $product[$i]->special_price = strval($product[$i]->special_price);
                        $product[$i]->special_price_with_tax = $product[$i]->special_price;

                        //convert price in multi currency
                        $product[$i]->currency_price_data = app(CurrencyService::class)->getPriceCurrency($product[$i]->price);
                        $product[$i]->currency_special_price_data = app(CurrencyService::class)->getPriceCurrency($product[$i]->special_price);
                    } else {
                        $product[$i]->price = strval($product[$i]->price);
                        $product[$i]->special_price = strval($product[$i]->special_price);

                        //convert price in multi currency
                        $product[$i]->currency_price_data = app(CurrencyService::class)->getPriceCurrency($product[$i]->price);
                    }
                }
                $product[$i]->product_rating_data = isset($rating) ? $rating : [];

                $product[$i]->tax_id = ((isset($product[$i]->tax_id) && intval($product[$i]->tax_id) > 0) && $product[$i]->tax_id != "") ? $product[$i]->tax_id : '0';
                $taxes = [];
                $tax_ids = explode(",", $product[$i]->tax_id);
                $taxes = Tax::whereIn('id', $tax_ids)->get()->toArray();
                $taxes = array_column($taxes, 'title');

                $translatedTaxes = [];

                foreach ($taxes as $tax) {
                    $translatedTaxes[] = app(TranslationService::class)->getDynamicTranslation(Tax::class, 'title', $product[$i]->tax_id, $language_code);
                }

                $product[$i]->tax_names = implode(",", $translatedTaxes);
                $tax_percentages = [];
                $tax_ids = explode(",", $product[$i]->tax_id);
                $tax_percentages = Tax::whereIn('id', $tax_ids)->get()->toArray();
                $tax_percentages = array_column($tax_percentages, 'percentage');
                $product[$i]->tax_percentage = implode(",", $tax_percentages);

                $product[$i]->attributes = $this->getComboAttributeValuesByPid($product[$i]->id);
                if ($product[$i]->other_images === null || $product[$i]->other_images === "null") {
                    $product[$i]->other_images_relative_path = [];
                } else {

                    $product[$i]->other_images_relative_path = !empty($product[$i]->other_images) ? json_decode($product[$i]->other_images) : [];
                }

                $product[$i]->min_max_price = $this->getMinMaxPriceOfComboProduct($product[$i]->id);
                // $product[$i]->min_max_price['discount_in_percentage'] = isset($product[$i]->min_max_price['discount_in_percentage']) && $product[$i]->min_max_price['discount_in_percentage'] !== null ? $product[$i]->min_max_price['discount_in_percentage'] : '';
                $product[$i]->type = "combo-product";
                $product[$i]->stock_type = isset($product[$i]->stock_type) && ($product[$i]->stock_type != '') ? $product[$i]->stock_type : '';
                $product[$i]->product_variant_ids = isset($product[$i]->product_variant_ids) && ($product[$i]->product_variant_ids != null) ? $product[$i]->product_variant_ids : '';
                $product[$i]->stock = isset($product[$i]->stock) && ($product[$i]->stock != '') ? (string) $product[$i]->stock : '';
                $other_images = $other_images_sm = $other_images_md = json_decode($product[$i]->other_images, 1);

                if (!empty($other_images)) {

                    $k = 0;
                    foreach ($other_images_md as $row) {
                        $other_images_md[$k] = app(MediaService::class)->getImageUrl($row, 'thumb', 'md');
                        $k++;
                    }
                    $other_images_md = (array) $other_images_md;
                    $other_images_md = array_values($other_images_md);
                    $product[$i]->other_images_md = $other_images_md;

                    $k = 0;
                    foreach ($other_images_sm as $row) {
                        $other_images_sm[$k] = app(MediaService::class)->getImageUrl($row, 'thumb', 'sm');
                        $k++;
                    }
                    $other_images_sm = (array) $other_images_sm;
                    $other_images_sm = array_values($other_images_sm);
                    $product[$i]->other_images_sm = $other_images_sm;

                    $k = 0;
                    foreach ($other_images as $row) {
                        $other_images[$k] = app(MediaService::class)->getMediaImageUrl($row);
                        $k++;
                    }
                    $other_images = (array) $other_images;
                    $other_images = array_values($other_images);
                    $product[$i]->other_images = $other_images;
                } else {
                    $product[$i]->other_images = array();
                    $product[$i]->other_images_sm = array();
                    $product[$i]->other_images_md = array();
                }

                $product[$i]->delivery_charges = isset($product[$i]->delivery_charges) && ($product[$i]->delivery_charges != '') ? $product[$i]->delivery_charges : '';
                $product[$i]->download_type = isset($product[$i]->download_type) && ($product[$i]->download_type != '') ? $product[$i]->download_type : '';
                $product[$i]->download_link = isset($product[$i]->download_link) && ($product[$i]->download_link != '') ? app(MediaService::class)->getMediaImageUrl($product[$i]->download_link) : '';
                $product[$i]->relative_path = isset($product[$i]->image) && !empty($product[$i]->image) ? $product[$i]->image : '';

                $product[$i]->attr_value_ids = isset($product[$i]->attr_value_ids) && !empty($product[$i]->attr_value_ids) ? $product[$i]->attr_value_ids : '';
                if (isset($user_id) && $user_id != null) {
                    $fav = Favorite::where(['product_id' => $product[$i]->id, 'user_id' => $user_id, 'product_type' => 'combo'])->count();

                    $product[$i]->is_favorite = $fav;
                } else {
                    $product[$i]->is_favorite = 0;
                }

                $product[$i]->name = outputEscaping($product[$i]->title);
                $image = app(MediaService::class)->getMediaImageUrl($product[$i]->image);

                $product[$i]->image = $image;
                $product[$i]->store_name = outputEscaping($product[$i]->store_name);
                $product[$i]->seller_rating = (isset($product[$i]->seller_rating) && !empty($product[$i]->seller_rating)) ? outputEscaping(number_format($product[$i]->seller_rating, 1)) : "0";
                $product[$i]->store_description = (isset($product[$i]->store_description) && !empty($product[$i]->store_description)) ? outputEscaping($product[$i]->store_description) : "";
                $product[$i]->has_similar_product = (isset($product[$i]->has_similar_product) && !empty($product[$i]->has_similar_product)) ? outputEscaping($product[$i]->has_similar_product) : "";
                $product[$i]->similar_product_ids = (isset($product[$i]->similar_product_ids) && !empty($product[$i]->similar_product_ids)) ? outputEscaping($product[$i]->similar_product_ids) : "";
                $product[$i]->seller_profile = outputEscaping(asset($product[$i]->seller_profile));
                $product[$i]->seller_name = outputEscaping($product[$i]->seller_name);

                $product[$i]->description = (isset($product[$i]->description) && !empty($product[$i]->description)) ? outputEscaping($product[$i]->description) : "";
                $product[$i]->pickup_location = isset($product[$i]->pickup_location) && !empty($product[$i]->pickup_location) ? $product[$i]->pickup_location : '';

                $product[$i]->seller_slug = isset($product[$i]->seller_slug) && !empty($product[$i]->seller_slug) ? outputEscaping($product[$i]->seller_slug) : "";

                // new arrival tags based on newly added product(weekly)

                if (isset($product[$i]->created_at) && strtotime($product[$i]->created_at) >= strtotime('-7 days')) {
                    $product[$i]->new_arrival = true;
                } else {
                    $product[$i]->new_arrival = false;
                }

                // end new arrival tags based on newly added product(weekly)


                // best seller tag based on most selling product (weekly)

                $weeklySale = $weekly_sales[$product[$i]->id] ?? 0;
                $product[$i]->best_seller = ($max_weekly_sale > 0 && $weeklySale >= ($max_weekly_sale * 0.8));

                // end best seller tag based on most selling product (weekly)

                if (isset($filter['discount']) && !empty($filter['discount']) && $filter['discount'] != "") {
                    $product[$i]->cal_discount_percentage = outputEscaping(number_format($product[$i]->cal_discount_percentage, 2));
                }
                $product[$i]->cancelable_till = isset($product[$i]->cancelable_till) && !empty($product[$i]->cancelable_till) ? $product[$i]->cancelable_till : '';
                $product[$i]->deliverable_zones_ids = isset($product[$i]->deliverable_zones) && !empty($product[$i]->deliverable_zones) ? $product[$i]->deliverable_zones : '';
                $product[$i]->availability = isset($product[$i]->availability) && ($product[$i]->availability != "") ? intval($product[$i]->availability) : '';
                $product[$i]->sku = isset($product[$i]->sku) && ($product[$i]->sku != "") ? $product[$i]->sku : '';
                /* getting zipcodes from ids */
                if ($product[$i]->deliverable_type != 'NONE' && $product[$i]->deliverable_type != 'ALL') {
                    $zones = [];
                    $zone_ids = explode(",", $product[$i]->deliverable_zones);
                    $zones = Zone::whereIn('id', $zone_ids)->get();

                    $translatedZones = [];

                    foreach ($zones as $zone) {
                        $translatedZones[] = app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code);
                    }

                    $product[$i]->deliverable_zones = implode(",", $translatedZones);
                } else {
                    $product[$i]->deliverable_zones = '';
                }

                $product[$i]->category_name = (isset($product[$i]->category_name) && !empty($product[$i]->category_name)) ? app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $product[$i]->category_id, $language_code) : '';
                /* check product delivrable or not */

                if ($is_deliverable != NULL) {
                    $zipcode = fetchDetails(Zipcode::class, ['zipcode' => $is_deliverable], '*');
                    if (!$zipcode->isEmpty()) {
                        $product[$i]->is_deliverable = app(DeliveryService::class)->isProductDelivarable($type = 'zipcode', $zipcode[0]->id, $product[$i]->id, 'combo');
                    } else {
                        $product[$i]->is_deliverable = false;
                    }
                } else {
                    $product[$i]->is_deliverable = false;
                }
                if ($product[$i]->deliverable_type == 1) {
                    $product[$i]->is_deliverable = true;
                }

                $product[$i]->tags = (!empty($product[$i]->tags)) ? explode(",", $product[$i]->tags) : [];
                $product[$i]->minimum_order_quantity = isset($product[$i]->minimum_order_quantity) && (!empty($product[$i]->minimum_order_quantity)) ? $product[$i]->minimum_order_quantity : 1;
                $product[$i]->quantity_step_size = isset($product[$i]->quantity_step_size) && (!empty($product[$i]->quantity_step_size)) ? $product[$i]->quantity_step_size : 1;
                $product_ids = $product[$i]->product_ids;

                $is_purchased = OrderItems::where([
                    'product_variant_id' => $product[$i]->id,
                    'user_id' => $user_id
                ])->orderBy('id', 'desc')->limit(1)->get()->toArray();
                if (!empty($is_purchased) && strtolower($is_purchased[0]['active_status']) == 'delivered') {

                    $product[$i]->is_purchased = 1;
                } else {

                    $product[$i]->is_purchased = 0;
                }
                $similar_product_ids = $product[$i]->similar_product_ids;
                $product_details = Product::select('id', 'name', 'image', 'type', 'slug', 'category_id', 'brand', 'tax', 'is_prices_inclusive_tax')->whereIn('id', explode(',', $product_ids))->get()->toarray();
                for ($k = 0; $k < count($product_details); $k++) {
                    $product_details[$k]['image'] = asset('storage' . $product_details[$k]['image']);
                    $variants = app(ProductService::class)->getVariantsValuesByPid($product_details[$k]['id']);
                    $tax_percentages = [];
                    $tax_ids = explode(",", $product_details[$k]['tax']);
                    $tax_percentages = Tax::whereIn('id', $tax_ids)->get()->toArray();
                    $tax_percentages = array_column($tax_percentages, 'percentage');
                    $product_details[$k]['tax_percentage'] = implode(",", $tax_percentages);

                    $product_details[$k]['name'] = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product_details[$k]['id'], $language_code);
                    foreach ($variants as &$variant) {

                        $variant['product_name'] = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $variant['product_id'], $language_code);
                        if ((isset($product_details[$k]['is_prices_inclusive_tax']) && $product_details[$k]['is_prices_inclusive_tax'] == 0)) {
                            $percentage = (isset($product_details[$k]['tax_percentage']) && intval($product_details[$k]['tax_percentage']) > 0 && $product_details[$k]['tax_percentage'] != null) ? $product_details[$k]['tax_percentage'] : '';
                            $variant['price'] = strval(calculatePriceWithTax($percentage, $variant['price']));
                            $variant['special_price'] = strval(calculatePriceWithTax($percentage, $variant['special_price']));
                        } else {
                            $variant['price'] = strval($variant['price']);

                            $variant['special_price'] = strval($variant['special_price']);
                        }
                        // Check if 'images' is a string "[]" and convert it to an empty array []
                        if ($variant['images'] == "[]" || $variant['images'] == null) {
                            $variant['images'] = [];
                        } else {
                            $variant['images'] = $variant['images'];
                        }
                        unset($variant['product']);
                    }
                    $product_details[$k]['variants'] = $variants;
                }


                $similar_product_details = ComboProduct::select('title', 'image', 'id')->whereIn('id', explode(',', $similar_product_ids))->get()->toarray();
                for ($s = 0; $s < count($similar_product_details); $s++) {
                    $similar_product_details_image = asset('storage' . $similar_product_details[$s]['image']);
                    $similar_product_details[$s]['image'] = $similar_product_details_image;

                    $similar_product_details[$s]['title'] = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'title', $similar_product_details[$s]['id'], $language_code);
                }

                $product[$i]->product_details = $product_details;
                $product[$i]->similar_product_details = $similar_product_details;

                // if (isset($total_data[0]->cal_discount_percentage)) {
                //     $dicounted_total = array_values(array_filter(explode(',', $total_data[0]->cal_discount_percentage)));
                // } else {
                //     $dicounted_total = 0;
                // }
                // $response['total'] = (isset($filter) && !empty($filter['discount'])) ? count($dicounted_total) : $totalCount;
                $response['total'] = $totalCount;
            }
        }
        $response['min_price'] = (isset($min_price)) ? $min_price : "0";
        $response['max_price'] = (isset($max_price)) ? $max_price : "0";
        // $response['total'] = $totalCount;
        $response['category_ids'] = $category_ids;
        $response['brand_ids'] = $brand_ids;
        $response['combo_product'] = $product;
        return $response;
    }

    public function getComboPrice($type = "max", $store_id = null)
    {
        static $result = null;

        if ($result == null) {
            // Get eligible combo products with active sellers
            $comboProducts = ComboProduct::with('sellerData')
                ->where('status', 1)
                ->where('store_id', $store_id)
                ->get()
                ->filter(function ($product) {
                    return optional($product->sellerData)->status == 1;
                });

            // Compute prices
            $result = $comboProducts->map(function ($product) {
                $basePrice = $product->special_price > 0 ? $product->special_price : $product->price;
                $price = floatval($basePrice);

                if (!$product->is_prices_inclusive_tax && !empty($product->tax)) {
                    $taxIds = array_map('intval', explode(',', $product->tax));
                    $taxPercentage = Tax::whereIn('id', $taxIds)->where('status', 1)->sum('percentage');
                    $price += $price * ($taxPercentage / 100);
                }

                return $price;
            })->toArray();
        }

        if (!empty($result)) {
            return $type == 'min' ? min($result) : max($result);
        }

        return 0;
    }

    public function getComboAttributeValuesByPid($id)
    {
        $comboProduct = ComboProduct::find($id);

        if (!$comboProduct || empty($comboProduct->attribute_value_ids)) {
            return [];
        }

        $attributeValueIds = explode(',', $comboProduct->attribute_value_ids);

        // Fetch attribute values with their attributes
        $attributeValues = ComboProductAttributeValue::with('ComboAttribute')
            ->whereIn('id', $attributeValueIds)
            ->where('status', 1)
            ->whereHas('ComboAttribute', function ($query) {
                $query->where('status', 1);
            })
            ->orderBy('id')
            ->get();

        // Group values by attribute
        $grouped = $attributeValues->groupBy('ComboAttribute.id');

        $result = [];

        foreach ($grouped as $attributeId => $values) {
            $result[] = [
                'ids' => $values->pluck('id')->implode(','),
                'value' => $values->pluck('value')->implode(', '),
                'attr_name' => $values->first()->ComboAttribute->name,
                'name' => $values->first()->ComboAttribute->name,
                'attr_id' => $attributeId,
            ];
        }

        return $result;
    }
    public function getMinMaxPriceOfComboProduct($product_id = '')
    {
        // Fetch the combo product with optional filter
        $query = ComboProduct::query();

        if (!empty($product_id)) {
            $query->where('id', $product_id);
        }

        $comboProducts = $query->get();

        if ($comboProducts->isEmpty()) {
            return [
                'min_price' => 0,
                'max_price' => 0,
                'special_price' => 0,
                'max_special_price' => 0,
                'discount_in_percentage' => 0,
            ];
        }

        // We'll collect all prices
        $prices = [];
        $specialPrices = [];

        foreach ($comboProducts as $product) {
            $price = floatval($product->price);
            $specialPrice = floatval($product->special_price);

            // Default tax addition is 0
            $priceTax = $specialPriceTax = 0;

            // If tax is NOT included in price
            if (!$product->is_prices_inclusive_tax && !empty($product->tax)) {
                $taxIds = array_filter(array_map('intval', explode(',', $product->tax)));
                $totalTax = Tax::whereIn('id', $taxIds)
                    ->where('status', 1)
                    ->sum('percentage');

                $priceTax = $price * ($totalTax / 100);
                $specialPriceTax = $specialPrice * ($totalTax / 100);
            }

            $prices[] = $price + $priceTax;
            $specialPrices[] = $specialPrice > 0 ? $specialPrice + $specialPriceTax : 0;
        }

        $min_price = min($prices);
        $max_price = max($prices);
        $special_price = min($specialPrices);
        $max_special_price = max($specialPrices);

        $discount_in_percentage = findDiscountInPercentage($special_price, $min_price);

        return compact(
            'min_price',
            'max_price',
            'special_price',
            'max_special_price',
            'discount_in_percentage'
        );
    }


    public function validateComboStock($product_ids, $qtns)
    {
        $is_exceed_allowed_quantity_limit = false;
        $error = false;

        foreach ($product_ids as $index => $product_id) {
            $combo_product = ComboProduct::where('id', $product_id)
                ->first();

            if ($combo_product->total_allowed_quantity !== null && $combo_product->total_allowed_quantity >= 0) {

                $total_allowed_quantity = intval($combo_product->total_allowed_quantity) - intval($qtns[$index]);
                if ($total_allowed_quantity < 0) {
                    $error = true;
                    $is_exceed_allowed_quantity_limit = true;
                    $response['message'] = 'One of the products quantity exceeds the allowed limit. Please deduct some quantity in order to purchase the item';
                    break;
                }
            }

            if ($combo_product->stock !== null && $combo_product->stock !== '') {
                if ($combo_product->stock == 0) {
                    if ($combo_product->product->stock !== null && $combo_product->product->stock !== '') {
                        $stock = intval($combo_product->product->stock) - intval($qtns[$index]);
                        if ($stock < 0 || $combo_product->product->availability == 0) {
                            $error = true;
                            $response['message'] = 'One of the product is out of stock.';
                        }
                    }
                }
            }
        }
        if ($error) {
            $response['error'] = true;
            if ($is_exceed_allowed_quantity_limit) {
                $response['message'] = 'One of the products quantity exceeds the allowed limit. Please deduct some quantity in order to purchase the item';
            } else {
                $response['message'] = "One of the product is out of stock.";
            }
        } else {
            $response['error'] = false;
            $response['message'] = "Stock available for purchasing.";
        }

        return $response;
    }
    public function updateComboStock($id, $quantity, $type = '')
    {
        if ($type == 'add' || $type == 'subtract') {

            // Find the combo product by its ID
            $comboProduct = ComboProduct::find($id);

            // If product not found, return 404 response
            if (!$comboProduct) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            if ($type == 'add') {
                // Add the quantity to the stock
                $comboProduct->stock += $quantity;

                // Update availability if stock is greater than 0
                if ($comboProduct->stock > 0) {
                    $comboProduct->availability = 1;
                }
            } elseif ($type == 'subtract') {
                // Subtract the quantity from the stock
                $comboProduct->stock -= $quantity;

                // Ensure stock doesn't go below 0
                if ($comboProduct->stock < 0) {
                    return response()->json(['message' => 'Stock cannot go negative'], 400);
                }

                // Update availability if stock is 0
                if ($comboProduct->stock == 0) {
                    $comboProduct->availability = 0;
                }
            }

            // Save the updated combo product
            $saved = $comboProduct->save();

            return $saved;
        }

        return response()->json(['message' => 'Invalid operation type'], 400);
    }
    public function getComboProductAttributeIdsByValue($values, $names)
    {
        if (is_string($names)) {
            $names = explode(',', str_replace('-', ' ', $names));
            $names = array_map('trim', $names);
        }

        if (empty($values) || empty($names)) {
            return [];
        }

        return ComboProductAttributeValue::whereIn('value', $values)
            ->whereHas('attribute', function ($query) use ($names) {
                $query->whereIn('name', $names);
            })
            ->pluck('id')
            ->toArray();
    }

    public function fetchComboRating($productId = null, $userId = null, $limit = null, $offset = null, $sort = null, $order = null, $ratingId = null, $hasImages = null)
    {

        $query = ComboProductRating::with('user');

        if (!empty($productId)) {
            $query->where('product_id', $productId);
        }

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        if (!empty($ratingId)) {
            $query->where('id', $ratingId);
        }

        if (!empty($rating)) {
            $rating = floatval($rating);
            $query->whereBetween('rating', [$rating, $rating + 0.3]);
        }

        if (!empty($sort) && !empty($order)) {
            $query->orderBy($sort, $order);
        }

        if (!empty($limit) && !empty($offset)) {
            $query->skip($offset)->take($limit);
        }

        $productRatings = $query->get()->map(function ($rating) {
            $images = json_decode($rating->images, true) ?? [];
            $formattedImages = [];

            foreach ($images as $image) {
                $formattedImages[] = app(MediaService::class)->getImageUrl($image);
            }

            return [
                'id' => $rating->id,
                'product_id' => $rating->product_id,
                'user_id' => $rating->user_id,
                'rating' => $rating->rating,
                'comment' => $rating->comment ?? '',
                'title' => $rating->title ?? '',
                'images' => $formattedImages,
                'user_name' => $rating->user->username ?? '',
                'user_profile' => !empty($rating->user->image) && File::exists(public_path(config('constants.USER_IMG_PATH') . $rating->user->image))
                    ? app(MediaService::class)->getMediaImageUrl($rating->user->image, 'USER_IMG_PATH')
                    : app(MediaService::class)->getImageUrl('no-user-img.jpeg', '', '', 'image', 'NO_USER_IMAGE'),
                'created_at' => $rating->created_at,
                'updated_at' => $rating->updated_at,
            ];
        });

        // Stats
        $totalRating = ComboProductRating::when($productId, fn($q) => $q->where('product_id', $productId))->count();

        $totalImages = ComboProductRating::when($productId, fn($q) => $q->where('product_id', $productId))
            ->whereNotNull('images')
            ->get()
            ->reduce(function ($carry, $item) {
                $images = json_decode($item->images, true);
                $count = is_array($images) ? count($images) : 0;
                return $carry + $count;
            }, 0);

        $totalReviewsWithImages = ComboProductRating::when($productId, fn($q) => $q->where('product_id', $productId))
            ->whereNotNull('images')
            ->count();

        $totalReviewsData = ComboProductRating::when($productId, fn($q) => $q->where('product_id', $productId))->get();

        $ratings = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        foreach ($totalReviewsData as $r) {
            $rVal = $r->rating;
            if ($rVal >= 4.5)
                $ratings['5']++;
            elseif ($rVal >= 4)
                $ratings['4']++;
            elseif (ceil($rVal) == 3)
                $ratings['3']++;
            elseif (ceil($rVal) == 2)
                $ratings['2']++;
            elseif (ceil($rVal) == 1)
                $ratings['1']++;
        }

        $no_of_reviews = 0;
        $no_of_reviews = ComboProductRating::where('product_id', $productId)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->count();
        return [
            'total_images' => $totalImages,
            'total_reviews_with_images' => $totalReviewsWithImages,
            'no_of_rating' => $totalRating,
            'total_reviews' => count($totalReviewsData),
            'star_1' => (string) $ratings['1'],
            'star_2' => (string) $ratings['2'],
            'star_3' => (string) $ratings['3'],
            'star_4' => (string) $ratings['4'],
            'star_5' => (string) $ratings['5'],
            'product_rating' => $productRatings,
            'no_of_reviews' => $no_of_reviews,
        ];
    }
    public function getComboProductFaqs($id = null, $product_id = null, $user_id = '', $search = '', $limit = '', $offset = '', $sort = '', $order = '', $is_seller = false, $seller_id = '')
    {

        $limit = $limit ?: 10;
        $offset = $offset ?: 0;
        $sort = $sort ?: 'id';
        $order = $order ?: 'desc';

        $query = ComboProductFaq::with(['user', 'answeredBy']);

        // Filters
        if (!empty($id)) {
            $query->where('id', $id);
        }

        if (!empty($product_id)) {
            $query->where('product_id', $product_id);
        }

        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
        }

        if (!empty($seller_id)) {
            $query->where('seller_id', $seller_id);
        }

        // Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $data = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($faq) {
                // dd($faq);
                return [
                    'id' => $faq->id,
                    'product_id' => $faq->product_id ?? '',
                    'user_id' => $faq->user_id ?? '',
                    'question' => $faq->question ?? '',
                    'answer' => $faq->answer ?? '',
                    'is_approved' => $faq->is_approved ?? '',
                    'answered_by' => $faq->answeredBy->username ?? '',
                    'user_username' => $faq->user->username ?? '',
                    'seller_id' => $faq->seller_id ?? '',
                    'created_by' => $faq->created_by ?? '',
                    'updated_by' => $faq->updated_by ?? '',
                    'status' => $faq->status ?? '',
                    'created_at' => $faq->created_at ?? '',
                    'updated_at' => $faq->updated_at ?? '',
                ];
            });

        return [
            'total' => $total,
            'data' => $data,
        ];
    }
}