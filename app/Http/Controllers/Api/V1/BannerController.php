<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Model\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function __construct(
        private Banner $banner
    ){}


    /**
     * @return JsonResponse
     */
    public function getBanners(): JsonResponse
    {
        $banners = $this->banner->active()->get();
        return response()->json($banners, 200);
    }
}
