<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\CollegeCutoff;
use App\Services\CollegeCutoffImporter;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollegeCutoffController extends Controller
{
    private const EXAM_TYPE = 'jee_main';

    public function index(): View|Factory|Application
    {
        $batches = CollegeCutoff::query()
            ->examType(self::EXAM_TYPE)
            ->select('year', 'round', DB::raw('count(*) as row_count'))
            ->groupBy('year', 'round')
            ->orderByDesc('year')
            ->orderByDesc('round')
            ->get();

        return view('admin-views.college-cutoff.index', compact('batches'));
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'round' => 'required|integer|min:1|max:10',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $result = CollegeCutoffImporter::importFromCsv(
                $validated['csv_file']->getRealPath(),
                $validated['year'],
                $validated['round'],
            );
        } catch (RuntimeException $e) {
            Toastr::error($e->getMessage());
            return back();
        }

        if ($result['imported'] === 0) {
            Toastr::error(translate('No valid rows found in the CSV.'));
            return back();
        }

        $message = translate('Imported') . ' ' . $result['imported'] . ' ' . translate('rows');
        if ($result['skipped'] > 0) {
            $message .= ' (' . $result['skipped'] . ' ' . translate('rows skipped') . ')';
        }
        Toastr::success($message);

        return back();
    }

    public function destroy(int $year, int $round): RedirectResponse
    {
        CollegeCutoff::examType(self::EXAM_TYPE)
            ->where('year', $year)
            ->where('round', $round)
            ->delete();

        Toastr::success(translate('Batch removed!'));
        return back();
    }
}
