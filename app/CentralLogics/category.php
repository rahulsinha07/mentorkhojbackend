<?php

namespace App\CentralLogics;

use App\Model\Category;
use App\Model\Product;

class CategoryLogic
{
    public static function parents()
    {
        return Category::where('position', 0)->get();
    }

    public static function child($parent_id)
    {
        return Category::where(['parent_id' => $parent_id])->get();
    }

    public static function products($category_id)
    {
        $products = Product::active()->get();
        $productIds = [];
        foreach ($products as $product) {
            foreach (json_decode($product['category_ids'], true) as $category) {
                if ($category['id'] == $category_id) {
                    $productIds[] = $product['id'];
                }
            }
        }
        return Product::active()->withCount(['wishlist', 'active_reviews'])->with('rating')->whereIn('id', $productIds)->get();
    }

    public static function all_products($id)
    {
        $categoryIds = [];
        $categoryIds[] = (int)$id;
        foreach (CategoryLogic::child($id) as $child){
            $categoryIds[] = $child['id'];
            foreach (CategoryLogic::child($child['id']) as $ch2){
                $categoryIds[] = $ch2['id'];
            }
        }

        $products = Product::active()->with('rating', 'active_reviews')->get();
        $productIds = [];
        foreach ($products as $product) {
            foreach (json_decode($product['category_ids'], true) as $category) {
                if (in_array($category['id'],$categoryIds)) {
                    $productIds[] = $product['id'];
                }
            }
        }

        return Product::active()->withCount(['wishlist'])->with('rating', 'active_reviews')->whereIn('id', $productIds)->get();
    }
}
