<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorKhojRevalidateLogic;
use App\CentralLogics\SeminarLogic;
use App\Http\Controllers\Controller;
use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarRegistration;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeminarController extends Controller
{
    public function __construct(
        private Seminar $seminar
    ) {}

    public function list(Request $request): View|Factory|Application
    {
        $search = $request->get('search');
        $queryParam = [];

        $query = $this->seminar->withCount('registrations')->orderBy('sort_order')->latest('id');
        if ($request->filled('search') && $search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('title', 'like', "%{$value}%")
                        ->orWhere('slug', 'like', "%{$value}%")
                        ->orWhere('tagline', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $search];
        }

        $seminars = $query->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.seminar.list', compact('seminars', 'search'));
    }

    public function add(): View|Factory|Application
    {
        return view('admin-views.seminar.form', ['seminar' => null]);
    }

    public function edit($id): View|Factory|Application
    {
        $seminar = $this->seminar->findOrFail($id);
        return view('admin-views.seminar.form', compact('seminar'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'slug' => 'nullable|max:120|unique:seminars,slug',
            'tagline' => 'nullable|max:255',
            'blurb' => 'nullable',
            'date' => 'nullable|max:255',
            'mode' => 'nullable|max:32',
            'duration' => 'nullable|max:255',
            'audience' => 'nullable|max:255',
            'emoji' => 'nullable|max:16',
            'highlights' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'fee_amount' => 'nullable|numeric|min:0|max:999999.99',
            'currency' => 'nullable|string|size:3',
        ]);

        $seminar = new Seminar();
        $seminar->title = $request->title;
        $seminar->slug = $request->filled('slug')
            ? $request->slug
            : SeminarLogic::uniqueSlug($request->title);
        $seminar->tagline = $request->tagline;
        $seminar->blurb = $request->blurb;
        $seminar->date = $request->date;
        $seminar->mode = $request->mode ?: 'Online';
        $seminar->duration = $request->duration;
        $seminar->audience = $request->audience;
        $seminar->emoji = $request->emoji;
        $seminar->highlights = SeminarLogic::parseHighlights($request->highlights);
        $seminar->status = $request->status ?: 'active';
        $seminar->is_published = (bool) $request->is_published;
        $seminar->sort_order = (int) ($request->sort_order ?? 0);
        $seminar->fee_amount = (float) ($request->fee_amount ?? 0);
        $seminar->currency = strtoupper($request->currency ?? 'INR');
        $seminar->save();

        MentorKhojRevalidateLogic::revalidateSeminar($seminar->slug);

        Toastr::success(translate('Seminar added successfully!'));
        return redirect()->route('admin.seminar.list');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $seminar = $this->seminar->findOrFail($id);

        $request->validate([
            'title' => 'required|max:255',
            'slug' => 'nullable|max:120|unique:seminars,slug,' . $id,
            'tagline' => 'nullable|max:255',
            'blurb' => 'nullable',
            'date' => 'nullable|max:255',
            'mode' => 'nullable|max:32',
            'duration' => 'nullable|max:255',
            'audience' => 'nullable|max:255',
            'emoji' => 'nullable|max:16',
            'highlights' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'fee_amount' => 'nullable|numeric|min:0|max:999999.99',
            'currency' => 'nullable|string|size:3',
        ]);

        $seminar->title = $request->title;
        $seminar->slug = $request->filled('slug')
            ? $request->slug
            : SeminarLogic::uniqueSlug($request->title, $seminar->id);
        $seminar->tagline = $request->tagline;
        $seminar->blurb = $request->blurb;
        $seminar->date = $request->date;
        $seminar->mode = $request->mode ?: 'Online';
        $seminar->duration = $request->duration;
        $seminar->audience = $request->audience;
        $seminar->emoji = $request->emoji;
        $seminar->highlights = SeminarLogic::parseHighlights($request->highlights);
        $seminar->status = $request->status ?: 'active';
        $seminar->is_published = (bool) $request->is_published;
        $seminar->sort_order = (int) ($request->sort_order ?? 0);
        $seminar->fee_amount = (float) ($request->fee_amount ?? 0);
        $seminar->currency = strtoupper($request->currency ?? 'INR');
        $seminar->save();

        MentorKhojRevalidateLogic::revalidateSeminar($seminar->slug);

        Toastr::success(translate('Seminar updated successfully!'));
        return redirect()->route('admin.seminar.list');
    }

    public function status(Request $request): RedirectResponse
    {
        $seminar = $this->seminar->find($request->id);
        if (!$seminar) {
            Toastr::error(translate('Seminar not found'));
            return back();
        }

        $seminar->status = $request->status === 'active' ? 'active' : 'paused';
        $seminar->save();

        MentorKhojRevalidateLogic::revalidateSeminar($seminar->slug);

        Toastr::success(translate('Seminar status updated!'));
        return back();
    }

    public function publish(Request $request): RedirectResponse
    {
        $seminar = $this->seminar->find($request->id);
        if (!$seminar) {
            Toastr::error(translate('Seminar not found'));
            return back();
        }

        $seminar->is_published = (bool) $request->is_published;
        $seminar->save();

        MentorKhojRevalidateLogic::revalidateSeminar($seminar->slug);

        Toastr::success(translate('Seminar publish status updated!'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $seminar = $this->seminar->find($request->id);
        if (!$seminar) {
            Toastr::error(translate('Seminar not found'));
            return back();
        }

        $slug = $seminar->slug;
        $seminar->registrations()->delete();
        $seminar->delete();

        MentorKhojRevalidateLogic::revalidateSeminar($slug);

        Toastr::success(translate('Seminar removed!'));
        return back();
    }

    public function registrations(Request $request): View|Factory|Application
    {
        $search = $request->get('search');
        $seminarId = $request->get('seminar_id');
        $queryParam = [];

        $query = SeminarRegistration::with('seminar')->latest();
        if ($seminarId) {
            $query->where('seminar_id', $seminarId);
            $queryParam['seminar_id'] = $seminarId;
        }
        if ($request->filled('search') && $search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('registration_id', 'like', "%{$value}%");
                }
            });
            $queryParam['search'] = $search;
        }

        $registrations = $query->paginate(Helpers::getPagination())->appends($queryParam);
        $seminars = $this->seminar->orderBy('title')->get(['id', 'title']);

        return view('admin-views.seminar.registrations', compact('registrations', 'search', 'seminarId', 'seminars'));
    }
}
