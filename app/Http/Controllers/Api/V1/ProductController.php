<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Model\CategorySearchedByUser;
use App\Model\FavoriteProduct;
use App\Model\Product;
use App\Model\ProductSearchedByUser;
use App\Model\RecentSearch;
use App\Model\Review;
use App\Model\SearchedCategory;
use App\Model\SearchedKeywordCount;
use App\Model\SearchedKeywordUser;
use App\Model\SearchedProduct;
use App\Model\Translation;
use App\VisitedProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function __construct(
        private CategorySearchedByUser $category_searched_by_user,
        private FavoriteProduct $favorite_product,
        private Product $product,
        private ProductSearchedByUser $product_searched_by_user,
        private RecentSearch $recentSearch,
        private Review $review,
        private SearchedCategory $searched_category,
        private SearchedKeywordCount $searched_keyword_count,
        private SearchedKeywordUser $searched_keyword_user,
        private SearchedProduct $searched_product,
        private Translation $translation,
        private VisitedProduct $visited_product
    ){}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllProducts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sort_by' => 'nullable|in:latest,popular,recommended,trending',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $sortBy = $request['sort_by'];

        if ($sortBy == 'latest'){
            $products = ProductLogic::getLatestProducts($request['limit'], $request['offset']);
        }elseif ($sortBy == 'popular'){
            $products = ProductLogic::getPopularProducts($request['limit'], $request['offset']);
        }elseif ($sortBy == 'recommended'){
            $user = $request->user();
            $products = ProductLogic::getRecommendedProducts($user, $request['limit'], $request['offset']);
        }elseif ($sortBy == 'trending'){
            $products = ProductLogic::getTrendingProducts($request['limit'], $request['offset']);
        }else{
            $products = ProductLogic::getLatestProducts($request['limit'], $request['offset']);
        }

        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }
    /**
 * @param Request $request
 * @return JsonResponse
 */


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getLatestProducts(Request $request): JsonResponse
    {
        $products = ProductLogic::getLatestProducts($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }
    public function createProduct(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'category_ids' => 'required|array',
        'category_ids.*.id' => 'required|integer|exists:categories,id',
        'category_ids.*.position' => 'required|integer',
        'images' => 'required|array',
        'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'total_stock' => 'required|numeric|min:1',
        'price' => 'required|numeric|min:0',
        'discount' => 'nullable|numeric|min:0|max:100',
        'discount_type' => 'nullable|in:amount,percent',
        'tax' => 'nullable|numeric|min:0',
        'tax_type' => 'nullable|in:amount,percent',
        'unit' => 'nullable|string|max:50',
        'description' => 'nullable|string',
    ], [
        'name.required' => 'Product name is required!',
        'category_ids.required' => 'Category is required!',
        'images.required' => 'At least one image is required!',
        'total_stock.required' => 'Total stock is required!',
        'price.required' => 'Price is required!',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
    }

    // Handle discount validation
    if ($request['discount_type'] == 'percent') {
        $discount = ($request['price'] / 100) * $request['discount'];
    } else {
        $discount = $request['discount'];
    }

    if ($request['price'] <= $discount) {
        return response()->json(['errors' => ['message' => 'Discount cannot be greater than or equal to the price']], 403);
    }

    // Handle image upload
    $imageNames = [];
    if (!empty($request->file('images'))) {
        foreach ($request->images as $img) {
            $imageData = Helpers::upload('product/', 'png', $img);
            $imageNames[] = $imageData;
        }
    } else {
        $imageNames[] = 'default.png'; // Use a default image if none provided
    }

    // Handle tags
    $tagIds = [];
    if ($request->tags != null) {
        $tags = explode(",", $request->tags);
        foreach ($tags as $value) {
            $tag = Tag::firstOrNew(['tag' => $value]);
            $tag->save();
            $tagIds[] = $tag->id;
        }
    }

    // Handle categories
    $category = [];
    if ($request->category_ids != null) {
        foreach ($request->category_ids as $cat) {
            $category[] = [
                'id' => $cat['id'],
                'position' => $cat['position'],
            ];
        }
    }

    // Handle choice options
    $choiceOptions = [];
    if ($request->has('choice')) {
        foreach ($request->choice_no as $key => $no) {
            $str = 'choice_options_' . $no;
            if ($request[$str][0] == null) {
                return response()->json(['errors' => ['message' => 'Choice option values cannot be null!']], 403);
            }
            $item['name'] = 'choice_' . $no;
            $item['title'] = $request->choice[$key];
            $item['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', $request[$str])));
            $choiceOptions[] = $item;
        }
    }

    // Handle variations
    $variations = [];
    $options = [];
    if ($request->has('choice_no')) {
        foreach ($request->choice_no as $key => $no) {
            $name = 'choice_options_' . $no;
            $my_str = implode('|', $request[$name]);
            $options[] = explode(',', $my_str);
        }
    }
    $combinations = Helpers::combinations($options);

    $stockCount = 0;
    if (count($combinations[0]) > 0) {
        foreach ($combinations as $combination) {
            $str = implode('-', array_map('str_replace', [' ', ''], $combination));
            $item = [];
            $item['type'] = $str;
            $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
            $item['stock'] = abs($request['stock_' . str_replace('.', '_', $str)]);

            if ($request['discount_type'] == 'amount' && $item['price'] <= $request['discount']) {
                return response()->json(['errors' => ['message' => 'Discount cannot be greater than or equal to the price for variant '. $item['type']]], 403);
            }

            $variations[] = $item;
            $stockCount += $item['stock'];
        }
    } else {
        $stockCount = (integer)$request['total_stock'];
    }

    if ((integer)$request['total_stock'] != $stockCount) {
        return response()->json(['errors' => ['message' => 'Stock calculation mismatch!']], 403);
    }

    // Create the product
    $product = new Product();
    $product->name = $request->name;
    $product->category_ids = json_encode($category);
    $product->description = $request->description;
    $product->choice_options = json_encode($choiceOptions);
    $product->variations = json_encode($variations);
    $product->price = $request->price;
    $product->unit = $request->unit;
    $product->image = json_encode($imageNames);
    $product->capacity = $request->capacity ?? null;
    $product->maximum_order_quantity = $request->maximum_order_quantity ?? null;
    $product->tax = $request->tax_type == 'amount' ? $request->tax : $request->tax;
    $product->tax_type = $request->tax_type;
    $product->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
    $product->discount_type = $request->discount_type;
    $product->total_stock = $request->total_stock;
    $product->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
    $product->status = $request->status ? $request->status : 0;
    $product->save();

    // Sync tags
    $product->tags()->sync($tagIds);

    // Handle translations (if any)
    if ($request->has('lang')) {
        $data = [];
        foreach ($request->lang as $index => $key) {
            if ($request->name[$index] && $key != 'en') {
                $data[] = [
                    'translationable_type' => 'App\Model\Product',
                    'translationable_id' => $product->id,
                    'locale' => $key,
                    'key' => 'name',
                    'value' => $request->name[$index],
                ];
            }
            if ($request->description[$index] && $key != 'en') {
                $data[] = [
                    'translationable_type' => 'App\Model\Product',
                    'translationable_id' => $product->id,
                    'locale' => $key,
                    'key' => 'description',
                    'value' => $request->description[$index],
                ];
            }
        }
        Translation::insert($data);
    }

    return response()->json(['message' => 'Product created successfully!'], 201);
}

    
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getSearchedProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $products = ProductLogic::searchProducts(
            name: $request['name'],
            lowestPrice: $request['price_low'],
            highestPrice: $request['price_high'],
            sortBy: $request['sort_by'],
            limit: $request['limit'],
            offset: $request['offset']
        );

        if (count($products['products']) == 0) {
            $key = explode(' ', $request['name']);
            $ids = $this->translation->where(['key' => 'name'])->where(function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->orWhere('value', 'like', "%{$value}%");
                }
            })->pluck('translationable_id')->toArray();

            $paginator = $this->product->active()
                ->with(['rating'])
                ->whereIn('id', $ids)->withCount(['wishlist'])
                ->when(isset($request['sort_by']) && $request['sort_by'] == 'low_to_high', function ($query){
                    return $query->orderBy('price', 'ASC');
                })
                ->when(isset($request['sort_by']) && $request['sort_by'] == 'high_to_low', function ($query){
                    return $query->orderBy('price', 'DESC');
                })
                ->when(isset($request['sort_by']) && $request['sort_by'] == 'descending', function ($query){
                    return $query->orderBy('name', 'DESC');
                })
                ->when(isset($request['sort_by']) && $request['sort_by'] == 'ascending', function ($query){
                    return $query->orderBy('name', 'ASC');
                })
                ->when(($request['price_low'] != null && $request['price_high'] != null), function ($query) use ($request) {
                    return $query->whereBetween('price', [$request['price_low'], $request['price_high']]);
                })
                ->paginate($request['limit'], ['*'], 'page', $request['offset']);

            $lowestPrice = $request['price_low'] ?? $paginator->min('price');
            $highestPrice = $request['price_high'] ?? $paginator->max('price');

            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'lowest_price' => $lowestPrice,
                'highest_price' => $highestPrice,
                'products' => $paginator->items()
            ];
        }

        $authUser = auth('api')->user();
        $keyword = strtolower($request['name']);

        $recentSearch = $this->recentSearch->firstOrCreate(['keyword' => $keyword], [
            'keyword' => $keyword,
        ]);

        $recentSearchUser = $this->searched_keyword_user;
        $recentSearchUser->recent_search_id = $recentSearch->id;
        $recentSearchUser->user_id = $authUser->id ?? null;
        $recentSearchUser->save();

        $searchedCount = $this->searched_keyword_count;
        $searchedCount->recent_search_id = $recentSearch->id;
        $searchedCount->keyword_count = 1;
        $searchedCount->save();

        $categoryIds = [];
        foreach ($products['products'] as $searched_result){
            $categories =  json_decode($searched_result['category_ids']);
            if(!is_null($categories) && count($categories) > 0) {
                foreach ($categories as $value) {
                    if ($value->position == 1) {
                        $categoryIds[] = $value->id;
                    }
                }
            }

            $searchedProductData = $this->searched_product->firstOrCreate([
                'recent_search_id' => $recentSearch->id,
                'product_id' => $searched_result->id
            ], [
                'recent_search_id' => $recentSearch->id,
                'product_id' => $searched_result->id
            ]);

            if (auth('api')->user()){
                $productSearchedByUser = $this->product_searched_by_user->firstOrCreate([
                    'user_id' => $authUser->id,
                    'product_id' => $searched_result->id
                ], [
                    'user_id' => $authUser->id,
                    'product_id' => $searched_result->id
                ]);
            }
        }

        $categoryIds = array_unique($categoryIds);
        foreach ($categoryIds as $cat_id){
            $searchedCategoryData = $this->searched_category->firstOrCreate([
                'recent_search_id' => $recentSearch->id,
                'category_id' => $cat_id,
            ], [
                'recent_search_id' => $recentSearch->id,
                'category_id' => $cat_id,
            ]);

            if (auth('api')->user()){
                $categorySearchedByUser = $this->category_searched_by_user->firstOrCreate([
                    'user_id' => $authUser->id,
                    'category_id' => $cat_id
                ], [
                    'user_id' => $authUser->id,
                    'category_id' => $cat_id
                ]);
            }

        }

        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function getProduct(Request $request, $id): JsonResponse
    {
        try {
            $product = ProductLogic::getProduct($id);
            if (!isset($product)) {
                return response()->json(['errors' => ['code' => 'product-001', 'message' => 'Product not found!']], 404);
            }

            $product = Helpers::product_data_formatting($product, false);

            $product->increment('view_count');

            if($request->has('attribute') && $request->attribute == 'product' && !is_null(auth('api')->user())) {
                $visitedProduct = $this->visited_product;
                $visitedProduct->user_id = auth('api')->user()->id ?? null;
                $visitedProduct->product_id = $product->id;
                $visitedProduct->save();
            }

            return response()->json($product, 200);

        } catch (\Exception $e) {
            return response()->json(['errors' => ['code' => 'product-001', 'message' => 'Product not found!']], 404);
        }
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getRelatedProducts($id): JsonResponse
    {
        if ($this->product->find($id)) {
            $products = ProductLogic::getRelatedProducts($id);
            $products = Helpers::product_data_formatting($products, true);
            return response()->json($products, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => 'Product not found!'],
        ], 404);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getProductReviews($id): JsonResponse
    {
        $reviews = $this->review->with(['customer'])->where(['product_id' => $id])->get();

        $storage = [];
        foreach ($reviews as $item) {
            $item['attachment'] = json_decode($item['attachment']);
            $storage[] = $item;
        }

        return response()->json($storage, 200);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getProductRating($id): JsonResponse
    {
        try {
            $product = $this->product->find($id);
            $overallRating = ProductLogic::getOverallRating($product->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function submitProductReview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'order_id' => 'required',
            'comment' => 'required',
            'rating' => 'required|numeric|max:5',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $product = $this->product->find($request->product_id);
        if (!isset($product)) {
            $validator->errors()->add('product_id', 'There is no such product');
        }

        $multipleReview = $this->review->where(['product_id' => $request->product_id, 'user_id' => $request->user()->id])->first();
        $review = $multipleReview ?? $this->review;

        $imageArray = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    $imageArray[] = Storage::disk('public')->put('review', $image);
                }
            }
        }

        $review->user_id = $request->user()->id;
        $review->product_id = $request->product_id;
        $review->order_id = $request->order_id;
        $review->comment = $request->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($imageArray);
        $review->save();

        return response()->json(['message' => 'successfully review submitted!'], 200);
    }

    /**
     * @return JsonResponse
     */
    public function getDiscountedProducts(): JsonResponse
    {
        try {
            $products = Helpers::product_data_formatting($this->product->active()->withCount(['wishlist'])->with(['rating'])->where('discount', '>', 0)->get(), true);
            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!'],
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getDailyNeedProducts(Request $request): JsonResponse
    {
        try {
            $paginator = $this->product->active()->withCount(['wishlist'])->with(['rating'])->where(['daily_needs' => 1])->orderBy('id', 'desc')->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'products' => $paginator->items()
            ];
            $paginator = Helpers::product_data_formatting($products['products'], true);

            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Products not found!'],
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getFavoriteProducts(Request $request): JsonResponse
    {
        $products = ProductLogic::getFavoriteProducts($request['limit'], $request['offset'], $request->user()->id);
        return response()->json($products, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getPopularProducts(Request $request): JsonResponse
    {
        $products = ProductLogic::getPopularProducts($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);

    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addFavoriteProducts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
        ],
            [
                'product_ids.required' => 'product_ids ' .translate('is required'),
                'product_ids.array' => 'product_ids ' .translate('must be an array')
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $favoriteIds = [];
        foreach ($request->product_ids as $id) {
            $values = [
                'user_id' => $request->user()->id,
                'product_id' => $id,
                'created_at' => now(),
                'updated_at' => now()
            ];
            $favoriteIds[] = $values;
        }
        $this->favorite_product->insert($favoriteIds);

        return response()->json(['message' => translate('Item added to favourite list!')], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFavoriteProducts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
        ],
            [
                'product_ids.required' => 'product_ids ' .translate('is required'),
                'product_ids.array' => 'product_ids ' .translate('must be an array')
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $collection = $this->favorite_product->whereIn('product_id', $request->product_ids)->get(['id']);
        $this->favorite_product->destroy($collection->toArray());

        return response()->json(['message' => translate('Item removed from favourite list! ')], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function featuredProducts(Request $request): JsonResponse
    {
        try {
            $paginator = $this->product->active()
                ->withCount(['wishlist'])
                ->with(['rating'])
                ->where(['is_featured' => 1])
                ->orderBy('id', 'desc')
                ->paginate($request['limit'], ['*'], 'page', $request['offset']);

            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'products' => $paginator->items()
            ];
            $paginator = Helpers::product_data_formatting($products['products'], true);

            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Products not found!'],
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getMostViewedProducts(Request $request): JsonResponse
    {
        $products = ProductLogic::getMostViewedProducts($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrendingProducts(Request $request): JsonResponse
    {
        $products = ProductLogic::getTrendingProducts($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecommendedProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $products = ProductLogic::getRecommendedProducts($user, $request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getMostReviewedProducts(Request $request): JsonResponse
    {
        $products = ProductLogic::getMostReviewedProducts($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

}
