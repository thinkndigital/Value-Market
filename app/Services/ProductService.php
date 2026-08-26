<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Zipcode;
use App\Models\ProductRating;
use App\Models\Zone;
use App\Models\Brand;
use App\Models\Attribute_values;
use App\Models\ProductFaq;
use App\Models\Product_variants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\TranslationService;
use App\Services\DeliveryService;
use App\Services\MediaService;
use App\Services\CurrencyService;
use App\Services\SettingService;

class ProductService
{
    public function fetchProduct($user_id = NULL, $filter = NULL, $id = NULL, $category_id = NULL, $limit = 20, $offset = NULL, $sort = 'products.id', $order = 'DESC', $return_count = NULL, $is_deliverable = NULL, $seller_id = NULL, $brand_id = NULL, $store_id = NULL, $is_detailed_data = 0, $type = '', $from_seller = 0, $language_code = "")
    {
        $attribute_values_ids = [];
        $productQuery = Product::with([
            'category',
            'brandRelation',
            'sellerData',
            'sellerStoreData',
            'productVariants',
            'productAttributes',
            'orderItems',
        ])
            ->select('products.*')->distinct()
            ->addSelect([
                'total_sale' => OrderItems::select(DB::raw('SUM(quantity)'))
                    ->whereColumn('product_variant_id', 'product_variants.id')
                    ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id')
            ])
            ->whereHas('productVariants', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('sellerData', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('category', function ($q) {
                $q->whereIn('status', [0, 1]);
            })
            /** ⭐ Filter by Rating */
            ->when(!empty($filter['rating']), function ($query) use ($filter) {
                $query->where('rating', '>=', $filter['rating']);
            })

            /** ⭐ Filter by Price (either special_price or price) */
            ->when(!empty($filter['minimum_price']) || !empty($filter['maximum_price']), function ($query) use ($filter) {
                $min = $filter['minimum_price'] ?? 0;
                $max = $filter['maximum_price'] ?? PHP_INT_MAX;

                $query->whereHas('productVariants', function ($q) use ($min, $max) {
                    $q->where(function ($q) use ($min, $max) {
                        $q->where('special_price', '>', 0)
                            ->whereBetween('special_price', [$min, $max]);
                    })->orWhere(function ($q) use ($min, $max) {
                        $q->where('special_price', '=', 0)
                            ->whereBetween('price', [$min, $max]);
                    });
                });
            })

            /** ⭐ Full-text Search (tags and name) */
            ->when(!empty($filter['search']), function ($query) use ($filter) {
                $tags = preg_split('/\s+/', $filter['search'], -1, PREG_SPLIT_NO_EMPTY);
                $query->where(function ($q) use ($tags, $filter) {
                    foreach ($tags as $tag) {
                        $q->orWhere('tags', 'like', '%' . $tag . '%');
                    }
                    $q->orWhere('name', 'like', '%' . $filter['search'] . '%');
                });
            })

            /** ⭐ Filter by tags (comma or pipe separated) */
            ->when(!empty($filter['tags']), function ($query) use ($filter) {
                $tags = preg_split('/[,\|]/', $filter['tags'], -1, PREG_SPLIT_NO_EMPTY);
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhere('tags', 'like', '%' . trim($tag) . '%');
                    }
                });
            })

            /** ⭐ Filter by brand */
            ->when(!empty($filter['brand']), function ($query) use ($filter) {
                $query->where('brand', $filter['brand']);
            })

            /** ⭐ Filter by slug */
            ->when(!empty($filter['slug']), function ($query) use ($filter) {
                $query->where('slug', $filter['slug']);
            })

            /** ⭐ Filter by seller ID */
            ->when(!empty($seller_id), function ($query) use ($seller_id) {
                $query->where('seller_id', $seller_id);
            })

            /** ⭐ Filter by attribute value IDs */
            ->when(!empty($filter['attribute_value_ids']), function ($query) use ($filter) {
                foreach ($filter['attribute_value_ids'] as $valueId) {
                    $query->whereHas('productAttributes', function ($q) use ($valueId) {
                        $q->whereRaw('FIND_IN_SET(?, attribute_value_ids)', [$valueId]);
                    });
                }
            })

            /** ⭐ Filter by category */
            ->when(!empty($category_id), function ($query) use ($category_id) {
                $query->whereIn('category_id', (array) $category_id);
            })

            /** ⭐ Filter by brand_id array (bulk) */
            ->when(!empty($brand_id), function ($query) use ($brand_id) {
                $query->whereIn('brand', (array) $brand_id);
            })
            /** ⭐ Filter by Attribute Values */
            ->when(!empty($filter['attribute_value_ids']), function ($query) use ($filter) {
                $query->whereHas('productAttributes', function ($q) use ($filter) {
                    foreach ($filter['attribute_value_ids'] as $attrId) {
                        $q->whereRaw('FIND_IN_SET(?, attribute_value_ids) > 0', [$attrId]);
                    }
                });
            })

            /** ⭐ Filter by Product Type */
            ->when(!empty($type), function ($query) use ($type) {
                if (in_array($type, ['simple_product', 'variable_product', 'digital_product'])) {
                    $query->where('type', $type);
                } elseif ($type == 'physical_product') {
                    $query->whereIn('type', ['simple_product', 'variable_product']);
                }
            })

            /** ⭐ Filter Only Physical Products */
            ->when(!empty($filter['show_only_physical_product']) && $filter['show_only_physical_product'] == 1, function ($query) {
                $query->where('type', '!=', 'digital_product');
            })

            /** ⭐ Filter by Active Product Stock */
            ->when(
                !empty($filter['show_only_stock_product']) && $filter['show_only_stock_product'] == 1,
                function ($query) {
                    $query->where(function ($q) {
                        // Check stock in the main products table
                        $q->whereNotNull('products.stock')
                            ->orWhere('products.stock', '>', 0)

                            // Or check stock in related product variants
                            ->orWhereHas('productVariants', function ($q2) {
                            $q2->whereNotNull('product_variants.stock')
                                ->where('product_variants.stock', '>', 0);
                        });
                    });
                }
            )

