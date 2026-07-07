<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\DeliveryMan;
use App\Model\DMReview;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryManController extends Controller
{
    public function __construct(
        private DeliveryMan $deliveryman,
        private DMReview $deliverymanReview
    ){}

    public function index(): Factory|View|Application
    {
        return view('admin-views.delivery-man.index');
    }

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function list(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $deliverymen = $this->deliveryman->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }else{
            $deliverymen = $this->deliveryman;
        }
        $deliverymen = $deliverymen->latest()->where('application_status', 'approved')->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.delivery-man.list', compact('deliverymen','search'));
    }

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function reviewsList(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
             $key = explode(' ', $request['search']);
             $deliverymanIds = $this->deliveryman->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('f_name', 'like', "%{$value}%")
                                ->orWhere('l_name', 'like', "%{$value}%");
                        }
            })->pluck('id')->toArray();
            $reviews = $this->deliverymanReview->with(['delivery_man', 'customer'])->whereIn('delivery_man_id', $deliverymanIds);
            $queryParam = ['search' => $request['search']];
        }else
        {
            $reviews = $this->deliverymanReview->with(['delivery_man', 'customer']);
        }
        $reviews = $reviews->latest()->paginate(Helpers::getPagination())->appends($queryParam);
        return view('admin-views.delivery-man.reviews-list', compact('reviews','search'));
    }

    /**
     * @param $id
     * @return Factory|View|Application
     */
    public function preview($id): View|Factory|Application
    {
        $deliveryman = $this->deliveryman->with(['reviews'])->where(['id' => $id])->first();
        $reviews = $this->deliverymanReview->where(['delivery_man_id' => $id])->latest()->paginate(Helpers::getPagination());
        return view('admin-views.delivery-man.view', compact('deliveryman', 'reviews'));
    }

    /**
     * @param Request $request
     * @return Redirector|Application|RedirectResponse
     */
    public function store(Request $request): Redirector|RedirectResponse|Application
    {
        $request->validate([
            'f_name' => 'required|max:100',
            'email' => 'required|regex:/(.+)@(.+)\.(.+)/i|unique:delivery_men',
            'phone' => 'required|unique:delivery_men',
            'password' => 'required|min:8',
            'password_confirmation' => 'required_with:password|same:password|min:8'
        ], [
            'f_name.required' => translate('First name is required!'),
            'email.required' => translate('Email is required!'),
            'email.unique' => translate('Email must be unique!'),
            'phone.required' => translate('Phone is required!'),
            'phone.unique' => translate('Phone must be unique!'),
        ]);

        if ($request->has('image')) {
            $imageName = Helpers::upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $imageName = 'def.png';
        }

        $identityImageNames = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identityImage = Helpers::upload('delivery-man/', 'png', $img);
                $identityImageNames[] = $identityImage;
            }
            $identityImage = json_encode($identityImageNames);
        } else {
            $identityImage = json_encode([]);
        }

        $deliveryman = $this->deliveryman;
        $deliveryman->f_name = $request->f_name;
        $deliveryman->l_name = $request->l_name;
        $deliveryman->email = $request->email;
        $deliveryman->phone = $request->phone;
        $deliveryman->identity_number = $request->identity_number;
        $deliveryman->identity_type = $request->identity_type;
        $deliveryman->branch_id = $request->branch_id;
        $deliveryman->identity_image = $identityImage;
        $deliveryman->image = $imageName;
        $deliveryman->is_active = 1;
        $deliveryman->password = bcrypt($request->password);
        $deliveryman->application_status= 'approved';
        $deliveryman->save();

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                Mail::to($deliveryman->email)->send(new \App\Mail\Deliveryman\DMRegistration($deliveryman, $request->password));
            }
        } catch (\Exception $e) {
        }

        Toastr::success('Delivery man added successfully!');
        return redirect('admin/delivery-man/list');
    }

    /**
     * @param $id
     * @return Factory|View|Application
     */
    public function edit($id): View|Factory|Application
    {
        $deliveryman = $this->deliveryman->find($id);
        return view('admin-views.delivery-man.edit', compact('deliveryman'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $deliveryman = $this->deliveryman->find($request->id);
        $deliveryman->is_active = $request->status;
        $deliveryman->save();

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                Mail::to($deliveryman->email)->send(new \App\Mail\Deliveryman\DMStatusChange($deliveryman));
            }
        } catch (\Exception $e) {
        }

        Toastr::success('Delivery man status updated!');
        return back();
    }

    /**
     * @param Request $request
     * @param $id
     * @return Redirector|Application|RedirectResponse
     */
    public function update(Request $request, $id): Redirector|RedirectResponse|Application
    {
        $request->validate([
            'f_name' => 'required|max:100',
            'email' => 'required|regex:/(.+)@(.+)\.(.+)/i',
            'password_confirmation' => 'required_with:password|same:password'
        ]);

        $deliveryman = $this->deliveryman->find($id);

        if ($deliveryman['email'] != $request['email']) {
            $request->validate([
                'email' => 'required|unique:delivery_men',
            ]);
        }

        if ($deliveryman['phone'] != $request['phone']) {
            $request->validate([
                'phone' => 'required|unique:delivery_men',
            ]);
        }

        if ($request->has('image')) {
            $imageName = Helpers::update('delivery-man/', $deliveryman->image, 'png', $request->file('image'));
        } else {
            $imageName = $deliveryman['image'];
        }

        if ($request->has('identity_image')){
            foreach (json_decode($deliveryman['identity_image'], true) as $img) {
                if (Storage::disk('public')->exists('delivery-man/' . $img)) {
                    Storage::disk('public')->delete('delivery-man/' . $img);
                }
            }
            $imgKeeper = [];
            foreach ($request->identity_image as $img) {
                $identityImage = Helpers::upload('delivery-man/', 'png', $img);
                $imgKeeper[] = $identityImage;
            }
            $identityImage = json_encode($imgKeeper);
        } else {
            $identityImage = $deliveryman['identity_image'];
        }
        $deliveryman->f_name = $request->f_name;
        $deliveryman->l_name = $request->l_name;
        $deliveryman->email = $request->email;
        $deliveryman->phone = $request->phone;
        $deliveryman->identity_number = $request->identity_number;
        $deliveryman->identity_type = $request->identity_type;
        $deliveryman->branch_id = $request->branch_id;
        $deliveryman->identity_image = $identityImage;
        $deliveryman->image = $imageName;
        $deliveryman->password = strlen($request->password) > 1 ? bcrypt($request->password) : $deliveryman['password'];
        $deliveryman->save();
        Toastr::success(translate('Delivery man updated successfully'));
        return redirect('admin/delivery-man/list');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): RedirectResponse
    {
        $deliveryman = $this->deliveryman->find($request->id);
        if (Storage::disk('public')->exists('delivery-man/' . $deliveryman['image'])) {
            Storage::disk('public')->delete('delivery-man/' . $deliveryman['image']);
        }

        foreach (json_decode($deliveryman['identity_image'], true) as $img) {
            if (Storage::disk('public')->exists('delivery-man/' . $img)) {
                Storage::disk('public')->delete('delivery-man/' . $img);
            }
        }

        $deliveryman->delete();

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                Mail::to($deliveryman->email)->send(new \App\Mail\Deliveryman\DMDelete($deliveryman));
            }
        } catch (\Exception $e) {
        }

        Toastr::success(translate('Delivery man removed!'));
        return back();
    }

    /**
     * @return StreamedResponse|string
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function export(Request $request): StreamedResponse|string
    {

        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $deliverymanList = $this->deliveryman->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }else{
            $deliverymanList = $this->deliveryman;
        }
        $deliverymanList = $deliverymanList->latest()->where('application_status', 'approved')->get();

        $storage = [];

        foreach($deliverymanList as $deliveryman){

            if ($deliveryman['branch_id'] == 0){
                $branch = 'All Branch';
            }else{
                $branch = $deliveryman->branch ? $deliveryman->branch->name : '';
            }

            $storage[] = [
                'first_name' => $deliveryman['f_name'],
                'last_name' => $deliveryman['l_name'],
                'phone' => $deliveryman['phone'],
                'email' => $deliveryman['email'],
                'identity_type' => $deliveryman['identity_type'],
                'identity_number' => $deliveryman['identity_number'],
                'branch' => $branch,
            ];
        }
        return (new FastExcel($storage))->download('delivery-man.xlsx');
    }

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function pendingList(Request $request): Factory|View|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $deliverymen = $this->deliveryman->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }else{
            $deliverymen = $this->deliveryman;
        }

        $deliverymen = $deliverymen->with('branch')
            ->where('application_status', 'pending')
            ->latest()->paginate(Helpers::getPagination())
            ->appends($queryParam);

        return view('admin-views.delivery-man.pending-list', compact('deliverymen','search'));
    }

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function deniedList(Request $request): Factory|View|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $deliverymen = $this->deliveryman->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }else{
            $deliverymen = $this->deliveryman;
        }

        $deliverymen = $deliverymen->with('branch')
            ->where('application_status', 'denied')
            ->latest()
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        return view('admin-views.delivery-man.denied-list', compact('deliverymen','search'));
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateApplicationStatus(Request $request): RedirectResponse
    {
        $deliveryman = $this->deliveryman->findOrFail($request->id);
        $deliveryman->application_status = $request->status;
        if($request->status == 'approved') $deliveryman->is_active = 1;
        $deliveryman->save();

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                Mail::to($deliveryman->email)->send(new \App\Mail\Deliveryman\DMApprovedDenied($deliveryman));
            }
        } catch (\Exception $e) {
        }

        Toastr::success(translate('application_status_updated_successfully'));
        return back();
    }
}
