<?php

namespace App\Http\Controllers\Seller;

use App\Models\ComboProductRating;
use Illuminate\Routing\Controller;


class ComboProductRatingController extends Controller
{
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

        // dd($rating_data);
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
}
