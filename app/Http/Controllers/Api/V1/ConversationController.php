<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Model\Admin;
use App\Model\BusinessSetting;
use App\Model\Conversation;
use App\Model\DcConversation;
use App\Model\DeliveryMan;
use App\Model\Message;
use App\Model\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    public function __construct(
        private Admin $admin,
        private Conversation $conversation,
        private DcConversation $deliverymanConversation,
        private DeliveryMan $deliveryman,
        private Message $message,
        private Order $order
    ){}

    /**
     * @param Request $request
     * @return array
     */
    public function getAdminMessage(Request $request): array
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $offset = $request->has('offset') ? $request->offset : 1;
        $messages = $this->conversation->where(['user_id' => $request->user()->id])->latest()->paginate($limit, ['*'], 'page', $offset);
        $messages = ConversationResource::collection($messages);
        return [
            'total_size' => $messages->total(),
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'messages' => $messages->items()
        ];
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function storeAdminMessage(Request $request): JsonResponse
    {
        if ($request->message == null && $request->image == null) {
            return response()->json(['message' => 'Message can not be empty'], 403);
        }

        try {
            $imageNames = [];
            if (!empty($request->file('image'))) {
                foreach ($request->image as $img) {
                    $image = Helpers::upload('conversation/', 'png', $img);
                    $imageUrl = asset('storage/app/public/conversation') . '/' . $image;
                    $imageNames[] = $imageUrl;
                }
                $images = $imageNames;
            } else {
                $images = null;
            }
            $conversation = $this->conversation;
            $conversation->user_id = $request->user()->id;
            $conversation->message = $request->message;
            $conversation->image = json_encode($images);
            $conversation->save();

            $admin = $this->admin->first();
            $data = [
                'title' => $request->user()->f_name . ' ' . $request->user()->l_name . translate(' send a message'),
                'description' => $request->user()->id,
                'order_id' => '',
                'image' => asset('storage/app/public/restaurant') . '/' . BusinessSetting::where(['key' => 'logo'])->first()->value,
                'type' => 'order'
            ];
            try {
                Helpers::send_push_notif_to_device($admin->fcm_token, $data);
            } catch (\Exception $exception) {

            }

            return response()->json(['message' => 'Successfully sent!'], 200);

        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }
    }

    /**
     * @param Request $request
     * @return array|JsonResponse|int[]
     */
    public function getMessageByOrder(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $limit = $request->has('limit') ? $request->limit : 10;
        $offset = $request->has('offset') ? $request->offset : 1;

        $conversations = $this->deliverymanConversation->where('order_id', $request->order_id)->first();
        if (!isset($conversations)) {
            return [
                'total_size' => 0,
                'limit' => (int)$limit,
                'offset' => (int)$offset,
                'messages' => []
            ];
        }
        $conversations = $conversations->setRelation('messages', $conversations->messages()
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset));

        $message = MessageResource::collection($conversations->messages);

        return [
            'total_size' => $message->total(),
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'messages' => $message->items()
        ];
    }

    /**
     * @param Request $request
     * @param $sender_type
     * @return JsonResponse
     */
    public function storeMessageByOrder(Request $request, $sender_type): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $senderId = null;
        $order = $this->order->with('delivery_man')->with('customer')->find($request->order_id);

        if($sender_type == 'deliveryman') {
            $validator = Validator::make($request->all(), [
                'token' => 'required'
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => Helpers::error_processor($validator)], 403);
            }

            $deliveryman = $this->deliveryman->where('auth_token', $request->token)->first();

            if (isset($deliveryman) && $deliveryman->id != $order->delivery_man->id) {
                return response()->json(['errors' => 'Unauthorized'], 401);
            }

            $senderId = $order->delivery_man->id;
        }
        elseif($sender_type == 'customer') {
            $senderId = $order->customer->id;
        }

        if($request->message == null && $request->image == null) {
            return response()->json(['message' => 'Message can not be empty'], 400);
        }

        $imageNames = [];
        if (!empty($request->file('image'))) {
            foreach ($request->image as $img) {
                $image = Helpers::upload('conversation/', 'png', $img);
                $imageUrl = asset('storage/app/public/conversation') . '/' . $image;
                $imageNames[] = $imageUrl;
            }
            $images = $imageNames;
        } else {
            $images = null;
        }

        if($request->order_id != null) {
            DB::transaction(function () use ($request, $sender_type, $images, $senderId) {
                $deliverymanConversation = $this->deliverymanConversation->where('order_id', $request->order_id)->first();
                if (!isset($deliverymanConversation)) {
                    $deliverymanConversation = new DcConversation();
                    $deliverymanConversation->order_id = $request->order_id;
                    $deliverymanConversation->save();
                }

                $message = $this->message;
                $message->conversation_id = $deliverymanConversation->id;
                $message->customer_id = ($sender_type == 'customer') ? $senderId : null;
                $message->deliveryman_id = ($sender_type == 'deliveryman') ? $senderId : null;
                $message->message = $request->message ?? null;
                $message->attachment = json_encode($images);
                $message->save();
            });
        }

        if($sender_type == 'customer') {
            $receiver_fcm_token = $order->delivery_man->fcm_token ?? null;

        } elseif($sender_type == 'deliveryman') {
            $receiver_fcm_token = $order->customer->cm_firebase_token ?? null;
        }
        $data = [
            'title' => 'New message arrived',
            'description' => $request->reply,
            'order_id' => $request->order_id ?? null,
            'image' => '',
            'type' => 'message'
        ];
        try {
            Helpers::send_push_notif_to_device($receiver_fcm_token, $data);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Push notification send failed'], 200);
        }
        return response()->json(['message' => 'Message successfully sent'], 200);
    }

    /**
     * @param Request $request
     * @return array|JsonResponse|int[]
     */
    public function getOrderMessageForDeliveryman(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $deliveryMan = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if(!isset($deliveryMan)) {
            return response()->json(['errors' => 'Unauthenticated.'], 401);
        }

        $limit = $request->has('limit') ? $request->limit : 10;
        $offset = $request->has('offset') ? $request->offset : 1;

        $conversations = $this->deliverymanConversation->where('order_id', $request->order_id)->first();
        if (!isset($conversations)) {
            return [ 'total_size' => 0, 'limit' => (int)$limit, 'offset' => (int)$offset, 'messages' => [] ];
        }
        $conversations = $conversations->setRelation('messages', $conversations->messages()->latest()->paginate($limit, ['*'], 'page', $offset));
        $message = MessageResource::collection($conversations->messages);

        return [
            'total_size' => $message->total(),
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'messages' => $message->items()
        ];
    }
}
