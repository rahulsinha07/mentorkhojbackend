<?php

namespace App\CentralLogics;


use App\Model\CategoryDiscount;
use App\Model\FavoriteProduct;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\Review;
use App\User;
use Illuminate\Support\Facades\DB;

class ProductLogic
{
    public static function getProduct($id)
    {
        return Product::active()->withCount(['wishlist'])->with(['rating', 'active_reviews', 'active_reviews.customer'])->where('id', $id)->first();
    }

    public static function getLatestProducts($limit = 10, $offset = 1)
    {
        $paginator = Product::active()
            ->withCount(['wishlist'])
            ->with(['rating', 'active_reviews'])
            ->latest()->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
    }

    public static function getFavoriteProducts($limit, $offset, $user_id)
    {
        $limit = is_null($limit) ? 500 : $limit;
        $offset = is_null($offset) ? 1 : $offset;

        $ids = User::with('favorite_products')->find($user_id)->favorite_products->pluck('product_id')->toArray();
        $favoriteProducts = Product::whereIn('id', $ids)->paginate($limit, ['*'], 'page', $offset);

        $formatted_products = Helpers::product_data_formatting($favoriteProducts, true);

        return [
            'total_size' => $favoriteProducts->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $formatted_products
        ];
    }

    public static function getRelatedProducts($product_id)
    {
        $product = Product::find($product_id);
        return Product::active()->withCount(['wishlist'])->with(['rating', 'active_reviews'])->where('category_ids', $product->category_ids)
            ->where('id', '!=', $product->id)
            ->limit(10)
            ->get();
    }

    public static function searchProducts($name, $lowestPrice, $highestPrice, $sortBy, $limit = 10, $offset = 1)
    {
        $key = explode(' ', $name);
        $paginator = Product::active()
            ->withCount(['wishlist'])
            ->with(['rating', 'active_reviews'])
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
                $q->orWhereHas('tags',function($query) use ($key){
                    $query->where(function($q) use ($key){
                        foreach ($key as $value) {
                            $q->where('tag', 'like', "%{$value}%");
                        };
                    });
                });
            })
            ->when(isset($sortBy) && $sortBy == 'low_to_high', function ($query){
                return $query->orderBy('price', 'ASC');
            })
            ->when(isset($sortBy) && $sortBy == 'high_to_low', function ($query){
                return $query->orderBy('price', 'DESC');
            })
            ->when(isset($sortBy) && $sortBy == 'descending', function ($query){
                return $query->orderBy('name', 'DESC');
            })
            ->when(isset($sortBy) && $sortBy == 'ascending', function ($query){
                return $query->orderBy('name', 'ASC');
            })
            ->when(($lowestPrice != null && $highestPrice != null), function ($query) use ($lowestPrice, $highestPrice) {
                return $query->whereBetween('price',[$lowestPrice, $highestPrice]);
            })
            ->paginate($limit, ['*'], 'page', $offset);

        $lowestPrice = $lowestPrice ?? $paginator->min('price');
        $highestPrice = $highestPrice ?? $paginator->max('price');

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'lowest_price' => $lowestPrice,
            'highest_price' => $highestPrice,
            'products' => $paginator->items()
        ];
    }

    public static function get_product_review($id)
    {
        $reviews = Review::active()->where('product_id', $id)->get();
        return $reviews;
    }

    public static function get_rating($reviews)
    {
        $rating5 = 0;
        $rating4 = 0;
        $rating3 = 0;
        $rating2 = 0;
        $rating1 = 0;
        foreach ($reviews as $key => $review) {
            if ($review->rating == 5) {
                $rating5 += 1;
            }
            if ($review->rating == 4) {
                $rating4 += 1;
            }
            if ($review->rating == 3) {
                $rating3 += 1;
            }
            if ($review->rating == 2) {
                $rating2 += 1;
            }
            if ($review->rating == 1) {
                $rating1 += 1;
            }
        }
        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }

    public static function getOverallRating($reviews)
    {
        $totalRating = count($reviews);
        $rating = 0;
        foreach ($reviews as $key => $review) {
            $rating += $review->rating;
        }
        if ($totalRating == 0) {
            $overallRating = 0;
        } else {
            $overallRating = number_format($rating / $totalRating, 2);
        }

        return [$overallRating, $totalRating];
    }

    public static function getPopularProducts($limit = 10, $offset = 1)
    {
        $paginator = Product::active()->with(['rating', 'active_reviews'])->orderBy('popularity_count', 'desc')->paginate($limit, ['*'], 'page', $offset);
        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
    }

    public static function getMostViewedProducts($limit = 10, $offset = 1)
    {
        $paginator = Product::active()
            ->with(['rating', 'active_reviews'])
            ->orderBy('view_count', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
    }

    public static function getTrendingProducts($limit = 10, $offset = 1)
    {
        if(OrderDetail::count() > 0) {
            $paginator = Product::active()
                ->with(['rating', 'active_reviews'])
                ->whereHas('order_details', function ($query) {
                    $query->where('created_at', '>', now()->subDays(30)->endOfDay());
                })
                ->withCount('order_details')
                ->orderBy('order_details_count', 'desc')
                ->paginate($limit, ['*'], 'page', $offset);

        } else {
            $paginator = Product::active()
                ->with(['rating', 'active_reviews'])
                ->inRandomOrder()
                ->paginate($limit, ['*'], 'page', $offset);
        }

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
    }

    public static function getRecommendedProducts($user, $limit = 10, $offset = 1)
    {
        if($user != null) {
            $orderIds = Order::where('user_id', $user->id)->pluck('id');
            $productIds = OrderDetail::whereIn('order_id', $orderIds)->pluck('product_id')->toArray();
            $categoryIds = Product::whereIn('id', $productIds)->pluck('category_ids')->toArray();

            $ids = [];
            foreach ($categoryIds as $value) {
                $items = json_decode($value);
                foreach ($items as $item) {
                    if ($item->position == 1) {
                        $ids[] = $item->id;
                    }
                }
            }
            $ids = array_unique($ids);

            $paginator = Product::active()
                ->with(['rating', 'active_reviews'])
                ->where(function ($query) use ($ids) {
                    foreach ($ids as $id) {
                        $query->orWhereJsonContains('category_ids', [['id' => $id, 'position' => 1]]);
                    }
                })
                ->paginate($limit, ['*'], 'page', $offset);

        } else {
            $paginator = Product::active()
                ->with(['rating', 'active_reviews'])
                ->inRandomOrder()
                ->paginate($limit, ['*'], 'page', $offset);
        }

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
    }

    public static function getMostReviewedProducts($limit = 10, $offset = 1)
    {
        $paginator = Product::active()
            ->with(['rating', 'active_reviews'])
            ->withCount('active_reviews')
            ->orderBy('active_reviews_count', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
    }

}