            /** ⭐ Filter only Active Products + Variants + Seller */
            ->unless(
                isset($filter['show_only_active_products']) && $filter['show_only_active_products'] == 0,
                function ($query) use ($from_seller) {
                    $query->whereHas('productVariants', fn($q) => $q->where('product_variants.status', 1))
                        ->whereHas('sellerData', fn($q) => $q->where('status', 1));

                    if (isset($from_seller) && $from_seller == 0) {
                        $query->where('products.status', 1);
                    }
                }
            )
            /** ⭐ Sort by most_popular_products */
            ->when(isset($sort) && $sort == 'most_popular_products', function ($query) {
                $query->orderBy('products.rating', 'desc');
            })
            /** ⭐ Sort by price */
            ->addSelect([
                'calculated_price' => DB::table('product_variants')
                    ->select(DB::raw("
                        IF(special_price > 0,
                            IF(products.is_prices_inclusive_tax = 1,
                                special_price,
                                special_price + ((special_price * products.tax) / 100)
                            ),
                            IF(products.is_prices_inclusive_tax = 1,
                                price,
                                price + ((price * products.tax) / 100)
                            )
                        )
                    "))
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->limit(1)
            ])
            ->when($sort == 'pv.price', function ($query) use ($order) {
                $query->orderBy('calculated_price', $order);
            })
            /** ⭐ Filter by most selling products */
            ->when(
                isset($filter['product_type']) && strtolower($filter['product_type']) == 'most_selling_products',
                function ($query) use (&$sort, &$order) {
                    $sort = 'total_sale';
                    $order = 'desc';
                }
            )
            /** ⭐ Filter by min_price and max_price */
            ->leftJoin('product_variants', function ($join) {
                $join->on('product_variants.product_id', '=', 'products.id')
                    ->where('product_variants.status', 1);
            })
            ->when(
                isset($filter['min_price'], $filter['max_price']) &&
                $filter['min_price'] > 0 &&
                $filter['max_price'] > 0,
                function ($query) use ($filter) {
                    $min_price = $filter['min_price'];
                    $max_price = $filter['max_price'];

                    $query->whereRaw("
                        (
                            CASE
                                WHEN product_variants.special_price > 0 THEN
                                    product_variants.special_price * (1 + (
                                        IFNULL((
                                            SELECT MAX(taxes.percentage)
                                            FROM taxes
                                            WHERE FIND_IN_SET(taxes.id, products.tax)
                                        ), 0) / 100
                                    ))
                                ELSE
                                    product_variants.price * (1 + (
                                        IFNULL((
                                            SELECT MAX(taxes.percentage)
                                            FROM taxes
                                            WHERE FIND_IN_SET(taxes.id, products.tax)
                                        ), 0) / 100
                                    ))
                            END
                        ) BETWEEN ? AND ?
                    ", [$min_price, $max_price]);
                }
            )
            /** ⭐ Filter by seller id */
            ->when(!empty($seller_id), function ($query) use ($seller_id) {
                $query->where('products.seller_id', $seller_id);
            })
            /** ⭐ Filter by store id */
            ->when(!empty($store_id), function ($query) use ($store_id) {
                $query->where('products.store_id', $store_id);
            })
            /** ⭐ Filter by product on sale */
            ->when(!empty($filter['product_type']) && strtolower($filter['product_type']) == 'products_on_sale', function ($query) {
                $query->whereHas('productVariants', function ($q) {
                    $q->where('special_price', '>', 0);
                });
            })
            /** ⭐ Filter by top rated product */
            ->when(!empty($filter['product_type']) && strtolower($filter['product_type']) == 'top_rated_products', function ($query) {
                $query->where('no_of_ratings', '>', 0)
                    ->orderBy('rating', 'desc')
                    ->orderBy('no_of_ratings', 'desc');
            })
            /** ⭐ Filter by top rated product including all product */
            ->when(!empty($filter['product_type']) && strtolower($filter['product_type']) == 'top_rated_product_including_all_products', function ($query) {
                $query->orderBy('rating', 'desc')
                    ->orderBy('no_of_ratings', 'desc');
            })
            /** ⭐ Filter by new added products */
            ->when(!empty($filter['product_type']) && strtolower($filter['product_type']) == 'new_added_products', function ($query) {
                $query->orderBy('id', 'desc');
            })
            /** ⭐ Filter by product variant ids */
            ->when(!empty($filter['product_variant_ids']) && is_array($filter['product_variant_ids']), function ($query) use ($filter) {
                $query->whereHas('productVariants', function ($q) use ($filter) {
                    $q->whereIn('id', $filter['product_variant_ids']);
                });
            })
            /** ⭐ Filter by id */
            ->when(!empty($id), function ($query) use ($id) {
                if (is_array($id)) {
                    $query->whereIn('products.id', $id);
                } else {
                    $query->where('products.id', $id);
                }
            })
            /** ⭐ Filter by discount */
            ->when(!empty($filter['discount']), function ($query) use ($filter) {
                $discount = $filter['discount'];

                $query->whereHas('productVariants', function ($q) use ($discount) {
                    $q->where('special_price', '>', 0)
                        ->whereRaw('((price - special_price) / price) * 100 >= ?', [$discount]);
                });
            })
            /** ⭐ sort using price */
            ->when(!empty($sort) && $sort !== 'price', function ($query) use ($sort, $order) {
                $query->orderBy($sort, $order);
            }, function ($query) {
                $query->orderBy('products.id', 'DESC');
            })
            /** ⭐ sort using discount */
            ->when($sort == 'discount', function ($query) {
                $query->orderByRaw('special_price > 0 DESC');
            })

            ->when(isset($from_seller) && $from_seller == 1, function ($query) {
                $query->whereIn('products.status', [1, 2]);
            });
        $totalCount = (clone $productQuery)
            ->get()
            ->unique('id')
            ->count();
        $productData = $productQuery->skip($offset)->take($limit)->get();

        // dd($productQuery->toSql(),$productQuery->getBindings());

        $category_ids = collect($productData)->pluck('category_id')->unique()->values()->all();
        $brand_ids = collect($productData)->pluck('brand')->unique()->values()->all();

        $weekly_sales = OrderItems::with(['productVariant.product'])
            ->where('created_at', '>=', now()->subDays(7))
            ->where('order_type', 'regular_order')
            ->get()
            ->groupBy(function ($item) {
                // dd($item);
                return optional(optional($item->productVariant)->product)->id;
            })
            ->map(function ($items, $productId) {
                return [
                    'product_id' => $productId,
                    'weekly_sale' => $items->sum('quantity'),
                ];
            })
            ->filter(fn($data) => $data['product_id'] !== null)
            ->pluck('weekly_sale', 'product_id')
            ->toArray();

        $max_weekly_sale = !empty($weekly_sales) ? max($weekly_sales) : 0;
        $min_price = $this->getPrice('min', $store_id);
        $max_price = $this->getPrice('max', $store_id);
        $refectorProducts = [];
        if (!$productData->isEmpty()) {
            // dd($product);
            foreach ($productData as $product) {
                $productId = $product['id'];
                $product->translated_name = json_decode($product['name']);
                $product->translated_short_description = json_decode($product['short_description']);
                if (($is_detailed_data != null && $is_detailed_data == 1)) {
                    $product_faq = $this->getProductFaqs('', $product['id']);
                    foreach ($product_faq['data'] as $faq) {
                        $faq['answer'] = $faq['answer'] ?? "";
                    }

                    $product['product_faq'] = isset($product_faq) && !empty($product_faq) ? $product_faq : [];

                    $rating = $this->fetchRating($product['id'], '', 8, 0, '', 'desc', '', 1);
                    $product['product_rating_data'] = $rating ?? [];

                    $product['price_range'] = $this->getPriceRangeOfProduct($productId);
                }

                $product['attributes'] = $this->getAttributeValuesByPid($productId);
                $variants = $this->getVariantsValuesByPid($product['id']);
                // dd($variants);
                $total_stock = 0;

                foreach ($variants as $variant) {

                    $stock = (isset($variant->stock) && !empty($variant->stock)) ? $variant->stock : 0;
                    $total_stock += $stock;
                    $product['total_stock'] = isset($total_stock) && !empty($total_stock) ? (string) $total_stock : '';
                }

                $product['variants'] = $variants;

                $product['min_max_price'] = $this->getMinMaxPriceOfProduct($productId);
                $product['tax_id'] = intval($product['tax']) > 0 ? $product['tax'] : '0';

                $taxes = [];
                $tax_ids = explode(",", $product['tax']);

                $taxes_result = Tax::whereIn('id', $tax_ids)->get()->toArray();
                $taxes = array_column($taxes_result, 'title');

                $translatedTaxes = [];

                foreach ($taxes as $tax) {
                    $translatedTaxes[] = app(TranslationService::class)->getDynamicTranslation(Tax::class, 'title', $product['tax'], $language_code);
                }

                $product['tax_names'] = implode(",", $translatedTaxes);

                $tax_percentages = [];
                $tax_ids = explode(",", $product['tax']);
                $tax_percentages = array_column($taxes_result, 'percentage');
                $product['tax_percentage'] = implode(",", $tax_percentages);
                // dd($product['productAttributes']);
                $attributeIds = $product->productAttributes
                    ->pluck('attribute_value_ids')
                    ->filter()->implode(',');

                $product['attribute_value_ids'] = $attributeIds;
                $product['name'] = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product['id'], $language_code);

                if (isset($product['created_at']) && strtotime($product['created_at']) >= strtotime('-7 days')) {
                    $product['new_arrival'] = true;
                } else {
                    $product['new_arrival'] = false;
                }

                // best seller tag based on most selling product (weekly)

                $weeklySale = $weekly_sales[$productId] ?? 0;
                $product['best_seller'] = ($max_weekly_sale > 0 && $weeklySale >= ($max_weekly_sale * 0.8));

                // end best seller tag based on most selling product (weekly)

                $product['store_name'] = outputEscaping($product['sellerStoreData']['store_name'] ?? '');
                $product['product_type'] = outputEscaping($product['type'] ?? '');
                $product['attr_value_ids'] = $attributeIds;
                $product['seller_rating'] = (number_format($product['sellerStoreData']['rating'] ?? 0, 1));
                $product['store_description'] = outputEscaping($product['sellerStoreData']['store_description'] ?? '');
                $product['seller_profile'] = outputEscaping(asset($product['sellerStoreData']['logo'] ?? ''));
                $product['seller_no_of_ratings'] = $product['sellerStoreData']['no_of_ratings'] ?? 0;
                $product['seller_name'] = outputEscaping($product['sellerStoreData']['user']['username'] ?? '');
                $product['short_description'] = app(TranslationService::class)->getDynamicTranslation(Product::class, 'short_description', $product['id'], $language_code);
                $product['description'] = outputEscaping($product['description'] ?? '');

                $product['extra_description'] = outputEscaping($product['extra_description']);
                $product['pickup_location'] = $product['pickup_location'] ?? '';
                $product['brand_slug'] = $product['brandRelation']['slug'] ?? '';
                $product['category_slug'] = $product['category']['slug'] ?? '';
                $product['pickup_location'] = $product['pickup_location'] ?? '';
                $product['download_link'] = !empty($product['download_link']) ? app(MediaService::class)->getMediaImageUrl($product['download_link']) : '';
                $product['relative_path'] = !empty($product['image']) ? ($product['image']) : '';
                $product['video_relative_path'] = !empty($product['video']) ? ($product['video']) : '';
                $product['seller_slug'] = outputEscaping($product['sellerStoreData']['slug'] ?? '');

                if (!empty($filter['discount'] ?? '')) {
                    $product['cal_discount_percentage'] = outputEscaping(number_format($product['cal_discount_percentage'] ?? 0, 2));
                }
                $productCustomFieldsData = Product::with('customFieldValues.customField')->find($productId);
                $customFieldsData = $productCustomFieldsData->customFieldValues->map(function ($fieldValue) {
                    $field = $fieldValue->customField;
                    if (!$field) {
                        // Log or skip this invalid relation
                        return null; // or log warning, or throw an exception
                    }
                    $value = $fieldValue->value;

                    // Check and decode JSON string (if it's an encoded array)
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $value = $decoded;
                        }
                    }
                    // dd($field);
                    // Handle file type
                    if ($field->type === 'file' && $value && is_string($value)) {
                        $isPublicDisk = true;
                        $imagePath = $isPublicDisk
                            ? asset(config('constants.CUSTOM_FIELD_FILE_PATH') . $value)
                            : $value;
                        // dd(config('constants.CUSTOM_FIELD_FILE_PATH'));
                        $value = app(MediaService::class)->getMediaImageUrl($imagePath);
                    }

                    return [
                        'custom_field_id' => $field->id,
                        'name' => $field->name,
                        'type' => $field->type,
                        'required' => $field->required,
                        'options' => is_array($field->options) ? $field->options : json_decode($field->options, true),
                        'value' => $value,
                    ];
                });
                $product['cancelable_till'] = $product['cancelable_till'] ?? '';
                $product['custom_fields'] = $customFieldsData;
                $product['indicator'] = (string) ($product['indicator'] ?? '0');
                $product['deliverable_zones_ids'] = $product['deliverable_zones_ids'] ?? '';
                $product['rating'] = outputEscaping(number_format($product['rating'] ?? 0, 2));
                $product['availability'] = isset($product['availability']) && $product['availability'] !== '' ? (int) $product['availability'] : '';
                $product['stock'] = isset($product['stock']) && $product['stock'] !== '' ? (string) $product['stock'] : '';
                $product['sku'] = $product['sku'] ?? '';
                if ($product['deliverable_type'] != 'NONE' && $product['deliverable_type'] != 'ALL') {
                    $zones = [];
                    $zone_ids = explode(",", $product['deliverable_zones']);
                    if (!empty($zone_ids) && $product['deliverable_zones'] != "") {
                        $zones = Zone::whereIn('id', $zone_ids)->get();
                    }
                    $translatedZones = [];
                    foreach ($zones as $zone) {
                        $translatedZones[] = app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code);
                    }
                    $product['deliverable_zones'] = implode(",", $translatedZones);
                } else {
                    $product['deliverable_zones'] = '';
                }

                $product['category_name'] = (isset($product['category_id']) && !empty(($product['category_id']))) ? app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $product['category_id'], $language_code) : '';
                $product['brand_name'] = (isset($product['brand']) && !empty($product['brand'])) ? app(TranslationService::class)->getDynamicTranslation(Brand::class, 'name', $product['brand'], $language_code) : '';
                /* check product delivrable or not */

