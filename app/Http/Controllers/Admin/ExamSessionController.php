<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\ExamSession;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamSessionController extends Controller
{
    public function index(): View|Factory|Application
    {
        $examSessions = ExamSession::query()
            ->orderByDesc('year')
            ->orderByDesc('session')
            ->get();

        return view('admin-views.exam-session.index', compact('examSessions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exam_type' => 'required|in:jee_main',
            'year' => 'required|integer|min:2000|max:2100',
            'session' => 'required|in:january,april',
            'total_candidates' => 'required|integer|min:1',
            'source_note' => 'nullable|max:255',
        ]);

        $exists = ExamSession::where('exam_type', $validated['exam_type'])
            ->where('year', $validated['year'])
            ->where('session', $validated['session'])
            ->exists();

        if ($exists) {
            Toastr::warning(translate('A session for this exam, year and session already exists. Edit it instead.'));
            return back();
        }

        ExamSession::create($validated);

        Toastr::success(translate('Exam session added!'));
        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $examSession = ExamSession::findOrFail($id);

        $validated = $request->validate([
            'total_candidates' => 'required|integer|min:1',
            'source_note' => 'nullable|max:255',
        ]);

        $examSession->update($validated);

        Toastr::success(translate('Exam session updated!'));
        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        ExamSession::findOrFail($id)->delete();

        Toastr::success(translate('Exam session removed!'));
        return back();
    }
}
