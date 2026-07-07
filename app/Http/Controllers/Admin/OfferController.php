<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\FlashDeal;
use App\Model\FlashDealProduct;
use App\Model\Product;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OfferController extends Controller
{
    public function __construct(
        private FlashDeal $flashDeal,
        private FlashDealProduct $flashDealProduct,
        private Product $product
    ){}

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function flashIndex(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $flashDeal = $this->flashDeal->withCount('products')
                ->where('deal_type', 'flash_deal')
                ->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->Where('title', 'like', "%{$value}%");
                    }
                });
            $queryParam = ['search' => $request['search']];
        } else {
            $flashDeal = $this->flashDeal->withCount('products')->where('deal_type', 'flash_deal');
        }
        $flashDeals = $flashDeal->latest()->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.offer.flash-deal-index', compact('flashDeals', 'search'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function flashStore(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'start_date' => 'required',
            'end_date' => 'required',
            'image' => 'required',
        ],[
            'title.required'=>translate('Title is required'),
        ]);

        if (!empty($request->file('image'))) {
            $image_name = Helpers::upload('offer/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $flashDeal = $this->flashDeal;
        $flashDeal->title = $request->title;
        $flashDeal->start_date = $request->start_date;
        $flashDeal->end_date = $request->end_date;
        $flashDeal->deal_type = 'flash_deal';
        $flashDeal->status = 0;
        $flashDeal->featured = 0;
        $flashDeal->image = $image_name;
        $flashDeal->save();
        Toastr::success(translate('Flash deal added successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->flashDeal->where(['status' => 1])->update(['status' => 0]);
        $flashDeal = $this->flashDeal->find($request->id);
        $flashDeal->status = $request->status;
        $flashDeal->save();
        Toastr::success(translate('Flash deal status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $flashDeal = $this->flashDeal->find($request->id);
        if (Storage::disk('public')->exists('offer/' . $flashDeal['image'])) {
            Storage::disk('public')->delete('offer/' . $flashDeal['image']);
        }
        $flashDealProductIds = $this->flashDealProduct->where(['flash_deal_id' => $request->id])->pluck('product_id');
        $flashDeal->delete();

        $this->flashDealProduct->whereIn('id', $flashDealProductIds)->delete();

        Toastr::success(translate('Flash deal removed!'));
        return back();
    }

    /**
     * @param $flash_deal_id
     * @return Factory|View|Application
     */
    public function flashEdit($flash_deal_id): View|Factory|Application
    {
        $flashDeal = $this->flashDeal->find($flash_deal_id);
        return view('admin-views.offer.edit-flash-deal', compact('flashDeal'));
    }

    /**
     * @param Request $request
     * @param $flash_deal_id
     * @return RedirectResponse
     */
    public function flashUpdate(Request $request, $flash_deal_id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'start_date' => 'required',
            'end_date' => 'required',
        ],[
            'title.required'=>translate('Title is required'),
        ]);

        $flashDeal = $this->flashDeal->find($flash_deal_id);
        $flashDeal->title = $request->title;
        $flashDeal->start_date = $request->start_date;
        $flashDeal->end_date = $request->end_date;
        $flashDeal->image = $request->has('image') ? Helpers::update('offer/', $flashDeal->image, 'png', $request->file('image')) : $flashDeal->image;
        $flashDeal->save();
        Toastr::success(translate('Flash deal updated successfully!'));
        return redirect()->route('admin.offer.flash.index');
    }

    /**
     * @param $flash_deal_id
     * @return Factory|View|Application
     */
    public function addFlashSaleProduct($flash_deal_id): View|Factory|Application
    {
        $flashDeal = $this->flashDeal->where('id', $flash_deal_id)->first();
        $flashDealProductIds = $this->flashDealProduct->where('flash_deal_id', $flash_deal_id)->pluck('product_id');
        $flashDealProducts = $this->product->whereIn('id', $flashDealProductIds)->paginate(Helpers::getPagination());
        $products = $this->product->active()->whereNotIn('id', $flashDealProductIds)->orderBy('name', 'asc')->get();

        return view('admin-views.offer.add-product-index', compact('flashDeal', 'flashDealProducts', 'products'));
    }

    /**
     * @param Request $request
     * @param $flash_deal_id
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function flashProductStore(Request $request, $flash_deal_id): RedirectResponse
    {
        $this->validate($request, [
            'product_id' => 'required'
        ]);
        $flashDealProducts = $this->flashDealProduct->where(['flash_deal_id' => $flash_deal_id, 'product_id' => $request['product_id']])->first();

        if(!isset($flashDealProducts))
        {
            DB::table('flash_deal_products')->insertOrIgnore([
                'product_id' => $request['product_id'],
                'flash_deal_id' => $flash_deal_id,
                'discount' => $request['discount'],
                'discount_type' => $request['discount_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Toastr::success('Product added successfully!');
        }else{
            Toastr::info('Product already added!');
        }
        return back();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteFlashProduct(Request $request): JsonResponse
    {
        $this->flashDealProduct->where(['product_id' => $request->id, 'flash_deal_id' => $request->flash_deal_id])->delete();
        return response()->json();
    }
}