                if ($is_deliverable != NULL) {
                    $zipcode = fetchDetails(Zipcode::class, ['id' => $is_deliverable], '*');

                    if (!empty($zipcode)) {
                        $product['is_deliverable'] = app(DeliveryService::class)->isProductDelivarable($type = 'zipcode', $zipcode[0]->id, $product['id']);
                    } else {
                        $product['is_deliverable'] = false;
                    }
                } else {
                    $product['is_deliverable'] = false;
                }
                // if ($product['deliverable_type'] == 1) {
                //     $product['deliverable_type'] = true;
                // }
                $product['tags'] = (!empty($product['tags'])) ? explode(",", $product['tags']) : [];

                $product['video'] = (isset($product['video_type']) && (!empty($product['video_type']) || $product['video_type'] != NULL)) ? (($product['video_type'] == 'youtube' || $product['video_type'] == 'vimeo') ? $product['video_type'] : asset('storage/' . $product['video_type'])) : "";
                $product['minimum_order_quantity'] = isset($product['minimum_order_quantity']) && (!empty($product['minimum_order_quantity'])) ? $product['minimum_order_quantity'] : 1;
                $product['quantity_step_size'] = isset($product['quantity_step_size']) && (!empty($product['quantity_step_size'])) ? $product['quantity_step_size'] : 1;



