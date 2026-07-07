<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Conversation;
use App\Model\CustomerAddress;
use App\Model\Newsletter;
use App\Model\Order;
use App\Models\GuestUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Illuminate\Http\JsonResponse;


class CustomerController extends Controller
{
    public function __construct(
        private Conversation $conversation,
        private CustomerAddress $customerAddress,
        private Newsletter $newsletter,
        private Order $order,
        private User $user,
        private GuestUser $guestUser
    ){}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addressList(Request $request): JsonResponse
    {
        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        return response()->json($this->customerAddress->where(['user_id' => $userId, 'is_guest' => $userType])->latest()->get(), 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addNewAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (auth('api')->user() || $request->header('guest-id')){
            $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
            $userType = (bool)auth('api')->user() ? 0 : 1;

            $address = [
                'user_id' => $userId,
                'is_guest' => $userType,
                'contact_person_name' => $request->contact_person_name,
                'contact_person_number' => $request->contact_person_number,
                'address_type' => $request->address_type,
                'address' => $request->address,
                'road' => $request->road,
                'house' => $request->house,
                'floor' => $request->floor,
                'longitude' => $request->longitude,
                'latitude' => $request->latitude,
                'created_at' => now(),
                'updated_at' => now()
            ];
            DB::table('customer_addresses')->insert($address);
            return response()->json(['message' => 'successfully added!'], 200);
        }
        return response()->json(['message' => 'no user data found!'], 403);

    }

    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function updateAddress(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        $address = [
            'user_id' => $userId,
            'is_guest' => $userType,
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'road' => $request->road,
            'house' => $request->house,
            'floor' => $request->floor,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => now(),
            'updated_at' => now()
        ];
        DB::table('customer_addresses')->where('id',$id)->update($address);
        return response()->json(['message' => 'successfully updated!'], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        if (DB::table('customer_addresses')->where(['id' => $request['address_id'], 'user_id' => $userId, 'is_guest' => $userType])->first()) {
            DB::table('customer_addresses')->where(['id' => $request['address_id'], 'user_id' => $userId, 'is_guest' => $userType])->delete();
            return response()->json(['message' => 'successfully removed!'], 200);
        }
        return response()->json(['message' => 'No such data found!'], 404);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function info(Request $request): JsonResponse
    {
       return response()->json($request->user(), 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'l_name' => 'required',
            'phone' => ['required', 'unique:users,phone,'.auth()->user()->id]
        ], [
            'f_name.required' => 'First name is required!',
            'l_name.required' => 'Last name is required!',
            'phone.required' => 'Phone is required!',
            'phone.unique' => translate('Phone must be unique!'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image = $request->file('image');

        if ($image != null) {
            $data = getimagesize($image);
            $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . 'png';
            if (!Storage::disk('public')->exists('profile')) {
                Storage::disk('public')->makeDirectory('profile');
            }
            $noteImage = Image::make($image)->fit($data[0], $data[1])->stream();
            Storage::disk('public')->put('profile/' . $imageName, $noteImage);
        } else {
            $imageName = $request->user()->image;
        }

        if ($request['password'] != null && strlen($request['password']) > 5) {
            $password = bcrypt($request['password']);
        } else {
            $password = $request->user()->password;
        }

        $userDetails = [
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'phone' => $request->phone,
            'image' => $imageName,
            'password' => $password,
            'updated_at' => now()
        ];

        $this->user->where(['id' => $request->user()->id])->update($userDetails);

        return response()->json(['message' => 'successfully updated!'], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateFirebaseToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cm_firebase_token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        DB::table('users')->where('id',$request->user()->id)->update([
            'cm_firebase_token'=>$request['cm_firebase_token']
        ]);

        return response()->json(['message' => 'successfully updated!'], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function subscribeNewsletter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $newsLetter = $this->newsletter->where('email', $request->email)->first();
        if (!isset($newsLetter)) {
            $newsLetter = $this->newsletter;
            $newsLetter->email = $request->email;
            $newsLetter->save();

            try {
                $emailServices = Helpers::get_business_settings('mail_config');
                if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                    Mail::to(Helpers::get_business_settings('email_address'))->send(new \App\Mail\Admin\SubscribeNewsletter($newsLetter->email));
                }
            } catch (\Exception $e) {
            }

            return response()->json(['message' => 'Successfully subscribed'], 200);

        } else {
            return response()->json(['message' => 'Email Already exists'], 400);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeAccount(Request $request): JsonResponse
    {
        $customer = $this->user->find($request->user()->id);

        if (!$customer) {
            return response()->json(['errors' => [['code' => 404, 'message' => translate('Not found')]]], 403);
        }

        $runningOrdersCount = $this->order
            ->where(['user_id' => $request->user()->id, 'is_guest' => 0])
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->count();

        if ($runningOrdersCount > 0){
            return response()->json(['errors' => [['code' => 'customer', 'message' => translate("You have {$runningOrdersCount} running order. Please complete the running order first")]]], 403);
        }

        Helpers::file_remover('profile/', $customer->image);

        $customerDeleted = $customer->delete();

        if ($customerDeleted) {
            $pendingOrders = $this->order
                ->where(['user_id' => $request->user()->id, 'is_guest' => 0])
                ->where(['order_status' => 'pending'])
                ->get();

            if ($pendingOrders->isNotEmpty()) {
                foreach ($pendingOrders as $order) {
                    $order->order_status = 'canceled';
                    $order->save();
                }
            }
        }

        $conversations = $this->conversation->where('user_id', $customer->id)->get();
        foreach ($conversations as $conversation){
            if ($conversation->checked == 0){
                $conversation->checked = 1;
                $conversation->save();
            }
        }

        return response()->json(['status_code' => 200, 'message' => translate('Successfully deleted')], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeLanguage(Request $request): JsonResponse
    {
        if (auth('api')->user()){
            $customer = $this->user->find(auth('api')->user()->id);
            $customer->language_code = $request->language_code ?? 'en';
            $customer->save();
        }else{
            $guest = $this->guestUser::find($request->header('guest-id'));
            if (!isset($guest)) {
                $guest = $this->guestUser;
                $guest->ip_address = $request->ip();
                $guest->fcm_token = $request->fcm_token ?? null;
            }
            $guest->language_code = $request->language_code ?? 'en';
            $guest->save();
        }
        return response()->json(200);
    }

    /**
     * @return JsonResponse
     */
    public function lastOrderedAddress(): JsonResponse
    {
        if (!auth('api')->user()){
            return response()->json(['status_code' => 401, 'message' => translate('Unauthorized')], 200);
        }

        $userId = auth('api')->user()->id;

        $order = $this->order->where(['user_id' => $userId, 'is_guest' => 0])
            ->whereNotNull('delivery_address_id')
            ->orderBy('id', 'DESC')
            ->with('delivery_address')
            ->first();

        if (isset($order) && $order->delivery_address){
            return response()->json($order->delivery_address, 200);
        }

        return response()->json(null, 200);

    }
}
