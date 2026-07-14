<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\AccountTypeLogic;
use App\CentralLogics\CustomerBookingStats;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Conversation;
use App\Model\Newsletter;
use App\Model\Order;
use App\User;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        private User $user,
        private Order $order,
        private Newsletter $newsletter,
        private Conversation $conversation
    ){}

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function list(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        $baseQuery = CustomerBookingStats::applyListAggregates(
            $this->user->with(['mentorProfile'])
        );

        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $customers = $baseQuery->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('f_name', 'like', "%{$value}%")
                                ->orWhere('l_name', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%");
                        }
            });
            $queryParam = ['search' => $request['search']];
        }else{
            $customers = $baseQuery;
        }
        $customers = $customers->latest()->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.customer.list', compact('customers','search'));
    }

    /**
     * @param Request $request
     * @param $id
     * @return View|Factory|RedirectResponse|Application
     */
    public function view(Request $request, $id): Factory|View|Application|RedirectResponse
    {
        $customer = $this->user->find($id);
        if (isset($customer)) {
            $queryParam = [];
            $search = $request['search'];
            if($request->has('search'))
            {
                $key = explode(' ', $request['search']);
                $orders = $this->order->where(['user_id' => $id])
                    ->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('id', 'like', "%{$value}%")
                                ->orWhere('order_amount', 'like', "%{$value}%");
                        }
                });
                $queryParam = ['search' => $request['search']];
            }else{
                $orders = $this->order->where(['user_id' => $id]);
            }
            $orders = $orders->latest()->paginate(Helpers::getPagination())->appends($queryParam);
            $customer->load('mentorProfile');
            $bookingStats = CustomerBookingStats::forUser((int) $id);

            return view('admin-views.customer.customer-view', compact('customer', 'orders', 'search', 'bookingStats'));
        }
        Toastr::error(translate('Customer not found!'));
        return back();
    }

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function subscribedEmails(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $newsletters = $this->newsletter->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        } else {
            $newsletters = $this->newsletter;
        }

        $newsletters = $newsletters->latest()->paginate(Helpers::getPagination())->appends($queryParam);
        return view('admin-views.customer.subscribed-list', compact('newsletters', 'search'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): RedirectResponse
    {
        $customer = $this->user->find($request->id);

        if (!$customer) {
            Toastr::error(translate('Customer not found!'));
            return back();
        }

        $runningOrdersCount = $this->order
            ->where(['user_id' => $request->id, 'is_guest' => 0])
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->count();

        if ($runningOrdersCount > 0){
            Toastr::error(translate("This customer have {$runningOrdersCount} running order. Please complete the running order first"));
            return back();
        }

        if (Storage::disk('public')->exists('customer/' . $customer['image'])) {
            Storage::disk('public')->delete('customer/' . $customer['image']);
        }

        $conversations = $this->conversation->where('user_id', $request->id)->get();
        foreach ($conversations as $conversation){
            if ($conversation->checked == 0){
                $conversation->checked = 1;
                $conversation->save();
            }
        }

        $customerDeleted = $customer->delete();

        if ($customerDeleted) {
            $pendingOrders = $this->order
                ->where(['user_id' => $request->id, 'is_guest' => 0])
                ->where(['order_status' => 'pending'])
                ->get();

            if ($pendingOrders->isNotEmpty()) {
                foreach ($pendingOrders as $order) {
                    $order->order_status = 'canceled';
                    $order->save();
                }
            }
        }

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1 && isset($customer['email'])) {
                $name = $customer->f_name. ' '. $customer->l_name;
                Mail::to($customer->email)->send(new \App\Mail\Customer\CustomerDelete($name));
            }
        } catch (\Exception $e) {
        }

        Toastr::success(translate('Customer removed!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $user = $this->user->find($request->id);
        $user->is_block = $request->status;
        $user->save();

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1 && isset($user['email'])) {
                Mail::to($user->email)->send(new \App\Mail\Customer\CustomerChangeStatus($user));
            }
        } catch (\Exception $e) {
        }

        Toastr::success(translate('Block status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());
            return back();
        }

        $customer = $this->user->find($id);
        if (!$customer) {
            Toastr::error(translate('Customer not found!'));
            return back();
        }

        $customer->password = bcrypt($request->password);
        $customer->save();

        if ($request->boolean('notify_customer') && $customer->email) {
            try {
                $emailServices = Helpers::get_business_settings('mail_config');
                if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                    $name = trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? ''));
                    Mail::to($customer->email)->send(new \App\Mail\Customer\AdminPasswordReset($name, $request->password));
                }
            } catch (\Exception $e) {
            }
        }

        Toastr::success(translate('Password updated successfully'));
        return back();
    }

    /**
     * @param Request $request
     * @return StreamedResponse|string
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportCustomer(Request $request): StreamedResponse|string
    {
        $storage = [];
        $queryParam = [];
        $search = $request['search'];

        $customers = CustomerBookingStats::applyListAggregates(
            $this->user->with('mentorProfile')
        )->when($request->has('search'), function ($query) use ($request) {
                $key = explode(' ', $request['search']);
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    }
                });
            })
            ->get();

        foreach($customers as $customer){

            $storage[] = [
                'first_name' => $customer['f_name'],
                'last_name' => $customer['l_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'account_type' => AccountTypeLogic::accountTypeLabel($customer->account_type ?? null),
                'mentor_username' => $customer->mentorProfile?->username,
                'last_login_portal' => AccountTypeLogic::loginPortalLabel($customer->last_login_as ?? null),
                'last_login_at' => $customer->last_login_at?->format('Y-m-d H:i'),
                'login_method' => AccountTypeLogic::loginMediumLabel($customer->login_medium ?? null),
                'total_bookings' => (int) ($customer->bookings_count ?? 0),
                'total_booking_amount' => (float) ($customer->bookings_amount ?? 0),
            ];
        }
        return (new FastExcel($storage))->download('customers.xlsx');
    }
}
