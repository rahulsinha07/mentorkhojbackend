<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MentorController extends Controller
{
    public function __construct(
        private Mentor $mentor
    ) {}

    public function list(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request->get('search');

        $query = $this->mentor->withCount(['services', 'bookings'])->latest();
        if ($request->has('search') && $search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('display_name', 'like', "%{$value}%")
                        ->orWhere('username', 'like', "%{$value}%")
                        ->orWhere('headline', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $search];
        }

        $mentors = $query->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.mentor.list', compact('mentors', 'search'));
    }

    public function status(Request $request): RedirectResponse
    {
        $mentor = $this->mentor->find($request->id);
        if (!$mentor) {
            Toastr::error(translate('mentor not found'));
            return back();
        }

        $mentor->status = $request->status ? 'active' : 'draft';
        $mentor->save();

        Toastr::success(translate('mentor status updated!'));
        return back();
    }

    public function publish(Request $request): RedirectResponse
    {
        $mentor = $this->mentor->find($request->id);
        if (!$mentor) {
            Toastr::error(translate('mentor not found'));
            return back();
        }

        $mentor->is_published = (bool) $request->is_published;
        $mentor->save();

        Toastr::success(translate('mentor publish status updated!'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $mentor = $this->mentor->find($request->id);
        if (!$mentor) {
            Toastr::error(translate('mentor not found'));
            return back();
        }

        foreach ($mentor->images_array as $image) {
            if ($image && Storage::disk('public')->exists('product/' . $image)) {
                Storage::disk('public')->delete('product/' . $image);
            }
        }

        $mentor->services()->delete();
        $mentor->bookings()->delete();
        $mentor->earnings()->delete();
        $mentor->payouts()->delete();
        $mentor->settings()->delete();
        $mentor->shareLogs()->delete();
        $mentor->delete();

        Toastr::success(translate('mentor removed!'));
        return back();
    }
}
