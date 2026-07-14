<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\InternshipLogic;
use App\Http\Controllers\Controller;
use App\Model\Internship\Internship;
use App\Model\Internship\InternshipApplication;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternshipController extends Controller
{
    public function __construct(
        private Internship $internship
    ) {}

    public function list(Request $request): View|Factory|Application
    {
        $search = $request->get('search');
        $queryParam = [];

        $query = $this->internship->withCount('applications')->orderBy('sort_order')->latest('id');
        if ($request->filled('search') && $search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('role', 'like', "%{$value}%")
                        ->orWhere('slug', 'like', "%{$value}%")
                        ->orWhere('team', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $search];
        }

        $internships = $query->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.internship.list', compact('internships', 'search'));
    }

    public function add(): View|Factory|Application
    {
        return view('admin-views.internship.form', ['internship' => null]);
    }

    public function edit($id): View|Factory|Application
    {
        $internship = $this->internship->findOrFail($id);
        return view('admin-views.internship.form', compact('internship'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'role' => 'required|max:255',
            'slug' => 'nullable|max:120|unique:internships,slug',
            'team' => 'nullable|max:255',
            'location' => 'nullable|max:255',
            'type' => 'nullable|max:32',
            'duration' => 'nullable|max:255',
            'stipend' => 'nullable|max:255',
            'blurb' => 'nullable',
            'skills' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $internship = new Internship();
        $internship->role = $request->role;
        $internship->slug = $request->filled('slug')
            ? $request->slug
            : InternshipLogic::uniqueSlug($request->role);
        $internship->team = $request->team;
        $internship->location = $request->location;
        $internship->type = $request->type ?: 'Remote';
        $internship->duration = $request->duration;
        $internship->stipend = $request->stipend;
        $internship->blurb = $request->blurb;
        $internship->skills = InternshipLogic::parseSkills($request->skills);
        $internship->status = $request->status ?: 'active';
        $internship->is_published = (bool) $request->is_published;
        $internship->sort_order = (int) ($request->sort_order ?? 0);
        $internship->save();

        Toastr::success(translate('Internship added successfully!'));
        return redirect()->route('admin.internship.list');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $internship = $this->internship->findOrFail($id);

        $request->validate([
            'role' => 'required|max:255',
            'slug' => 'nullable|max:120|unique:internships,slug,' . $id,
            'team' => 'nullable|max:255',
            'location' => 'nullable|max:255',
            'type' => 'nullable|max:32',
            'duration' => 'nullable|max:255',
            'stipend' => 'nullable|max:255',
            'blurb' => 'nullable',
            'skills' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $internship->role = $request->role;
        $internship->slug = $request->filled('slug')
            ? $request->slug
            : InternshipLogic::uniqueSlug($request->role, $internship->id);
        $internship->team = $request->team;
        $internship->location = $request->location;
        $internship->type = $request->type ?: 'Remote';
        $internship->duration = $request->duration;
        $internship->stipend = $request->stipend;
        $internship->blurb = $request->blurb;
        $internship->skills = InternshipLogic::parseSkills($request->skills);
        $internship->status = $request->status ?: 'active';
        $internship->is_published = (bool) $request->is_published;
        $internship->sort_order = (int) ($request->sort_order ?? 0);
        $internship->save();

        Toastr::success(translate('Internship updated successfully!'));
        return redirect()->route('admin.internship.list');
    }

    public function status(Request $request): RedirectResponse
    {
        $internship = $this->internship->find($request->id);
        if (!$internship) {
            Toastr::error(translate('Internship not found'));
            return back();
        }

        $internship->status = $request->status === 'active' ? 'active' : 'paused';
        $internship->save();

        Toastr::success(translate('Internship status updated!'));
        return back();
    }

    public function publish(Request $request): RedirectResponse
    {
        $internship = $this->internship->find($request->id);
        if (!$internship) {
            Toastr::error(translate('Internship not found'));
            return back();
        }

        $internship->is_published = (bool) $request->is_published;
        $internship->save();

        Toastr::success(translate('Internship publish status updated!'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $internship = $this->internship->find($request->id);
        if (!$internship) {
            Toastr::error(translate('Internship not found'));
            return back();
        }

        $internship->applications()->update(['internship_id' => null]);
        $internship->delete();

        Toastr::success(translate('Internship removed!'));
        return back();
    }

    public function applications(Request $request): View|Factory|Application
    {
        $search = $request->get('search');
        $internshipId = $request->get('internship_id');
        $queryParam = [];

        $query = InternshipApplication::with('internship')->latest();
        if ($internshipId) {
            $query->where('internship_id', $internshipId);
            $queryParam['internship_id'] = $internshipId;
        }
        if ($request->filled('search') && $search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('role', 'like', "%{$value}%")
                        ->orWhere('application_id', 'like', "%{$value}%");
                }
            });
            $queryParam['search'] = $search;
        }

        $applications = $query->paginate(Helpers::getPagination())->appends($queryParam);
        $internships = $this->internship->orderBy('role')->get(['id', 'role']);

        return view('admin-views.internship.applications', compact('applications', 'search', 'internshipId', 'internships'));
    }
}
