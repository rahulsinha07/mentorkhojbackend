<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\NeetCategoryCutoff;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NeetCategoryCutoffController extends Controller
{
    public function index(): View|Factory|Application
    {
        $cutoffs = NeetCategoryCutoff::query()
            ->orderByDesc('year')
            ->orderBy('cutoff_marks_low')
            ->get();

        return view('admin-views.neet-category-cutoff.index', compact('cutoffs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'category' => 'required|in:general,ews,obc_ncl,sc,st',
            'percentile' => 'required|integer|min:1|max:100',
            'cutoff_marks_low' => 'required|integer|min:0|max:720',
            'cutoff_marks_high' => 'required|integer|min:0|max:720',
            'source_note' => 'nullable|max:255',
        ]);

        $exists = NeetCategoryCutoff::where('year', $validated['year'])
            ->where('category', $validated['category'])
            ->exists();

        if ($exists) {
            Toastr::warning(translate('A cutoff for this year and category already exists. Edit it instead.'));
            return back();
        }

        NeetCategoryCutoff::create($validated);

        Toastr::success(translate('Cutoff added!'));
        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $cutoff = NeetCategoryCutoff::findOrFail($id);

        $validated = $request->validate([
            'cutoff_marks_low' => 'required|integer|min:0|max:720',
            'cutoff_marks_high' => 'required|integer|min:0|max:720',
            'source_note' => 'nullable|max:255',
        ]);

        $cutoff->update($validated);

        Toastr::success(translate('Cutoff updated!'));
        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        NeetCategoryCutoff::findOrFail($id)->delete();

        Toastr::success(translate('Cutoff removed!'));
        return back();
    }
}