                if (!empty($product['variants'])) {
                    $count_stock = [];
                    $is_purchased_count = [];

                    // Convert variants to an array or use a reference loop
                    $variants = [];

                    foreach ($product['variants'] as $variant) {
                        // Clone the variant as array (or object if preferred)
                        $variant = is_array($variant) ? $variant : $variant->toArray();

                        $variant['product_name'] = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $variant['product_id'], $language_code);
                        $variant['attribute_set'] = $variant['attribute_set'] ?? '';
                        $variant['stock_type'] = isset($product['stock_type']) ? (string) $product['stock_type'] : '';
                        $variant['sku'] = $variant['sku'] ?? '';
                        $variant['variant_ids'] = $variant['variant_ids'] ?? '';
                        $variant['attr_name'] = $variant['attr_name'] ?? '';
                        $variant['variant_values'] = $variant['variant_values'] ?? '';
                        $variant['attribute_value_ids'] = $variant['attribute_value_ids'] ?? '';
                        $variant_other_images = $variant_other_images_sm = $variant_other_images_md = $variant['images'];

                        if (!empty($variant_other_images[0])) {
                            $variant['variant_relative_path'] = !empty($variant['images']) ? (is_string($variant['images']) ? json_decode($variant['images']) : $variant['images']) : [];
                            $variant['images_md'] = array_map(fn($img) => app(MediaService::class)->getImageUrl($img, 'thumb', 'md'), $variant_other_images_md ?? []);
                            $variant['images_sm'] = array_map(fn($img) => app(MediaService::class)->getImageUrl($img, 'thumb', 'sm'), $variant_other_images_sm ?? []);
                            $mediaService = app(MediaService::class);
                            $variant['images'] = array_map(function ($image) use ($mediaService) {
                                return $mediaService->getMediaImageUrl($image);
                            }, $variant_other_images ?? []);
                        } else {
                            $variant['images'] = [];
                            $variant['images_md'] = [];
                            $variant['images_sm'] = [];
                            $variant['variant_relative_path'] = [];
                        }

                        $variant['product_image'] = isset($variant['product']['image']) && !empty($variant['product']['image']) ? app(MediaService::class)->getMediaImageUrl($variant['product']['image']) : '';
                        $variant['swatche_type'] = $variant['swatche_type'] ?? "0";
                        $variant['swatche_value'] = $variant['swatche_type'] == 2
                            ? (!empty($variant['swatche_value']) ? app(MediaService::class)->getImageUrl($variant['swatche_value']) : "")
                            : ($variant['swatche_value'] ?? "0");

                        if (($product['stock_type'] == 0 || $product['stock_type'] == null)) {
                            $variant['availability'] = intval($product['availability'] ?? 0);
                        } else {
                            $variant['availability'] = $variant['availability'] ?? 0;
                            $count_stock[] = $variant['availability'];
                        }

                        $variant['stock'] = ($product['stock_type'] == 0)
                            ? (string) $this->getStock($product['id'], 'product')
                            : (string) $this->getStock($variant['id'], 'variant');

                        $percentage = (isset($product['tax_percentage']) && intval($product['tax_percentage']) > 0)
                            ? $product['tax_percentage'] : '';

                        $price = strval($variant['price']);
                        $special_price = strval($variant['special_price']);

                        if (isset($product['is_prices_inclusive_tax']) && $product['is_prices_inclusive_tax'] == 0) {
                            if (isset($from_seller) && $from_seller == 1) {
                                $variant['price'] = $price;
                                $variant['price_with_tax'] = strval(calculatePriceWithTax($percentage, $price));
                                $variant['special_price'] = $special_price;
                                $variant['special_price_with_tax'] = strval(calculatePriceWithTax($percentage, $special_price));
                            } else {
                                $variant['price'] = strval(calculatePriceWithTax($percentage, $price));
                                $variant['special_price'] = strval(calculatePriceWithTax($percentage, $special_price));
                            }
                        } else {
                            $variant['price'] = $price;
                            $variant['special_price'] = $special_price;
                            if (isset($from_seller) && $from_seller == 1) {
                                $variant['price_with_tax'] = $price;
                                $variant['special_price_with_tax'] = $special_price;
                            }
                        }

                        $variant['currency_price_data'] = app(CurrencyService::class)->getPriceCurrency($variant['price']);
                        $variant['currency_special_price_data'] = app(CurrencyService::class)->getPriceCurrency($variant['special_price']);

                        if (isset($user_id) && $user_id != null && $is_detailed_data === 1) {
                            $userCartData = Cart::where([
                                'product_variant_id' => $variant['id'],
                                'user_id' => $user_id,
                                'is_saved_for_later' => 0
                            ])->select('qty as cart_count')->first();

                            $variant['cart_count'] = $userCartData->cart_count ?? "0";

                            $is_purchased = OrderItems::where([
                                'product_variant_id' => $variant['id'],
                                'user_id' => $user_id
                            ])->orderByDesc('id')->first();

                            if (!empty($is_purchased) && strtolower($is_purchased->active_status) == 'delivered') {
                                $variant['is_purchased'] = 1;
                                $is_purchased_count[] = 1;
                            } else {
                                $variant['is_purchased'] = 0;
                                $is_purchased_count[] = 0;
                            }
                        } else {
                            $variant['cart_count'] = "0";
                        }

                        unset($variant['product']);
                        $variants[] = $variant;
                    }
                    $product['variants'] = $variants;
                    $product['is_purchased'] = isset($is_purchased_count) && array_sum($is_purchased_count) > 0;
                }

                if (isset($product['stock_type']) && !empty($product['stock_type'])) {
                    //Case 2 & 3: Product level (variable product) || Variant level (variable product)
                    if ($product['stock_type'] == 1 || $product['stock_type'] == 2) {
                        // Ensure $count_stock is an array and not null
                        if (isset($count_stock) && is_array($count_stock)) {
                            // Filter out non-integer and non-string values from $count_stock array
                            $count_stock_filtered = array_filter($count_stock, function ($value) {
                                return is_int($value) || is_string($value);
                            });

                            // Count occurrences of each value
                            $counts = array_count_values($count_stock_filtered);

                            // Sum the counts
                        }
                    }
                }
                if (isset($user_id) && $user_id != null) {
                    $fav = Favorite::where(['product_id' => $product['id'], 'user_id' => $user_id, 'product_type' => 'regular'])->count();

                    $product['is_favorite'] = $fav;
                } else {
                    $product['is_favorite'] = 0;
                }
                $product['image'] = app(MediaService::class)->getMediaImageUrl(ltrim($product['image'], '/'));
                $other_images = json_decode($product['other_images'], 1);


                if (!empty($other_images)) {
                    $k = 0;
                    foreach ($other_images as $row) {
                        $other_images[$k] = app(MediaService::class)->getMediaImageUrl($row);
                        $k++;
                    }
                    $other_images = (array) $other_images;
                    $other_images = array_values($other_images);
                    $product['other_images'] = $other_images;
                } else {
                    $product['other_images'] = array();
                }


                $tags_to_strip = array("table", "<th>", "<td>");
                $replace_with = array("", "h3", "p");
                $n = 0;

                foreach ($tags_to_strip as $tag) {
                    $product['description'] = !empty($product['description']) ? outputEscaping(str_replace('\r\n', '&#13;&#10;', (string) $product['description'])) : "";
                    $product['extra_description'] = !empty($product['extra_description']) && $product['extra_description'] != null ? outputEscaping(str_replace('\r\n', '&#13;&#10;', (string) $product['extra_description'])) : "";
                    $n++;
                }


                $variant_attributes = [];
                $attributes_array = explode(
                    ',',
                    isset($product['variants']) && !empty($product['variants']) && isset($product['variants'][0]['attr_name'])
                    ? $product['variants'][0]['attr_name']
                    : ""
                );
                // dd($attributes_array);
                // dd($product['variants'][0]['attr_name']);
                // dd($product['attr_value_ids']);
                foreach ($attributes_array as $attribute) {
                    $attribute = trim($attribute);
                    $key = array_search($attribute, array_column($product['attributes'], 'name'), false);

                    if (($key == 0 || !empty($key)) && isset($product[0]->attributes[$key])) {

                        $variant_attributes[$key]['ids'] = $product[0]->attributes[$key]['ids'];
                        $variant_attributes[$key]['value'] = $product[0]->attributes[$key]['value'];
                        $variant_attributes[$key]['swatche_type'] = isset($product[0]->attributes[$key]['swatche_type']) ? $product[0]->attributes[$key]['swatche'] : '';
                        $variant_attributes[$key]['swatche_value'] = isset($product[0]->attributes[$key]['swatche_value']) ? $product[0]->attributes[$key]['swatche_value'] : '';
                        $variant_attributes[$key]['attr_name'] = $attribute;
                    }
                }


                if (!empty($attributeIds)) {
                    $attribute_values_ids[] = $attributeIds;
                }
                // dd($product);
                $product = $product->toArray();
                unset(
                    $product['category'],
                    $product['brand_relation'],
                    $product['seller_data'],
                    $product['seller_store_data'],
                    $product['product_variants'],
                    $product['product_attributes'],
                    $product['order_items']
                );

                $product['variant_attributes'] = $variant_attributes;

                $refectorProducts[] = $product;
            }


            if (isset($total_data[0]->cal_discount_percentage)) {
                $dicounted_total = array_values(array_filter(explode(',', $total_data[0]->cal_discount_percentage)));
            } else {
                $dicounted_total = 0;
            }

            $response['total'] = (isset($filter) && !empty($filter['discount'])) ? count($dicounted_total) : $totalCount;

            $attribute_values_ids = implode(",", $attribute_values_ids);


            $attr_value_ids = array_filter(array_unique(explode(',', $attribute_values_ids)));
        }
        $response['min_price'] = (isset($min_price)) ? $min_price : "0";
        $response['max_price'] = (isset($max_price)) ? $max_price : "0";
        $response['category_ids'] = (isset($category_ids)) ? $category_ids : "";
        $response['brand_ids'] = (isset($brand_ids)) ? $brand_ids : "";
        $response['product'] = $refectorProducts;
        if (isset($filter) && $filter != null) {
            if (!empty($attr_value_ids)) {
                $response['filters'] = $this->getAttributeValuesById($attr_value_ids);
            } else {
                $response['filters'] = [];
            }
        } else {
            $response['filters'] = [];
        }

        return $response;
    }

    public function getMinMaxPriceOfProduct($product_id = '')
    {
        // Load product with its variants
        $product = Product::with(['productVariants'])->find($product_id);

        if (!$product) {
            return [
                'min_price' => 0,
                'max_price' => 0,
                'special_min_price' => 0,
                'special_max_price' => 0,
                'discount_in_percentage' => 0,
            ];
        }

        $variants = $product->productVariants->where('status', 1);

        if ($variants->isEmpty()) {
            return [
                'min_price' => 0,
                'max_price' => 0,
                'special_min_price' => 0,
                'special_max_price' => 0,
                'discount_in_percentage' => 0,
            ];
        }

        // Handle tax (comma-separated tax IDs)
        $taxIds = array_filter(array_map('intval', explode(',', $product->tax ?? '')));
        $totalTaxPercentage = 0;

        if (!$product->is_prices_inclusive_tax && !empty($taxIds)) {
            $totalTaxPercentage = Tax::whereIn('id', $taxIds)
                ->where('status', 1)
                ->sum('percentage');
        }

        // Prices with tax applied if needed
        $prices = $variants->map(function ($variant) use ($totalTaxPercentage) {
            return floatval($variant->price) + (floatval($variant->price) * $totalTaxPercentage / 100);
        });

        $specialPrices = $variants->map(function ($variant) use ($totalTaxPercentage) {
            if ($variant->special_price > 0) {
                $specialPrice = floatval($variant->special_price);
                return $specialPrice + ($specialPrice * $totalTaxPercentage / 100);
            }

            return 0;
        });

        $min_price = $prices->min();
        $max_price = $prices->max();
        $special_min_price = $specialPrices->min();
        $special_max_price = $specialPrices->max();

        $discount_in_percentage = findDiscountInPercentage($special_min_price, $min_price);

        return compact('min_price', 'max_price', 'special_min_price', 'special_max_price', 'discount_in_percentage');
    }

    public function getAttributeValuesByPid($productId): array
    {
        $product = Product::with('productAttributes')->find($productId);

        if (!$product) {
            return [];
        }

        $results = [];

        foreach ($product->productAttributes as $productAttribute) {
            // manually extract attribute values from CSV
            $ids = explode(',', $productAttribute->attribute_value_ids);
            $values = Attribute_values::with('attribute')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get();

            // Group values by attribute
            foreach ($values->groupBy('attribute.id') as $attributeId => $groupedValues) {
                // dd($groupedValues->first()->attribute->name);
                $firstValue = $groupedValues->first();
                $attributeName = $firstValue && $firstValue->attribute ? $firstValue->attribute->name : '';
                $results[] = [
                    'ids' => $groupedValues->pluck('id')->implode(','),
                    'value' => $groupedValues->pluck('value')->implode(', '),
                    'attr_name' => $attributeName,
                    'attr_id' => $attributeId,
                    'name' => $attributeName,
                ];
            }
        }

        return $results;
    }
    public function getAttributeValuesById($id)
    {
        $attributeValues = Attribute_values::select('attributes.name as attribute_name')
            ->selectRaw('GROUP_CONCAT(attribute_values.value ORDER BY attribute_values.id ASC) AS attribute_values')
            ->selectRaw('GROUP_CONCAT(attribute_values.id ORDER BY attribute_values.id ASC) AS attribute_values_id')
            ->join('attributes', 'attribute_values.attribute_id', '=', 'attributes.id')
            ->whereIn('attribute_values.id', $id)
            ->groupBy('attributes.name')
            ->get()
            ->toArray();

        // Process the attribute values
        if (!empty($attributeValues)) {
            foreach ($attributeValues as &$value) {
                // Convert comma-separated string to array for each attribute_values and attribute_values_id
                $value['attribute_values'] = explode(',', $value['attribute_values']);
                $value['attribute_values_id'] = explode(',', $value['attribute_values_id']);
            }
        }
        return $attributeValues;
    }
    public function getVariantsValuesByPid($id, $status = [1], $language_code = "")
    {
        $variants = Product_variants::with(['product'])
            ->where('product_id', $id)
            ->whereIn('status', $status)
            ->orderBy('id')
            ->get();
        foreach ($variants as $variant) {
            // Handle attribute_value_ids (FIND_IN_SET replacement)
            $attrValueIds = explode(',', $variant->attribute_value_ids);
            $attributeValues = collect((object) []);
            if ($variant->attribute_value_ids != "") {
                $attributeValues = Attribute_values::with('attribute')
                    ->whereIn('id', $attrValueIds)
                    ->orderBy('id')
                    ->get();
            }

            // Build grouped values
            $variant->variant_ids = implode(',', $attributeValues->pluck('id')->toArray());
            $variant->attr_name = $attributeValues->pluck('attribute.name')->implode(' ');
            $variant->variant_values = $attributeValues->pluck('value')->implode(',');
            $variant->swatche_type = $attributeValues->pluck('swatche_type')->implode(',');
            $variant->swatche_value_raw = $attributeValues->pluck('swatche_value')->implode(',');

            // Convert swatche values based on type
            $swatcheValues = explode(',', $variant->swatche_value_raw);
            $swatcheTypes = explode(',', $variant->swatche_type);
            $swatcheFinal = [];

            foreach ($swatcheTypes as $i => $type) {
                $swatcheFinal[] = in_array($type, ['1', '2']) ? ($swatcheValues[$i] ?? '') : '0';
            }

            $variant->swatche_value = implode(',', $swatcheFinal);

            // Decode images and convert to URLs
            $images = [];
            $variantImages = json_decode($variant->images ?? '[]', true);
            // dd($variantImages);
            foreach ($variantImages as $img) {
                $images[] = app(MediaService::class)->getMediaImageUrl($img);
            }
            $variant->images = $images;
            // dd($variant);
            // dd($variant->images);
            // Add availability fallback
            $variant->availability = $variant->availability ?? '';
        }
        // dd($variants->toarray());
        return $variants->toarray();
    }
    public function getPrice($type = 'max', $store_id = null)
    {
        static $result = null;

        if ($result == null) {
            $products = Product::with([
                'productVariants' => function ($query) {
                    $query->where('status', 1);
                },
                'category',
                'sellerData'
            ])
                ->where('status', 1)
                ->whereHas('sellerData', function ($query) {
                    $query->where('status', 1);
                })
                ->where('store_id', $store_id)
                ->where(function ($query) {
                    $query->whereHas('category', function ($q) {
                        $q->whereIn('status', [0, 1]);
                    })->orWhereNull('category_id');
                })
                ->get();

            $result = $products->flatMap(function ($product) {
                // Parse tax IDs
                $taxIds = !empty($product->tax)
                    ? array_map('intval', explode(',', $product->tax))
                    : [];

                // Fetch tax records
                $taxes = Tax::whereIn('id', $taxIds)->where('status', 1)->get();
                $totalTaxPercentage = $taxes->sum('percentage');

                return $product->productVariants->map(function ($variant) use ($product, $totalTaxPercentage) {
                    $basePrice = $variant->special_price > 0 ? $variant->special_price : $variant->price;
                    $price = floatval($basePrice);

                    if (!$product->is_prices_inclusive_tax && $totalTaxPercentage > 0) {
                        $price += $price * ($totalTaxPercentage / 100);
                    }

                    return $price;
                });
            })->toArray();
        }

        return !empty($result)
            ? ($type == 'min' ? min($result) : max($result))
            : 0;
    }

    public function getStock($id, $type)
    {
        if ($type == 'variant') {
            $modal = Product_variants::class;
        } else {
            $modal = Product::class;
        }

        $stock = $modal::where('id', $id)
            ->select('stock')
            ->first();

        return $stock ? $stock->stock : null;
    }

    public function getVariantsValuesById($id)
    {
        $variant = Product_variants::find($id);

        if (!$variant) {
            return [];
        }

        $variant->images = $variant->images ? json_decode($variant->images) : [];

        $attributeValueIds = array_filter(explode(',', $variant->attribute_value_ids));
        $attributeValues = Attribute_values::with('attribute')
            ->whereIn('id', $attributeValueIds)
            ->get();

        $attrNames = $attributeValues->pluck('attribute.name')->filter()->unique()->implode(', ');
        $variantValues = $attributeValues->pluck('value')->filter()->implode(', ');
        $variantIds = $attributeValues->pluck('id')->implode(', ');

        $result = [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'attribute_value_ids' => $variant->attribute_value_ids,
            'price' => $variant->price ?? 0,
            'special_price' => $variant->special_price ?? 0,
            'sku' => $variant->sku ?? '',
            'stock' => $variant->stock ?? 0,
            'weight' => $variant->weight ?? 0,
            'height' => $variant->height ?? 0,
            'breadth' => $variant->breadth ?? 0,
            'length' => $variant->length ?? 0,
            'images' => $variant->images,
            'availability' => $variant->availability ?? '',
            'status' => $variant->status ?? '',
            'created_at' => $variant->created_at ?? '',
            'updated_at' => $variant->updated_at ?? '',
            'variant_ids' => $variantIds,
            'attr_name' => $attrNames,
            'variant_values' => $variantValues,
        ];

        return [$result];
    }

    public function updateStock($product_variant_ids, $qtns, $type = '')
    {

        $ids = implode(',', (array) $product_variant_ids);

        $productVariants = Product_variants::select('p.*', 'product_variants.*', 'p.id as p_id', 'product_variants.id as pv_id', 'p.stock as p_stock', 'product_variants.stock as pv_stock')
            ->whereIn('product_variants.id', is_array($product_variant_ids) ? $product_variant_ids : [$product_variant_ids])
            ->join('products as p', 'product_variants.product_id', '=', 'p.id')
            ->orderByRaw('FIELD(product_variants.id,' . $ids . ')')
            ->get();

        foreach ($productVariants as $i => $res) {

            if ($res->stock_type !== null || $res->stock_type !== "") {

                if ($res->stock_type == 0) {

                    if ($type == 'plus') {

                        if ($res->p_stock !== null) {

                            $stock = ($res->p_stock) + intval(is_array($qtns) ? $qtns[$i] : $qtns);

                            Product::where('id', $res->product_id)->update(['stock' => $stock]);

                            if ($stock > 0) {
                                Product::where('id', $res->product_id)->update(['availability' => '1']);
                            }
                        }
                    } else {

                        if ($res->p_stock !== null && $res->p_stock > 0) {
                            $stock = intval($res->p_stock) - intval(is_array($qtns) ? $qtns[$i] : $qtns);
                            Product::where('id', $res->product_id)->update(['stock' => $stock]);
                            if ($stock == 0) {
                                Product::where('id', $res->product_id)->update(['availability' => '0']);
                            }
                        }
                    }
                }


                if ($res->stock_type == 1) {
                    if ($type == 'plus') {

                        if ($res->pv_stock !== null) {

                            $stock = intval($res->pv_stock) + intval(is_array($qtns) ? $qtns[$i] : $qtns);

                            Product::where('id', $res->p_id)->update(['stock' => $stock]);
                            Product_variants::where('product_id', $res->product_id)->update(['stock' => $stock]);
                            if ($stock > 0) {
                                Product_variants::where('product_id', $res->product_id)->update(['availability' => '1']);
                            }
                        }
                    } else {
                        if ($res->pv_stock !== null && $res->pv_stock > 0) {
                            $stock = intval($res->pv_stock) - intval(is_array($qtns) ? $qtns[$i] : $qtns);
                            Product::where('id', $res->p_id)->update(['stock' => $stock]);
                            Product_variants::where('product_id', $res->product_id)->update(['stock' => $stock]);
                            if ($stock == 0) {
                                Product_variants::where('product_id', $res->product_id)->update(['availability' => '0']);
                            }
                        }
                    }
                }

                // Case 3 : Variant level (variable product)
                if ($res->stock_type == 2) {
                    if ($type == 'plus') {
                        if ($res->pv_stock !== null) {
                            $stock = intval($res->pv_stock) + intval(is_array($qtns) ? $qtns[$i] : $qtns);
                            Product_variants::where('id', $res->id)->update(['stock' => $stock]);
                            if ($stock > 0) {
                                Product_variants::where('id', $res->id)->update(['availability' => '1']);
                            }
                        }
                    } else {
                        if ($res->pv_stock !== null && $res->pv_stock > 0) {
                            $stock = intval($res->pv_stock) - intval(is_array($qtns) ? $qtns[$i] : $qtns);
                            Product_variants::where('id', $res->id)->update(['stock' => $stock]);
                            if ($stock == 0) {
                                Product_variants::where('id', $res->id)->update(['availability' => '0']);
                            }
                        }
                    }
                }
            }
        }
    }

    public function getPriceRangeOfProduct($product_id = '')
    {
        $system_settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);
        $currency = $system_settings['currency'] ?? '';

        $product = Product::with('productVariants')->find($product_id);

        if (!$product) {
            return ['range' => $currency . '0.00'];
        }

        $taxPercentages = $product->getTaxPercentages();
        $totalTaxPercentage = array_sum($taxPercentages);
        $isTaxInclusive = $product->is_prices_inclusive_tax == 1;

        $variants = $product->productVariants;

        if ($variants->count() == 1) {
            $variant = $variants->first();

            $basePrice = $variant->special_price > 0 ? $variant->special_price : $variant->price;
            $taxAmount = $isTaxInclusive ? 0 : ($basePrice * $totalTaxPercentage / 100);
            $finalPrice = $basePrice + $taxAmount;

            return [
                'range' => $currency . "<small style='font-size: 20px;'>" . number_format($finalPrice, 2) . "</small>"
            ];
        }

        // Multiple variants
        $minSpecialPrice = $variants->where('special_price', '>', 0)->min('special_price') ?? $variants->min('price');
        $maxPrice = $variants->max('price');

        if (!$isTaxInclusive && $totalTaxPercentage > 0) {
            $minSpecialPrice += $minSpecialPrice * ($totalTaxPercentage / 100);
            $maxPrice += $maxPrice * ($totalTaxPercentage / 100);
        }

        return [
            'min_special_price' => round($minSpecialPrice, 2),
            'max_price' => round($maxPrice, 2)
        ];
    }
    public function fetchRating($productId = null, $userId = null, $limit = null, $offset = null, $sort = null, $order = null, $ratingId = null, $hasImages = null, $count_empty_comments = false, $rating = '')
    {
        $query = ProductRating::with('user');

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
        $totalRating = ProductRating::when($productId, fn($q) => $q->where('product_id', $productId))->count();

        $totalImages = ProductRating::when($productId, fn($q) => $q->where('product_id', $productId))
            ->whereNotNull('images')
            ->get()
            ->reduce(function ($carry, $item) {
                $images = json_decode($item->images, true);
                $count = is_array($images) ? count($images) : 0;
                return $carry + $count;
            }, 0);

        $totalReviewsWithImages = ProductRating::when($productId, fn($q) => $q->where('product_id', $productId))
            ->whereNotNull('images')
            ->count();

        $totalReviewsData = ProductRating::when($productId, fn($q) => $q->where('product_id', $productId))->get();

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
        if ($count_empty_comments) {
            $no_of_reviews = ProductRating::where('product_id', $productId)
                ->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->count();
        }

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

    public function countProducts($seller_id = "", $store_id = "")
    {
        $query = Product::query();

        if (!empty($seller_id)) {
            $query->where('seller_id', $seller_id);
        }

        if (!empty($store_id)) {
            $query->where('store_id', $store_id);
        }

        return $query->count();
    }

    public function getProductFaqs($id = null, $product_id = null, $user_id = '', $search = '', $limit = '', $offset = '', $sort = '', $order = '', $is_seller = false, $seller_id = '')
    {
        $limit = $limit ?: 10;
        $offset = $offset ?: 0;
        $sort = $sort ?: 'id';
        $order = $order ?: 'desc';

        $query = ProductFaq::with(['user', 'answeredBy']);

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
    public function countProductsAvailabilityStatus($seller_id = "", $store_id = "")
    {
        $query = Product::query()
            ->whereNotNull('stock_type')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('stock', 0)
                        ->where('availability', 0);
                })->orWhereHas('variants', function ($variant) {
                    $variant->where('stock', 0)
                        ->where('availability', 0);
                });
            });

        if (!empty($seller_id)) {
            $query->where('seller_id', $seller_id);
        }

        if (!empty($store_id)) {
            $query->where('store_id', $store_id);
        }

        return $query->distinct('id')->count('id');
    }
    public function getAttributeIdsByValue(array $values, $names)
    {
        if (is_string($names)) {
            $names = explode(',', str_replace('-', ' ', $names));
            $names = array_map('trim', $names);
        }

        if (empty($values) || empty($names)) {
            return [];
        }

        return Attribute_values::whereIn('value', $values)
            ->whereHas('attribute', function ($query) use ($names) {
                $query->whereIn('name', $names);
            })
            ->pluck('id')
            ->toArray();
    }
}