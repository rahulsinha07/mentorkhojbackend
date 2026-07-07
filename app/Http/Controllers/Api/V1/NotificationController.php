<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Model\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private Notification $notification
    ){}

    /**
     * @return JsonResponse
     */
    public function getNotifications(): JsonResponse
    {
        return response()->json($this->notification->active()->get(), 200);
    }
}
