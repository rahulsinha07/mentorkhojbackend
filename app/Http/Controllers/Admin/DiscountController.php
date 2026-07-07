<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Category;
use App\Model\CategoryDiscount;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function __construct(
        private Category $category,
        private CategoryDiscount $categoryDiscount
    ){}

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    function index(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $discounts = $this->categoryDiscount->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            })->orderBy('id', 'desc');
            $queryParam = ['search' => $request['search']];
        }else{
            $discounts = $this->categoryDiscount->orderBy('id', 'desc');
        }
        $discounts = $discounts->paginate(Helpers::getPagination())->appends($queryParam);

        $categories = $this->category->where(['parent_id'=>0])->orderBy('name')->get();
        return view('admin-views.discount.index', compact('discounts', 'categories','search'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|max:255',
            'category_id' => 'required|unique:category_discounts,category_id',
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount_type' => 'required',
            'discount_amount' => 'required',
            'maximum_amount' => 'required_if:discount_type,percent',
        ],[
            'name.required'=>translate('Name is required'),
            'category_id.required'=>translate('Category select is required'),
            'start_date.required'=>translate('Start date select is required'),
            'expire_date.required'=>translate('Expire date select is required'),
            'category_id.unique'=>translate('Discount on this Category is already exist'),
            'discount_type.required'=>translate('Discount type is required'),
            'discount_amount.required'=>translate('Discount amount is required'),
        ]);

        if ($request->discount_type === 'percent' && $request->discount_amount > 100){
            Toastr::error(translate('Discount amount can not more than 100 percent!'));
            return back();
        }

        $discount = $this->categoryDiscount;
        $discount->name = $request->name;
        $discount->category_id = $request->category_id;
        $discount->start_date = $request->start_date;
        $discount->expire_date = $request->expire_date;
        $discount->discount_type = $request->discount_type;
        $discount->discount_amount = $request->discount_amount;
        $discount->maximum_amount = $request->discount_type == 'percent' ? $request->maximum_amount : 0;
        $discount->save();
        Toastr::success(translate('Discount added successfully!'));
        return back();
    }

    /**
     * @param $id
     * @return Application|Factory|View
     */
    public function edit($id): View|Factory|Application
    {
        $discount = $this->categoryDiscount->find($id);
        $categories = $this->category->where(['parent_id'=>0])->orderBy('name')->get();
        return view('admin-views.discount.edit', compact('discount', 'categories'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $discount = $this->categoryDiscount->find($request->id);
        $discount->status = $request->status;
        $discount->save();
        Toastr::success(translate('Discount status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'name' => 'required|max:255',
            'category_id' => 'required|unique:category_discounts,category_id,' .$id,
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount_type' => 'required',
            'discount_amount' => 'required',
        ],[
            'name.required'=>translate('Name is required'),
            'category_id.required'=>translate('Category select is required'),
            'start_date.required'=>translate('Start date select is required'),
            'expire_date.required'=>translate('Expire date select is required'),
            'category_id.unique'=>translate('Discount on this Category is already exist'),
            'discount_type.required'=>translate('Discount type is required'),
            'discount_amount.required'=>translate('Discount amount is required'),
        ]);

        $discount = $this->categoryDiscount->find($id);
        $discount->name = $request->name;
        $discount->category_id = $request->category_id;
        $discount->start_date = $request->start_date;
        $discount->expire_date = $request->expire_date;
        $discount->discount_type = $request->discount_type;
        $discount->discount_amount = $request->discount_amount;
        $discount->maximum_amount = $request->discount_type == 'percent' ? $request->maximum_amount : 0;
        $discount->save();
        Toastr::success(translate('Discount updated successfully!'));
        return redirect()->route('admin.discount.add-new');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): RedirectResponse
    {
        $discount = $this->categoryDiscount->find($request->id);
        $discount->delete();
        Toastr::success(translate('Discount removed!'));
        return back();
    }
}
