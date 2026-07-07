<?php

namespace App\Http\Controllers\Branch;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function __construct(
        private Branch $branch
    ){}

    /**
     * @return JsonResponse
     */
    public function businessData(): JsonResponse
    {
        $orderCount = DB::table('orders')->where(['branch_id' => auth('branch')->id(), 'checked' => 0])->count();
        return response()->json([
            'success' => 1,
            'data' => ['new_order' => $orderCount]
        ]);
    }

    /**
     * @return Factory|View|Application
     */
    public function settings(): View|Factory|Application
    {
        return view('branch-views.settings');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function settingsUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required',
        ]);

        $branch = $this->branch->find(auth('branch')->id());

        if ($request->has('image')) {
            $imageName =Helpers::update('branch/', $branch->image, 'png', $request->file('image'));
        } else {
            $imageName = $branch['image'];
        }

        $branch->name = $request->name;
        $branch->phone = $request->phone;
        $branch->image = $imageName;
        $branch->save();
        Toastr::success(translate('Branch updated successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function settingsPasswordUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|same:confirm_password|min:8|max:255',
            'confirm_password' => 'required|max:255',
        ]);

        $branch = $this->branch->find(auth('branch')->id());
        $branch->password = bcrypt($request['password']);
        $branch->save();
        Toastr::success(translate('Branch password updated successfully!'));
        return back();
    }
}
