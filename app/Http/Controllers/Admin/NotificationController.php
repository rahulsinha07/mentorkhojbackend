<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\UserNotificationLogic;
use App\Http\Controllers\Controller;
use App\Model\Notification;
use App\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    public function __construct(
        private Notification $notification
    ){}

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    function index(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $notifications = $this->notification->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('title', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                        }
            });
            $queryParam = ['search' => $request['search']];
        }else{
           $notifications = $this->notification;
        }
        $notifications = $notifications->latest()->paginate(Helpers::getPagination())->appends($queryParam);
        $audience = $request->query('audience') === 'mentor' ? 'mentor' : 'mentee';
        return view('admin-views.notification.index', compact('notifications','search','audience'));
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'description' => 'required|max:255',
            'image'=> 'required',
            'audience' => 'required|in:mentor,mentee',
        ], [
            'title.required' => 'title is required!',
        ]);

        if ($request->has('image')) {
            $imageName = Helpers::upload('notification/', 'png', $request->file('image'));
        } else {
            $imageName = null;
        }

        $audience = $request->audience;

        $notification = $this->notification;
        $notification->title = $request->title;
        $notification->description = $request->description;
        $notification->image = $imageName;
        $notification->audience = $audience;
        $notification->status = 1;
        $notification->save();

        $sent = 0;
        $failed = 0;

        User::query()
            ->when($audience === 'mentor',
                fn ($q) => $q->where('account_type', 'mentor'),
                fn ($q) => $q->where(function ($q2) {
                    $q2->where('account_type', 'mentee')->orWhereNull('account_type');
                })
            )
            ->select(['id'])
            ->chunkById(200, function ($users) use (&$sent, &$failed, $notification) {
                foreach ($users as $user) {
                    try {
                        UserNotificationLogic::create(
                            (int) $user->id,
                            'admin_broadcast',
                            $notification->title,
                            $notification->description,
                            null,
                            null,
                            ['image' => $notification->imageFullPath]
                        );
                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Admin broadcast notification failed for user '.$user->id.': '.$e->getMessage());
                    }
                }
            });

        if ($failed === 0) {
            Toastr::success(translate('Notification sent to').' '.$sent.' '.translate($audience === 'mentor' ? 'mentors' : 'students').'!');
        } else {
            Toastr::warning(translate('Notification sent to').' '.$sent.' '.translate($audience === 'mentor' ? 'mentors' : 'students').', '.$failed.' '.translate('failed'));
        }

        return back();
    }

    /**
     * @param $id
     * @return Factory|View|Application
     */
    public function edit($id): View|Factory|Application
    {
        $notification = $this->notification->find($id);
        return view('admin-views.notification.edit', compact('notification'));
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'description' => 'required|max:255',
        ], [
            'title.required' => 'title is required!',
        ]);

        $notification = $this->notification->find($id);

        if ($request->has('image')) {
            $imageName = Helpers::update('notification/', $notification->image, 'png', $request->file('image'));
        } else {
            $imageName = $notification['image'];
        }

        $notification->title = $request->title;
        $notification->description = $request->description;
        $notification->image = $imageName;
        $notification->save();
        Toastr::success(translate('Notification updated successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): \Illuminate\Http\RedirectResponse
    {
        $notification = $this->notification->find($request->id);
        $notification->status = $request->status;
        $notification->save();
        Toastr::success(translate('Notification status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $notification = $this->notification->find($request->id);
        if (Storage::disk('public')->exists('notification/' . $notification['image'])) {
            Storage::disk('public')->delete('notification/' . $notification['image']);
        }
        $notification->delete();
        Toastr::success(translate('Notification removed!'));
        return back();
    }
}
