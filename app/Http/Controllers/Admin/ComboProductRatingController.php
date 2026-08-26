<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrderItems;
use App\Models\ComboProduct;
use App\Models\ComboProductRating;
use App\Models\Seller;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;


class ComboProductRatingController extends Controller
{
    public function set_rating(Request $request, $files)
    {
        $data = $request->all();

        $rating = [
            'user_id' => $data['user_id'],
            'product_id' => $data['product_id'],
        ];

        if (isset($data['rating']) && !empty($data['rating'])) {
            $rating['rating'] = $data['rating'];
        }

        if (isset($data['comment']) && !empty($data['comment'])) {
            $rating['comment'] = $data['comment'];
        }

        if (isset($data['title']) && !empty($data['title'])) {
            $rating['title'] = $data['title'];
        }

        if ($files) {
            foreach ($files as $file) {
                if (is_array($file)) {
                    // If $file is an array, you need to iterate through its contents
                    foreach ($file as $f) {
                        $uploadedImage = $this->uploadFile($f);
                        $uploadedImages[] = $uploadedImage;
                    }
                } else {
                    // Handle the single file object
                    $uploadedImage = $this->uploadFile($file);
                    $uploadedImages[] = $uploadedImage;
                }
            }
        }
        $rating['images'] = isset($uploadedImages) && !empty($uploadedImages) ? json_encode($uploadedImages) : '';

        $existing_rating = ComboProductRating::where('user_id', $data['user_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing_rating) {
            $existing_rating->update($rating);
        } else {
            ComboProductRating::create($rating);
        }

        if (isset($data['rating']) && !empty($data['rating'])) {
            // Update product rating
            $product = ComboProduct::find($data['product_id']);
            $ratings = ComboProductRating::where('product_id', $data['product_id'])->count();
            $total_rating = ComboProductRating::where('product_id', $data['product_id'])->sum('rating');
            $new_rating = ($ratings > 0) ? round($total_rating / $ratings, 1, PHP_ROUND_HALF_UP) : 0;
            $product->update(['rating' => $new_rating, 'no_of_ratings' => $ratings]);

            // Update seller rating
            $store_id = $product->store_id;
            $seller_id = $product->seller_id;
            $seller_ratings = ComboProduct::where('seller_id', $seller_id)->where('rating', '>', 0)->count();

            $seller_total_rating = ComboProduct::where('seller_id', $seller_id)->sum('rating');
            $seller_new_rating = ($seller_ratings > 0) ? round($seller_total_rating / $seller_ratings, 1, PHP_ROUND_HALF_UP) : 0;

            $seller = Seller::find($seller_id);
            // dd($seller);
            // dd($seller_new_rating);
            $seller->stores()->updateExistingPivot($store_id, [
                'rating' => $seller_new_rating,
                'no_of_ratings' => $seller_ratings
            ]);
        }
        return true;
    }


    private function uploadFile($file)
    {
        $image_original_name = $file->getClientOriginalName();
        $image = Storage::disk('public')->putFileAs('review_images', $file, $image_original_name);
        return $image;
    }

    public function fetch_rating($product_id = '', $user_id = '', $limit = '', $offset = '', $sort = 'id', $order = 'desc', $rating_id = '', $has_images = '', $rating = '', $count_empty_comments = false)
    {
        // Fetch product ratings with user data
        $query = ComboProductRating::with(['user:id,username,image'])
            ->when($product_id, fn($q) => $q->where('product_id', $product_id))
            ->when($user_id, fn($q) => $q->where('user_id', $user_id))
            ->when($rating_id, fn($q) => $q->where('id', $rating_id))
            ->when($rating, fn($q) => $q->where('rating', $rating))
            ->when($has_images == 1, fn($q) => $q->whereNotNull('images'))
            ->when($sort && $order, fn($q) => $q->orderBy($sort, $order))
            ->skip($offset)
            ->take($limit);

        $rating_data = $query->get();

        // Transform images and user data
        $rating_data = $rating_data->transform(function ($rating) {
            $ratingArray = $rating->toArray(); // convert to raw array early

            // Format dates
            $ratingArray['created_at'] = $rating->created_at ? $rating->created_at->format('Y-m-d H:i:s') : null;
            $ratingArray['updated_at'] = $rating->updated_at ? $rating->updated_at->format('Y-m-d H:i:s') : null;

            // Decode images
            $ratingArray['images'] = $rating->images ? array_map(fn($img) => asset('storage/' . $img), json_decode($rating->images, true)) : [];

            // Add user data
            $ratingArray['user_profile'] = $rating->user?->image ? asset(config('constants.USER_IMG_PATH') . $rating->user->image) : null;
            $ratingArray['user_name'] = $rating->user?->username ?? null;
            unset($ratingArray['user']);
            return $ratingArray;
        })->toArray();

        // Aggregates
        $res = [];

        // Total number of ratings
        $res['no_of_rating'] = ComboProductRating::where('product_id', $product_id)->count();

        // Total reviews with images
        $res['total_images'] = (string) ComboProductRating::where('product_id', $product_id)
            ->whereNotNull('images')
            ->get()
            ->sum(function ($item) {
                $decoded = json_decode($item->images, true);
                return is_array($decoded) ? count($decoded) : 0;
            });

        // Star-wise review breakdown
        $star_counts = ComboProductRating::selectRaw('
                count(id) as total,
                sum(case when CEILING(rating) = 1 then 1 else 0 end) as rating_1,
                sum(case when CEILING(rating) = 2 then 1 else 0 end) as rating_2,
                sum(case when CEILING(rating) = 3 then 1 else 0 end) as rating_3,
                sum(case when CEILING(rating) = 4 then 1 else 0 end) as rating_4,
                sum(case when CEILING(rating) = 5 then 1 else 0 end) as rating_5
        ')
            ->where('product_id', $product_id)
            ->first();

        $res['total_reviews'] = $star_counts->total ?? 0;
        $res['star_1'] = $star_counts->rating_1 ?? 0;
        $res['star_2'] = $star_counts->rating_2 ?? 0;
        $res['star_3'] = $star_counts->rating_3 ?? 0;
        $res['star_4'] = $star_counts->rating_4 ?? 0;
        $res['star_5'] = $star_counts->rating_5 ?? 0;

        // Reviews with non-empty comments
        $res['no_of_reviews'] = $count_empty_comments
            ? ComboProductRating::where('product_id', $product_id)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->count()
            : 0;

        // Final product rating data
        $res['product_rating'] = $rating_data;

        return $res;
    }


    public function delete_rating($rating_id)
    {

        $rating_id = (int) $rating_id;
        $rating_details = ComboProductRating::find($rating_id);

        if ($rating_details) {
            $images = json_decode($rating_details->images, true);

            if (!empty($images)) {
                foreach ($images as $image) {
                    Storage::disk('public')->delete($image);
                }
            }

            $rating_details->delete();

            $product = ComboProduct::find($rating_details->product_id);

            if ($product) {
                $combo_product_ratings = ComboProductRating::selectRaw('count(rating) as no_of_ratings, sum(rating) as sum_of_rating')
                    ->where('product_id', $product->id)
                    ->first();

                $no_of_rating = $combo_product_ratings->no_of_ratings;
                $total_rating = $combo_product_ratings->sum_of_rating;

                $newrating = ($no_of_rating > 0) ? round($total_rating / $no_of_rating, 1, PHP_ROUND_HALF_UP) : 0;

                $product->update(['rating' => $newrating, 'no_of_ratings' => $no_of_rating]);

                $seller_rating = ComboProduct::selectRaw('count(rating) as no_of_ratings, sum(rating) as sum_of_rating')
                    ->where('seller_id', $product->seller_id)
                    ->where('rating', '>', 0)
                    ->first();

                $no_of_ratings_seller = $seller_rating->no_of_ratings;
                $total_rating_seller = $seller_rating->sum_of_rating;

                $new_rating_seller = ($no_of_ratings_seller > 0) ? round($total_rating_seller / $no_of_ratings_seller, 1, PHP_ROUND_HALF_UP) : 0;

                Seller::where('user_id', $product->seller_id)
                    ->update(['rating' => $new_rating_seller, 'no_of_ratings' => $no_of_ratings_seller]);
            }
            return true;
        } else {
            return false;
        }
    }
}
