<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\CategoryLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private Category $category
    ){}


    /**
     * @return JsonResponse
     */
    public function getCategories(): JsonResponse
    {
        $categories = $this->category->where(['position'=>0,'status'=>1])->orderBY('priority', 'ASC')->get();
        return response()->json($categories, 200);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getChildes($id): JsonResponse
    {
        $categories = $this->category->where(['parent_id' => $id,'status'=>1])->get();
        return response()->json($categories, 200);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getProducts($id): JsonResponse
    {
        return response()->json(Helpers::product_data_formatting(CategoryLogic::products($id), true), 200);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getAllProducts($id): JsonResponse
    {
        return response()->json(Helpers::product_data_formatting(CategoryLogic::all_products($id), true), 200);

    }
}
